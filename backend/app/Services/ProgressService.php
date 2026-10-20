<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserCourseStat;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    /**
     * @return array<string, mixed>
     */
    public function forStudent(User $student, ?int $courseId): array
    {
        if ($student->role !== 'student') {
            abort(403, 'Solo los estudiantes consultan su progreso.');
        }

        $course = $this->resolveCourse($student, $courseId);
        $this->ensureEnrolled($student, $course);

        $stats = UserCourseStat::query()
            ->firstOrCreate(
                [
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                ],
                [
                    'total_points' => 0,
                    'current_level' => 1,
                    'streak_days' => 0,
                ],
            );

        $levels = Level::query()
            ->where('course_id', $course->id)
            ->orderBy('level_number')
            ->get();

        $currentLevel = $levels->firstWhere('level_number', $stats->current_level)
            ?? $levels->first();

        $nextLevel = $levels
            ->where('level_number', '>', $stats->current_level)
            ->sortBy('level_number')
            ->first();

        $points = (int) $stats->total_points;
        $currentRequired = (int) ($currentLevel?->points_required ?? 0);
        $nextRequired = $nextLevel?->points_required;
        $pointsToNext = $nextRequired === null ? 0 : max(0, $nextRequired - $points);
        $range = $nextRequired !== null ? max(1, $nextRequired - $currentRequired) : 1;
        $progressPercent = $nextRequired === null
            ? 100.0
            : round(min(100, max(0, (($points - $currentRequired) / $range) * 100)), 2);

        $rank = UserCourseStat::query()
            ->where('course_id', $course->id)
            ->where(function ($query) use ($points, $student) {
                $query->where('total_points', '>', $points)
                    ->orWhere(function ($inner) use ($points, $student) {
                        $inner->where('total_points', $points)
                            ->where('user_id', '<', $student->id);
                    });
            })
            ->count() + 1;

        $earned = UserBadge::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->with('badge')
            ->get();

        $earnedIds = $earned->pluck('badge_id')->all();

        $available = Badge::query()
            ->where(function ($query) use ($course) {
                $query->where('course_id', $course->id)->orWhereNull('course_id');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'rule_type' => $badge->rule_type ?: $badge->trigger_type,
                'rule_value' => $badge->rule_value,
                'condition' => $this->badgeCondition($badge),
                'earned' => in_array($badge->id, $earnedIds, true),
            ])
            ->values()
            ->all();

        $upcoming = Activity::query()
            ->where('course_id', $course->id)
            ->where('is_published', true)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->limit(5)
            ->get(['id', 'title', 'type', 'due_at', 'base_points']);

        $recentGrades = ActivitySubmission::query()
            ->with('activity:id,title,course_id,type')
            ->where('user_id', $student->id)
            ->where('status', 'graded')
            ->whereHas('activity', fn ($query) => $query->where('course_id', $course->id))
            ->latest('graded_at')
            ->limit(5)
            ->get();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'current_level' => $currentLevel ? [
                'level_number' => $currentLevel->level_number,
                'name' => $currentLevel->name,
                'points_required' => $currentLevel->points_required,
            ] : [
                'level_number' => (int) $stats->current_level,
                'name' => 'Nivel '.$stats->current_level,
                'points_required' => $currentRequired,
            ],
            'next_level' => $nextLevel ? [
                'level_number' => $nextLevel->level_number,
                'name' => $nextLevel->name,
                'points_required' => $nextLevel->points_required,
            ] : null,
            'total_points' => $points,
            'available_points' => $points,
            'points_to_next' => $pointsToNext,
            'progress_percent' => $progressPercent,
            'rank' => $rank,
            'streak_days' => (int) $stats->streak_days,
            'is_max_level' => $nextLevel === null && $levels->isNotEmpty(),
            'badges' => [
                'earned' => $earned->map(fn (UserBadge $row) => [
                    'id' => $row->badge_id,
                    'name' => $row->badge?->name,
                    'icon' => $row->badge?->icon,
                    'awarded_at' => $row->awarded_at?->toIso8601String(),
                ])->values()->all(),
                'available' => $available,
            ],
            'upcoming_activities' => $upcoming,
            'recent_grades' => $recentGrades->map(fn (ActivitySubmission $submission) => [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'title' => $submission->activity?->title,
                'score' => $submission->score,
                'ai_feedback' => $submission->ai_feedback,
                'teacher_feedback' => $submission->teacher_feedback,
                'graded_at' => $submission->graded_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function resolveCourse(User $student, ?int $courseId): Course
    {
        if ($courseId) {
            return Course::findOrFail($courseId);
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', 'active')
            ->latest('enrolled_at')
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'course_id' => ['No tienes cursos inscritos.'],
            ]);
        }

        return Course::findOrFail($enrollment->course_id);
    }

    private function ensureEnrolled(User $student, Course $course): void
    {
        $enrolled = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            abort(403, 'No estás inscrito en este curso.');
        }
    }

    private function badgeCondition(Badge $badge): string
    {
        $type = $badge->rule_type ?: $badge->trigger_type;
        $value = $badge->rule_value;

        return match ($type) {
            Badge::RULE_FIRST_SUBMISSION => 'Realiza tu primer envío',
            Badge::RULE_SUBMISSIONS_COUNT => sprintf('Completa %d envíos', (int) $value),
            Badge::RULE_POINTS_TOTAL => sprintf('Alcanza %d puntos', (int) $value),
            Badge::RULE_LEVEL_REACHED => sprintf('Alcanza el nivel %d', (int) $value),
            Badge::RULE_MANUAL => 'Otorgada por el docente',
            default => $badge->description ?: 'Completa el desafío del curso',
        };
    }
}
