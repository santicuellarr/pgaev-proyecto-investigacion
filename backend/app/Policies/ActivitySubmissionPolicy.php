<?php

namespace App\Policies;

use App\Models\ActivitySubmission;
use App\Models\User;
use App\Policies\Concerns\ChecksCourseAccess;

class ActivitySubmissionPolicy
{
    use ChecksCourseAccess;

    public function view(User $user, ActivitySubmission $submission): bool
    {
        $submission->loadMissing('activity.course');

        if ($submission->user_id === $user->id) {
            return true;
        }

        return $this->canManageCourse($user, $submission->activity->course);
    }

    public function grade(User $user, ActivitySubmission $submission): bool
    {
        $submission->loadMissing('activity.course');

        return $this->canManageCourse($user, $submission->activity->course);
    }
}
