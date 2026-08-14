<?php

namespace App\Services;

use App\Exceptions\EnrollmentAlreadyExistsException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
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
        return Course::create([
            'owner_id' => $teacherId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'invite_code' => $this->generateUniqueInviteCode(),
            'is_active' => $data['is_active'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);
    }

    public function enrollStudent(string $inviteCode, int $studentId): Enrollment
    {
        $course = Course::where('invite_code', $inviteCode)->first();

        if (! $course) {
            throw ValidationException::withMessages([
                'invite_code' => ['El código de invitación no es válido.'],
            ]);
        }

        if (! $course->is_active) {
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
        return match ($user->role) {
            'teacher' => Course::where('owner_id', $user->id)->latest()->get(),
            'student' => Course::whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->with(['enrollments' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])->latest()->get(),
            'admin' => Course::latest()->get(),
            default => new Collection,
        };
    }

    private function generateUniqueInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Course::where('invite_code', $code)->exists());

        return $code;
    }
}
