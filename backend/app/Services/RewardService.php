<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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

        $query = Reward::query()
            ->where('course_id', $course->id)
            ->orderBy('cost_points');

        if ($user->role === 'student') {
            $query->where('stock', '>', 0);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateReward(Reward $reward, array $data, User $teacher): Reward
    {
        $reward->loadMissing('course');
        $this->ensureTeacherOwnsCourse($reward->course, $teacher);

        $reward->update([
            'name' => $data['name'] ?? $reward->name,
            'cost_points' => $data['cost_points'] ?? $reward->cost_points,
            'stock' => $data['stock'] ?? $reward->stock,
            'needs_approval' => array_key_exists('needs_approval', $data) ? (bool) $data['needs_approval'] : $reward->needs_approval,
            'available_from' => array_key_exists('available_from', $data) ? $data['available_from'] : $reward->available_from,
            'available_to' => array_key_exists('available_to', $data) ? $data['available_to'] : $reward->available_to,
        ]);

        return $reward->fresh();
    }

    public function deleteReward(Reward $reward, User $teacher): void
    {
        $reward->loadMissing('course');
        $this->ensureTeacherOwnsCourse($reward->course, $teacher);

        if ($reward->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'reward' => ['No se puede eliminar una recompensa que ya tiene canjes.'],
            ]);
        }

        $reward->delete();
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
