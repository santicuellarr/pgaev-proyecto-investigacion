<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RewardService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createReward(array $data, Course $course, User $teacher): Reward
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        return Reward::create([
            'course_id' => $course->id,
            'name' => $data['name'],
            'cost_points' => $data['cost_points'],
            'stock' => $data['stock'],
            'needs_approval' => $data['needs_approval'] ?? false,
            'available_from' => $data['available_from'] ?? null,
            'available_to' => $data['available_to'] ?? null,
        ]);
    }

    /**
     * @return Collection<int, Reward>
     */
    public function getRewardsForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessCourse($course, $user);

        return Reward::query()
            ->where('course_id', $course->id)
            ->where('stock', '>', 0)
            ->orderBy('cost_points')
            ->get();
    }

    private function ensureTeacherOwnsCourse(Course $course, User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar recompensas.');
        }

        if ($teacher->role !== 'admin' && $course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para gestionar recompensas de este curso.');
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

        abort(403, 'No tienes acceso a las recompensas de este curso.');
    }
}
