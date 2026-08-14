<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Database\Eloquent\Collection;

class LeaderboardService
{
    /**
     * @return Collection<int, UserCourseStat>
     */
    public function getCourseLeaderboard(Course $course, User $user): Collection
    {
        $this->ensureUserCanViewLeaderboard($course, $user);

        return UserCourseStat::query()
            ->where('course_id', $course->id)
            ->with('user:id,name,avatar_url')
            ->orderByDesc('total_points')
            ->orderBy('user_id')
            ->get();
    }

    private function ensureUserCanViewLeaderboard(Course $course, User $user): void
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

        abort(403, 'No tienes acceso a la clasificación de este curso.');
    }
}
