<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RewardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, course: Course, reward: Reward}
     */
    private function createRewardContext(int $studentPoints = 100): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Recompensas',
            'invite_code' => 'RWD001',
            'is_active' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        UserCourseStat::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => $studentPoints,
            'current_level' => 1,
            'streak_days' => 0,
        ]);

        $reward = Reward::create([
            'course_id' => $course->id,
            'name' => 'Descuento en certificado',
            'cost_points' => 50,
            'stock' => 5,
            'needs_approval' => false,
        ]);

        return compact('teacher', 'student', 'course', 'reward');
    }

    public function test_student_can_redeem_reward_and_points_are_deducted(): void
    {
        ['student' => $student, 'course' => $course, 'reward' => $reward] = $this->createRewardContext(100);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/rewards/{$reward->id}/redeem", [
                'idempotency_key' => $idempotencyKey,
            ]);

        $response->assertCreated()
            ->assertJsonPath('redemption.status', 'approved')
            ->assertJsonPath('redemption.reward_id', $reward->id);

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 50,
        ]);

        $this->assertDatabaseHas('point_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'delta' => -50,
            'balance_after' => 50,
            'source_type' => 'redemption',
        ]);

        $this->assertDatabaseHas('rewards', [
            'id' => $reward->id,
            'stock' => 4,
        ]);

        $this->assertDatabaseHas('redemptions', [
            'user_id' => $student->id,
            'reward_id' => $reward->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'approved',
        ]);
    }

    public function test_student_cannot_redeem_reward_with_insufficient_points(): void
    {
        ['student' => $student, 'reward' => $reward] = $this->createRewardContext(30);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/rewards/{$reward->id}/redeem", [
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['points']);

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'total_points' => 30,
        ]);

        $this->assertDatabaseCount('redemptions', 0);
        $this->assertDatabaseHas('rewards', [
            'id' => $reward->id,
            'stock' => 5,
        ]);
    }
}
