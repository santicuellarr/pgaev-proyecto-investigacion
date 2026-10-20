<?php

namespace App\Policies\Concerns;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

trait ChecksCourseAccess
{
    protected function canViewCourse(User $user, Course $course): bool
    {
        if ($user->ownsCourse($course)) {
            return true;
        }

        if (! $user->isStudent()) {
            return false;
        }

        return Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    protected function canManageCourse(User $user, Course $course): bool
    {
        return $user->ownsCourse($course);
    }
}
