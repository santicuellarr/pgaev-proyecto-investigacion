<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassSessionService
{
    public const POINT_SOURCE = 'attendance';

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * @return Collection<int, ClassSession>
     */
    public function listForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessCourse($course, $user);

        return ClassSession::query()
            ->where('course_id', $course->id)
            ->with(['attendances.user:id,name,email'])
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Course $course, array $data, User $teacher): ClassSession
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        return ClassSession::create([
            'course_id' => $course->id,
            'title' => $data['title'],
            'starts_at' => $data['starts_at'],
            'duration_min' => $data['duration_min'],
            'meeting_url' => $data['meeting_url'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSession $session, array $data, User $teacher): ClassSession
    {
        $session->loadMissing('course');
        $this->ensureTeacherOwnsCourse($session->course, $teacher);

        $session->update([
            'title' => $data['title'] ?? $session->title,
            'starts_at' => $data['starts_at'] ?? $session->starts_at,
            'duration_min' => $data['duration_min'] ?? $session->duration_min,
            'meeting_url' => array_key_exists('meeting_url', $data) ? $data['meeting_url'] : $session->meeting_url,
        ]);

        return $session->fresh(['attendances.user:id,name,email']);
    }

    public function delete(ClassSession $session, User $teacher): void
    {
        $session->loadMissing('course');
        $this->ensureTeacherOwnsCourse($session->course, $teacher);
        $session->delete();
    }

    /**
     * @param  array<int, array{user_id: int, status: string}>  $rows
     * @return array{session: ClassSession, points_awarded: array<int, array{user_id: int, points: int}>}
     */
    public function saveAttendance(ClassSession $session, array $rows, User $teacher): array
    {
        $session->loadMissing('course');
        $this->ensureTeacherOwnsCourse($session->course, $teacher);

        $pointsAwarded = [];

        DB::transaction(function () use ($session, $rows, &$pointsAwarded) {
            $enrolledIds = Enrollment::query()
                ->where('course_id', $session->course_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            foreach ($rows as $row) {
                $userId = (int) $row['user_id'];

                if (! in_array($userId, $enrolledIds, true)) {
                    throw ValidationException::withMessages([
                        'attendances' => ['Solo se puede registrar asistencia de estudiantes inscritos.'],
                    ]);
                }

                $attendance = Attendance::query()->updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'user_id' => $userId,
                    ],
                    [
                        'status' => $row['status'],
                    ],
                );

                if (! $attendance->awardsPoints()) {
                    continue;
                }

                $alreadyAwarded = PointLog::query()
                    ->where('user_id', $userId)
                    ->where('course_id', $session->course_id)
                    ->where('source_type', self::POINT_SOURCE)
                    ->where('source_id', $session->id)
                    ->exists();

                if ($alreadyAwarded) {
                    continue;
                }

                $points = $attendance->status === Attendance::STATUS_LATE
                    ? (int) config('pgaev.points.attendance_late')
                    : (int) config('pgaev.points.attendance_present');

                if ($points <= 0) {
                    continue;
                }

                $this->gamificationService->awardPoints(
                    $userId,
                    $session->course_id,
                    $points,
                    self::POINT_SOURCE,
                    $session->id,
                );

                $pointsAwarded[] = [
                    'user_id' => $userId,
                    'points' => $points,
                ];
            }
        });

        return [
            'session' => $session->fresh(['attendances.user:id,name,email']),
            'points_awarded' => $pointsAwarded,
        ];
    }

    private function ensureTeacherOwnsCourse(Course $course, User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar las sesiones.');
        }

        if ($teacher->role !== 'admin' && $course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para gestionar las sesiones de este curso.');
        }
    }

    private function ensureUserCanAccessCourse(Course $course, User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'teacher' && $course->owner_id === $user->id) {
            return;
        }

        if ($user->role === 'student') {
            $isEnrolled = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($isEnrolled) {
                return;
            }
        }

        abort(403, 'No tienes acceso a las sesiones de este curso.');
    }
}
