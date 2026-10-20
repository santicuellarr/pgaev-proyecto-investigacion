<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\Redemption;
use App\Models\User;
use App\Models\UserCourseStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public const CSV_TYPES = [
        'completion',
        'distribution',
        'low_participation',
        'attendance',
        'forums',
    ];

    /**
     * @return array<string, mixed>
     */
    public function getCourseAnalytics(Course $course, User $user): array
    {
        $this->ensureTeacherOwnsCourse($course, $user);

        return $this->buildCourseAnalytics($course);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTeacherOverview(User $user): array
    {
        if (! in_array($user->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden ver el resumen analítico.');
        }

        $courses = Course::query()
            ->when($user->role !== 'admin', fn ($query) => $query->where('owner_id', $user->id))
            ->orderBy('title')
            ->get();

        $courses = $courses->values();
        $payloads = $courses->map(fn (Course $course) => $this->buildCourseAnalytics($course))->values();

        return $this->mergeOverviews($courses, $payloads);
    }

    public function exportCourseCsv(int $courseId, User $user, ?string $type = null): StreamedResponse
    {
        $course = Course::findOrFail($courseId);
        $this->ensureTeacherOwnsCourse($course, $user);

        if ($type === null || $type === '') {
            return $this->exportStudentsCsv($course);
        }

        if (! in_array($type, self::CSV_TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => ['Tipo de reporte no válido.'],
            ]);
        }

        $analytics = $this->buildCourseAnalytics($course);
        $filename = sprintf('analytics-curso-%d-%s.csv', $course->id, $type);

        return response()->streamDownload(function () use ($type, $analytics) {
            $handle = fopen('php://output', 'w');
            $this->writeTypedCsv($handle, $type, $analytics);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCourseAnalytics(Course $course): array
    {
        $enrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get();

        $enrolled = $enrollments->count();
        $studentIds = $enrollments->pluck('user_id')->all();

        $activities = Activity::query()
            ->where('course_id', $course->id)
            ->where('is_published', true)
            ->orderBy('title')
            ->get();

        $submissions = ActivitySubmission::query()
            ->whereIn('activity_id', $activities->pluck('id')->all() ?: [0])
            ->get();

        $stats = UserCourseStat::query()
            ->where('course_id', $course->id)
            ->whereIn('user_id', $studentIds ?: [0])
            ->with('user:id,name,email,avatar_url')
            ->get()
            ->keyBy('user_id');

        $sessions = ClassSession::query()
            ->where('course_id', $course->id)
            ->orderBy('starts_at')
            ->get();

        $attendances = Attendance::query()
            ->whereIn('session_id', $sessions->pluck('id')->all() ?: [0])
            ->get();

        $forumActivities = $activities->where('type', 'foro')->values();
        $forumPosts = ForumPost::query()
            ->whereIn('activity_id', $forumActivities->pluck('id')->all() ?: [0])
            ->get();

        $redemptions = Redemption::query()
            ->whereHas('reward', fn ($query) => $query->where('course_id', $course->id))
            ->get();

        $completionByActivity = $activities->map(function (Activity $activity) use ($submissions, $enrolled) {
            $uniqueSubmitters = $submissions
                ->where('activity_id', $activity->id)
                ->pluck('user_id')
                ->unique()
                ->count();

            return [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
                'enrolled' => $enrolled,
                'unique_submissions' => $uniqueSubmitters,
                'completion_rate' => $this->rate($uniqueSubmitters, $enrolled),
            ];
        })->values()->all();

        $graded = $submissions->where('status', 'graded')->filter(fn ($row) => $row->score !== null);
        $averageScore = $graded->count() > 0
            ? round((float) $graded->avg(fn ($row) => (float) $row->score), 2)
            : null;

        $totalPoints = (int) $stats->sum('total_points');

        $attendedMarks = $attendances->filter(fn (Attendance $row) => $row->awardsPoints())->count();
        $expectedAttendance = $sessions->count() * $enrolled;
        $attendanceRate = $sessions->isEmpty() ? null : $this->rate($attendedMarks, $expectedAttendance);

        $forumParticipants = $forumPosts->pluck('user_id')->unique()->count();
        $forumParticipationRate = $forumActivities->isEmpty()
            ? null
            : $this->rate($forumParticipants, $enrolled);

        $redemptionsByStatus = [
            'pending' => $redemptions->where('status', 'pending')->count(),
            'approved' => $redemptions->where('status', 'approved')->count(),
            'rejected' => $redemptions->where('status', 'rejected')->count(),
        ];

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'kpis' => [
                'enrolled' => $enrolled,
                'average_score' => $averageScore,
                'total_points' => $totalPoints,
                'attendance_rate' => $attendanceRate,
                'forum_participation_rate' => $forumParticipationRate,
                'redemptions_by_status' => $redemptionsByStatus,
            ],
            'completion_by_activity' => $completionByActivity,
            'points_distribution' => $this->pointsDistribution($studentIds, $stats),
            'submissions_by_week' => $this->submissionsByWeek($submissions),
            'low_participation' => $this->lowParticipation(
                $enrollments,
                $activities,
                $submissions,
                $sessions,
                $attendances,
            ),
            'leaderboard_top' => $stats
                ->sortByDesc('total_points')
                ->take(5)
                ->values()
                ->map(fn (UserCourseStat $stat) => [
                    'user_id' => $stat->user_id,
                    'name' => $stat->user?->name,
                    'total_points' => $stat->total_points,
                    'current_level' => $stat->current_level,
                    'avatar_url' => $stat->user?->avatar_url,
                ])
                ->all(),
            'attendance_by_session' => $sessions->map(function (ClassSession $session) use ($attendances, $enrolled) {
                $rows = $attendances->where('session_id', $session->id);
                $present = $rows->where('status', Attendance::STATUS_PRESENT)->count();
                $late = $rows->where('status', Attendance::STATUS_LATE)->count();
                $absent = $rows->where('status', Attendance::STATUS_ABSENT)->count();

                return [
                    'session_id' => $session->id,
                    'title' => $session->title,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'attendance_rate' => $this->rate($present + $late, $enrolled),
                ];
            })->values()->all(),
            'forums' => $forumActivities->map(function (Activity $activity) use ($forumPosts, $enrolled) {
                $posts = $forumPosts->where('activity_id', $activity->id);
                $participants = $posts->pluck('user_id')->unique()->count();

                return [
                    'activity_id' => $activity->id,
                    'title' => $activity->title,
                    'posts' => $posts->count(),
                    'participants' => $participants,
                    'participation_rate' => $this->rate($participants, $enrolled),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, array<string, mixed>>  $payloads
     * @return array<string, mixed>
     */
    private function mergeOverviews(Collection $courses, Collection $payloads): array
    {
        $enrolled = $payloads->sum(fn (array $row) => $row['kpis']['enrolled']);
        $totalPoints = $payloads->sum(fn (array $row) => $row['kpis']['total_points']);

        $scoreWeighted = 0.0;
        $scoreCount = 0;
        foreach ($payloads as $payload) {
            if ($payload['kpis']['average_score'] === null) {
                continue;
            }

            $count = max(1, $payload['kpis']['enrolled']);
            $scoreWeighted += $payload['kpis']['average_score'] * $count;
            $scoreCount += $count;
        }

        $attendanceRates = $payloads
            ->pluck('kpis.attendance_rate')
            ->filter(fn ($rate) => $rate !== null);
        $forumRates = $payloads
            ->pluck('kpis.forum_participation_rate')
            ->filter(fn ($rate) => $rate !== null);

        $redemptions = [
            'pending' => $payloads->sum(fn (array $row) => $row['kpis']['redemptions_by_status']['pending']),
            'approved' => $payloads->sum(fn (array $row) => $row['kpis']['redemptions_by_status']['approved']),
            'rejected' => $payloads->sum(fn (array $row) => $row['kpis']['redemptions_by_status']['rejected']),
        ];

        $distribution = [];
        foreach ($payloads as $payload) {
            foreach ($payload['points_distribution'] as $bucket) {
                $key = $bucket['bucket'];
                $distribution[$key] = ($distribution[$key] ?? 0) + $bucket['count'];
            }
        }

        $weeks = [];
        foreach ($payloads as $payload) {
            foreach ($payload['submissions_by_week'] as $week) {
                $key = $week['week_start'];
                $weeks[$key] = ($weeks[$key] ?? 0) + $week['count'];
            }
        }
        ksort($weeks);

        $low = [];
        foreach ($payloads as $index => $payload) {
            foreach ($payload['low_participation'] as $row) {
                $row['course_id'] = $courses[$index]->id;
                $row['course_title'] = $courses[$index]->title;
                $low[] = $row;
            }
        }

        $top = $payloads
            ->flatMap(function (array $payload, int $index) use ($courses) {
                return collect($payload['leaderboard_top'])->map(function (array $row) use ($courses, $index) {
                    $row['course_id'] = $courses[$index]->id;
                    $row['course_title'] = $courses[$index]->title;

                    return $row;
                });
            })
            ->sortByDesc('total_points')
            ->take(5)
            ->values()
            ->all();

        return [
            'kpis' => [
                'courses' => $courses->count(),
                'enrolled' => $enrolled,
                'average_score' => $scoreCount > 0 ? round($scoreWeighted / $scoreCount, 2) : null,
                'total_points' => $totalPoints,
                'attendance_rate' => $attendanceRates->count() ? round($attendanceRates->avg(), 2) : null,
                'forum_participation_rate' => $forumRates->count() ? round($forumRates->avg(), 2) : null,
                'redemptions_by_status' => $redemptions,
            ],
            'completion_by_activity' => $payloads
                ->flatMap(function (array $payload, int $index) use ($courses) {
                    return collect($payload['completion_by_activity'])->map(function (array $row) use ($courses, $index) {
                        $row['course_id'] = $courses[$index]->id;
                        $row['course_title'] = $courses[$index]->title;

                        return $row;
                    });
                })
                ->values()
                ->all(),
            'points_distribution' => collect($distribution)
                ->map(fn (int $count, string $bucket) => ['bucket' => $bucket, 'count' => $count])
                ->values()
                ->all(),
            'submissions_by_week' => collect($weeks)
                ->map(fn (int $count, string $weekStart) => ['week_start' => $weekStart, 'count' => $count])
                ->values()
                ->all(),
            'low_participation' => $low,
            'leaderboard_top' => $top,
        ];
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  Collection<int, UserCourseStat>  $stats
     * @return array<int, array{bucket: string, count: int}>
     */
    private function pointsDistribution(array $studentIds, Collection $stats): array
    {
        $edges = array_values(config('pgaev.analytics.point_buckets', [0, 50, 100, 150, 200, 300]));
        $buckets = [];

        for ($i = 0; $i < count($edges); $i++) {
            $from = $edges[$i];
            $to = $edges[$i + 1] ?? null;
            $label = $to === null ? sprintf('%d+', $from) : sprintf('%d-%d', $from, $to - 1);
            $buckets[$label] = 0;
        }

        foreach ($studentIds as $userId) {
            $points = (int) ($stats[$userId]->total_points ?? 0);
            $assigned = false;

            for ($i = 0; $i < count($edges); $i++) {
                $from = $edges[$i];
                $to = $edges[$i + 1] ?? null;
                $label = $to === null ? sprintf('%d+', $from) : sprintf('%d-%d', $from, $to - 1);

                if ($to === null ? $points >= $from : $points >= $from && $points < $to) {
                    $buckets[$label]++;
                    $assigned = true;
                    break;
                }
            }

            if (! $assigned && $buckets !== []) {
                $last = array_key_last($buckets);
                $buckets[$last]++;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $bucket) => ['bucket' => $bucket, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ActivitySubmission>  $submissions
     * @return array<int, array{week_start: string, count: int}>
     */
    private function submissionsByWeek(Collection $submissions): array
    {
        $weeks = (int) config('pgaev.analytics.weeks', 8);
        $series = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i)->startOfDay();
            $series[$start->toDateString()] = 0;
        }

        foreach ($submissions as $submission) {
            if (! $submission->submitted_at) {
                continue;
            }

            $weekStart = Carbon::parse($submission->submitted_at)->startOfWeek(Carbon::MONDAY)->toDateString();

            if (array_key_exists($weekStart, $series)) {
                $series[$weekStart]++;
            }
        }

        return collect($series)
            ->map(fn (int $count, string $weekStart) => ['week_start' => $weekStart, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, ActivitySubmission>  $submissions
     * @param  Collection<int, ClassSession>  $sessions
     * @param  Collection<int, Attendance>  $attendances
     * @return array<int, array<string, mixed>>
     */
    private function lowParticipation(
        Collection $enrollments,
        Collection $activities,
        Collection $submissions,
        Collection $sessions,
        Collection $attendances,
    ): array {
        $days = (int) config('pgaev.analytics.inactivity_days', 14);
        $threshold = (int) config('pgaev.analytics.low_completion_rate', 50);
        $since = Carbon::now()->subDays($days);
        $publishedCount = $activities->count();

        $rows = [];

        foreach ($enrollments as $enrollment) {
            $userId = $enrollment->user_id;
            $userSubs = $submissions->where('user_id', $userId);
            $completed = $userSubs->pluck('activity_id')->unique()->count();
            $completionRate = $this->rate($completed, $publishedCount);

            $recentSubmissions = $userSubs
                ->filter(fn (ActivitySubmission $row) => $row->submitted_at && $row->submitted_at->gte($since))
                ->count();

            $recentAttendance = $attendances
                ->where('user_id', $userId)
                ->filter(function (Attendance $row) use ($sessions, $since) {
                    if (! $row->awardsPoints()) {
                        return false;
                    }

                    $session = $sessions->firstWhere('id', $row->session_id);

                    return $session?->starts_at && $session->starts_at->gte($since);
                })
                ->count();

            $inactive = $recentSubmissions === 0 && $recentAttendance === 0;
            $lowCompletion = $completionRate < $threshold;

            if (! $inactive && ! $lowCompletion) {
                continue;
            }

            $reasons = [];
            if ($inactive) {
                $reasons[] = sprintf('Sin envíos ni asistencia en %d días', $days);
            }
            if ($lowCompletion) {
                $reasons[] = sprintf('Completitud menor a %d%%', $threshold);
            }

            $rows[] = [
                'user_id' => $userId,
                'name' => $enrollment->user?->name,
                'email' => $enrollment->user?->email,
                'completion_rate' => $completionRate,
                'recent_submissions' => $recentSubmissions,
                'recent_attendance' => $recentAttendance,
                'reasons' => $reasons,
            ];
        }

        return $rows;
    }

    private function exportStudentsCsv(Course $course): StreamedResponse
    {
        $filename = sprintf('analytics-curso-%d.csv', $course->id);

        return response()->streamDownload(function () use ($course) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Nombre Estudiante',
                'Email',
                'Puntos Totales',
                'Nivel Actual',
                'Tareas Completadas',
            ]);

            $enrollments = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('status', 'active')
                ->with('user')
                ->orderBy('enrolled_at')
                ->get();

            foreach ($enrollments as $enrollment) {
                $student = $enrollment->user;

                $stats = UserCourseStat::query()
                    ->where('user_id', $student->id)
                    ->where('course_id', $course->id)
                    ->first();

                $completedTasks = ActivitySubmission::query()
                    ->where('user_id', $student->id)
                    ->where('status', 'graded')
                    ->whereHas('activity', function ($query) use ($course) {
                        $query->where('course_id', $course->id);
                    })
                    ->count();

                fputcsv($handle, [
                    $student->name,
                    $student->email,
                    $stats?->total_points ?? 0,
                    $stats?->current_level ?? 1,
                    $completedTasks,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $analytics
     */
    private function writeTypedCsv($handle, string $type, array $analytics): void
    {
        if ($type === 'completion') {
            fputcsv($handle, ['Actividad', 'Inscritos', 'Envíos únicos', 'Completitud %']);
            foreach ($analytics['completion_by_activity'] as $row) {
                fputcsv($handle, [$row['title'], $row['enrolled'], $row['unique_submissions'], $row['completion_rate']]);
            }

            return;
        }

        if ($type === 'distribution') {
            fputcsv($handle, ['Rango', 'Estudiantes']);
            foreach ($analytics['points_distribution'] as $row) {
                fputcsv($handle, [$row['bucket'], $row['count']]);
            }

            return;
        }

        if ($type === 'low_participation') {
            fputcsv($handle, ['Nombre', 'Email', 'Completitud %', 'Envíos 14d', 'Asistencias 14d', 'Motivo']);
            foreach ($analytics['low_participation'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['completion_rate'],
                    $row['recent_submissions'],
                    $row['recent_attendance'],
                    implode('; ', $row['reasons']),
                ]);
            }

            return;
        }

        if ($type === 'attendance') {
            fputcsv($handle, ['Sesión', 'Fecha', 'Presentes', 'Tarde', 'Ausentes', 'Asistencia %']);
            foreach ($analytics['attendance_by_session'] as $row) {
                fputcsv($handle, [
                    $row['title'],
                    $row['starts_at'],
                    $row['present'],
                    $row['late'],
                    $row['absent'],
                    $row['attendance_rate'],
                ]);
            }

            return;
        }

        fputcsv($handle, ['Foro', 'Aportes', 'Participantes', 'Participación %']);
        foreach ($analytics['forums'] as $row) {
            fputcsv($handle, [$row['title'], $row['posts'], $row['participants'], $row['participation_rate']]);
        }
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function ensureTeacherOwnsCourse(Course $course, User $user): void
    {
        if (! in_array($user->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden consultar analíticas del curso.');
        }

        if ($user->role !== 'admin' && $course->owner_id !== $user->id) {
            abort(403, 'No tienes permiso para consultar analíticas de este curso.');
        }
    }
}
