<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Collection;

class BadgeService
{
    /**
     * @return Collection<int, UserBadge>
     */
    public function getUserBadgesForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessBadges($course, $user);

        return UserBadge::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();
    }

    private function ensureUserCanAccessBadges(Course $course, User $user): void
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

        abort(403, 'No tienes acceso a las insignias de este curso.');
    }
}
