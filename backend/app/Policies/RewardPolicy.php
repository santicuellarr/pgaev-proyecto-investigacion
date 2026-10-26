<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Reward;
use App\Models\User;
use App\Policies\Concerns\ChecksCourseAccess;

class RewardPolicy
{
    use ChecksCourseAccess;

    public function view(User $user, Reward $reward): bool
    {
        $reward->loadMissing('course');

        return $this->canViewCourse($user, $reward->course);
    }

    public function create(User $user, Course $course): bool
    {
        return $this->canManageCourse($user, $course);
    }

    public function update(User $user, Reward $reward): bool
    {
        $reward->loadMissing('course');

        return $this->canManageCourse($user, $reward->course);
    }

    public function delete(User $user, Reward $reward): bool
    {
        return $this->update($user, $reward);
    }

    public function redeem(User $user, Reward $reward): bool
    {
        $reward->loadMissing('course');

        return $user->isStudent() && $this->canViewCourse($user, $reward->course);
    }
}
