<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createActivity(array $data, Course $course, User $teacher): Activity
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        return Activity::create([
            'course_id' => $course->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'base_points' => $data['base_points'],
            'max_attempts' => $data['max_attempts'],
            'due_at' => $data['due_at'] ?? null,
            'is_published' => $data['is_published'] ?? false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateActivity(Activity $activity, array $data, User $teacher): Activity
    {
        $activity->loadMissing('course');
        $this->ensureTeacherOwnsCourse($activity->course, $teacher);

        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'type' => $data['type'] ?? $activity->type,
            'base_points' => $data['base_points'] ?? $activity->base_points,
            'max_attempts' => $data['max_attempts'] ?? $activity->max_attempts,
            'due_at' => array_key_exists('due_at', $data) ? $data['due_at'] : $activity->due_at,
            'is_published' => array_key_exists('is_published', $data) ? (bool) $data['is_published'] : $activity->is_published,
        ]);

        return $activity->fresh();
    }

    public function publishActivity(Activity $activity, User $teacher, bool $published = true): Activity
    {
        $activity->loadMissing('course');
        $this->ensureTeacherOwnsCourse($activity->course, $teacher);

        $activity->update([
            'is_published' => $published,
        ]);

        return $activity->fresh();
    }

    public function deleteActivity(Activity $activity, User $teacher): void
    {
        $activity->loadMissing('course');
        $this->ensureTeacherOwnsCourse($activity->course, $teacher);

        if ($activity->submissions()->exists()) {
            throw ValidationException::withMessages([
                'activity' => ['No se puede eliminar una actividad que ya tiene envíos.'],
            ]);
        }

        $activity->delete();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivitiesForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessCourse($course, $user);

        return Activity::where('course_id', $course->id)
            ->when($user->role === 'student', fn ($query) => $query->where('is_published', true))
            ->latest()
            ->get();
    }

    public function getActivity(Activity $activity, User $user): Activity
    {
        $activity->loadMissing('course');

        $this->ensureUserCanAccessCourse($activity->course, $user);

        if ($user->role === 'student' && ! $activity->is_published) {
            abort(404);
        }

        return $activity;
    }

    public function getStudentLatestSubmission(Activity $activity, User $user): ?ActivitySubmission
    {
        if ($user->role !== 'student') {
            return null;
        }

        return ActivitySubmission::where('activity_id', $activity->id)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->first();
    }

    /**
     * @return Collection<int, ActivitySubmission>
     */
    public function listSubmissionsForTeacher(Activity $activity, User $teacher): Collection
    {
        $activity->loadMissing('course');
        $this->ensureTeacherOwnsCourse($activity->course, $teacher);

        return ActivitySubmission::query()
            ->with('user:id,name,email')
            ->where('activity_id', $activity->id)
            ->latest('submitted_at')
            ->get();
    }

    private function ensureTeacherOwnsCourse(Course $course, User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar actividades.');
        }

        if ($teacher->role !== 'admin' && $course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para gestionar actividades de este curso.');
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
            $isEnrolled = Enrollment::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($isEnrolled) {
                return;
            }
        }

        abort(403, 'No tienes acceso a las actividades de este curso.');
    }
}
