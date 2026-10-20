<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\Badge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\Level;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserCourseStat;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_meets_minimum_counts_and_point_log_coherence(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(34, User::query()->count());
        $this->assertSame(1, User::query()->where('role', User::ROLE_ADMIN)->count());
        $this->assertSame(3, User::query()->where('role', User::ROLE_TEACHER)->count());
        $this->assertGreaterThanOrEqual(30, User::query()->where('role', User::ROLE_STUDENT)->count());

        $this->assertSame(4, Course::query()->count());
        $this->assertTrue(
            Course::query()->get()->every(fn (Course $course) => $course->enrollments()->count() >= 20),
        );
        $this->assertSame(24, Level::query()->count());
        $this->assertGreaterThanOrEqual(28, Activity::query()->count());
        $this->assertGreaterThanOrEqual(1, Activity::query()->where('is_published', false)->count());
        $this->assertSame(24, ClassSession::query()->count());
        $this->assertSame(16, Reward::query()->count());
        $this->assertGreaterThanOrEqual(20, Badge::query()->count());
        $this->assertGreaterThanOrEqual(40, ActivitySubmission::query()->count());
        $this->assertGreaterThanOrEqual(20, ActivitySubmission::query()->where('status', 'graded')->count());
        $this->assertGreaterThanOrEqual(20, ForumPost::query()->count());
        $this->assertGreaterThanOrEqual(40, Attendance::query()->count());
        $this->assertGreaterThanOrEqual(1, Redemption::query()->where('status', 'pending')->count());
        $this->assertGreaterThanOrEqual(1, Redemption::query()->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(1, Redemption::query()->where('status', 'rejected')->count());

        $this->assertDatabaseHas('users', [
            'email' => 'admin@pgaev.edu.co',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'docente@pgaev.edu.co',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'estudiante1@pgaev.edu.co',
            'role' => User::ROLE_STUDENT,
        ]);

        foreach (UserCourseStat::query()->get() as $stat) {
            $sum = (int) PointLog::query()
                ->where('user_id', $stat->user_id)
                ->where('course_id', $stat->course_id)
                ->sum('delta');

            $this->assertSame(
                $sum,
                (int) $stat->total_points,
                "Puntos incoherentes para usuario {$stat->user_id} en curso {$stat->course_id}",
            );
        }
    }
}
