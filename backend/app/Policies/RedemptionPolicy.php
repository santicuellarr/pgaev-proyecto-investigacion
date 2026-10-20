<?php

namespace App\Policies;

use App\Models\Redemption;
use App\Models\User;
use App\Policies\Concerns\ChecksCourseAccess;

class RedemptionPolicy
{
    use ChecksCourseAccess;

    public function view(User $user, Redemption $redemption): bool
    {
        $redemption->loadMissing('reward.course');

        if ($redemption->user_id === $user->id) {
            return true;
        }

        return $this->canManageCourse($user, $redemption->reward->course);
    }

    public function approve(User $user, Redemption $redemption): bool
    {
        $redemption->loadMissing('reward.course');

        return $this->canManageCourse($user, $redemption->reward->course);
    }

    public function reject(User $user, Redemption $redemption): bool
    {
        return $this->approve($user, $redemption);
    }
}
