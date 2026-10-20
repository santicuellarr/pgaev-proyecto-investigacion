<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use App\Policies\Concerns\ChecksCourseAccess;

class ActivityPolicy
{
    use ChecksCourseAccess;

    public function view(User $user, Activity $activity): bool
    {
        $activity->loadMissing('course');

        return $this->canViewCourse($user, $activity->course);
    }

    public function create(User $user, Course $course): bool
    {
        return $this->canManageCourse($user, $course);
    }

    public function update(User $user, Activity $activity): bool
    {
        $activity->loadMissing('course');

        return $this->canManageCourse($user, $activity->course);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function submit(User $user, Activity $activity): bool
    {
        $activity->loadMissing('course');

        return $user->isStudent() && $this->canViewCourse($user, $activity->course);
    }
}
