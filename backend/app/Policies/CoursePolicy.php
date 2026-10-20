<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Policies\Concerns\ChecksCourseAccess;

class CoursePolicy
{
    use ChecksCourseAccess;

    public function view(User $user, Course $course): bool
    {
        return $this->canViewCourse($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Course $course): bool
    {
        return $this->canManageCourse($user, $course);
    }

    public function enroll(User $user): bool
    {
        return $user->isStudent();
    }
}
