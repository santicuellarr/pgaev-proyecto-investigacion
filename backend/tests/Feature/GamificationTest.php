<?php

namespace Tests\Feature;

use App\Events\LevelUpEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\User;
use App\Models\UserCourseStat;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, course: Course}
     */
    private function createGamificationContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Gamificación',
            'invite_code' => 'GAM001',
            'is_active' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        Level::create([
            'course_id' => $course->id,
            'level_number' => 1,
            'name' => 'Novato',
            'points_required' => 0,
        ]);

        Level::create([
            'course_id' => $course->id,
            'level_number' => 2,
            'name' => 'Aprendiz',
            'points_required' => 100,
        ]);

        UserCourseStat::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 0,
            'current_level' => 1,
            'streak_days' => 0,
        ]);

        return compact('teacher', 'student', 'course');
    }

    public function test_award_points_updates_balance_inside_transaction(): void
    {
        ['student' => $student, 'course' => $course] = $this->createGamificationContext();

        $service = app(GamificationService::class);

        $pointLog = $service->awardPoints(
            $student->id,
            $course->id,
            50,
            'activity_submission',
        );

        $this->assertDatabaseHas('point_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'delta' => 50,
            'balance_after' => 50,
            'source_type' => 'activity_submission',
        ]);

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 50,
            'current_level' => 1,
        ]);

        $this->assertSame(50, $pointLog->balance_after);
    }

    public function test_level_up_event_is_dispatched_when_threshold_is_crossed(): void
    {
        Event::fake([LevelUpEvent::class]);

        ['student' => $student, 'course' => $course] = $this->createGamificationContext();

        $service = app(GamificationService::class);

        $service->awardPoints($student->id, $course->id, 60, 'activity_submission');
        $service->awardPoints($student->id, $course->id, 50, 'activity_submission');

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 110,
            'current_level' => 2,
        ]);

        Event::assertDispatched(LevelUpEvent::class, function (LevelUpEvent $event) use ($student, $course) {
            return $event->userId === $student->id
                && $event->courseId === $course->id
                && $event->newLevel === 2;
        });
    }

    public function test_leaderboard_returns_users_ordered_by_total_points(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course] = $this->createGamificationContext();

        $studentTwo = User::factory()->create(['role' => 'student']);

        Enrollment::create([
            'user_id' => $studentTwo->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        UserCourseStat::create([
            'user_id' => $studentTwo->id,
            'course_id' => $course->id,
            'total_points' => 200,
            'current_level' => 2,
            'streak_days' => 0,
        ]);

        UserCourseStat::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->update(['total_points' => 75]);

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/courses/{$course->id}/leaderboard");

        $response->assertOk()
            ->assertJsonPath('leaderboard.0.user_id', $studentTwo->id)
            ->assertJsonPath('leaderboard.0.total_points', 200)
            ->assertJsonPath('leaderboard.1.user_id', $student->id)
            ->assertJsonPath('leaderboard.1.total_points', 75);
    }
}
