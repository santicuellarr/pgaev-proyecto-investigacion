<?php

namespace App\Services;

use App\Exceptions\EnrollmentAlreadyExistsException;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCourse(array $data, int $teacherId): Course
    {
        $status = $data['status'] ?? Course::STATUS_PUBLISHED;

        return Course::create([
            'owner_id' => $teacherId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'invite_code' => $this->generateUniqueInviteCode(),
            'status' => $status,
            'color' => $data['color'] ?? '#4f46e5',
            'is_active' => $status === Course::STATUS_PUBLISHED,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCourse(Course $course, array $data, User $teacher): Course
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        $status = $data['status'] ?? $course->status;

        $course->update([
            'title' => $data['title'] ?? $course->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $course->description,
            'color' => $data['color'] ?? $course->color,
            'status' => $status,
            'is_active' => $status === Course::STATUS_PUBLISHED,
            'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $course->starts_at,
            'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $course->ends_at,
        ]);

        return $course->fresh();
    }

    public function updateStatus(Course $course, string $status, User $teacher): Course
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        $course->update([
            'status' => $status,
            'is_active' => $status === Course::STATUS_PUBLISHED,
        ]);

        return $course->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCourseDetail(Course $course, User $user): array
    {
        $this->ensureUserCanViewCourse($course, $user);

        $course = $this->applyCounts(Course::query()->whereKey($course->id))->firstOrFail();

        $enrollments = $course->enrollments()
            ->with('user:id,name,email,role')
            ->where('status', 'active')
            ->orderBy('enrolled_at')
            ->get();

        $activities = $course->activities()->latest()->get();

        $pendingSubmissions = ActivitySubmission::query()
            ->with([
                'user:id,name,email',
                'activity:id,title,course_id,type',
            ])
            ->where('status', 'submitted')
            ->whereHas('activity', fn ($query) => $query->where('course_id', $course->id))
            ->latest('submitted_at')
            ->get();

        return [
            'course' => $course,
            'counts' => [
                'enrolled' => (int) $course->enrolled_count,
                'activities' => (int) $course->activities_count,
                'pending_submissions' => (int) $course->pending_submissions_count,
            ],
            'enrollments' => $enrollments,
            'activities' => $activities,
            'pending_submissions' => $pendingSubmissions,
            'rewards' => Reward::query()->where('course_id', $course->id)->orderBy('cost_points')->get(),
            'sessions' => ClassSession::query()
                ->where('course_id', $course->id)
                ->with(['attendances.user:id,name,email'])
                ->orderByDesc('starts_at')
                ->get(),
            'badges' => Badge::query()
                ->where(function ($query) use ($course) {
                    $query->where('course_id', $course->id)->orWhereNull('course_id');
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    public function enrollStudent(string $inviteCode, int $studentId): Enrollment
    {
        $course = Course::where('invite_code', $inviteCode)->first();

        if (! $course) {
            throw ValidationException::withMessages([
                'invite_code' => ['El código de invitación no es válido.'],
            ]);
        }

        if (! $course->is_active || $course->status !== Course::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'invite_code' => ['Este curso no está activo.'],
            ]);
        }

        $alreadyEnrolled = Enrollment::where('user_id', $studentId)
            ->where('course_id', $course->id)
            ->exists();

        if ($alreadyEnrolled) {
            throw new EnrollmentAlreadyExistsException;
        }

        return Enrollment::create([
            'user_id' => $studentId,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCoursesForUser(User $user): Collection
    {
        $query = $this->applyCounts(Course::query());

        return match ($user->role) {
            'teacher' => $query->where('owner_id', $user->id)->latest()->get(),
            'student' => $query->whereHas('enrollments', function ($enrollmentQuery) use ($user) {
                $enrollmentQuery->where('user_id', $user->id);
            })->with(['enrollments' => function ($enrollmentQuery) use ($user) {
                $enrollmentQuery->where('user_id', $user->id);
            }])->latest()->get(),
            'admin' => $query->latest()->get(),
            default => new Collection,
        };
    }

    private function applyCounts(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as enrolled_count' => fn ($enrollmentQuery) => $enrollmentQuery->where('status', 'active'),
            'activities as activities_count',
            'submissions as pending_submissions_count' => fn ($submissionQuery) => $submissionQuery->where('status', 'submitted'),
        ]);
    }

    private function generateUniqueInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Course::where('invite_code', $code)->exists());

        return $code;
    }

    private function ensureTeacherOwnsCourse(Course $course, User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar cursos.');
        }

        if ($teacher->role !== 'admin' && $course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para modificar este curso.');
        }
    }

    private function ensureUserCanViewCourse(Course $course, User $user): void
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

        abort(403, 'No tienes acceso a este curso.');
    }
}
