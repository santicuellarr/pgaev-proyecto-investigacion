<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_database_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_unauthenticated_request_returns_uniform_401(): void
    {
        $this->getJson('/api/courses')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_missing_course_returns_uniform_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/courses/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_student_cannot_create_course(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/courses', [
                'title' => 'Curso prohibido',
            ])
            ->assertForbidden()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_student_cannot_grade_submission(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['owner_id' => $teacher->id]);
        $activity = Activity::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $submission = ActivitySubmission::factory()->create([
            'activity_id' => $activity->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->patchJson('/api/submissions/'.$submission->id.'/grade', [
                'score' => 80,
            ])
            ->assertForbidden()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_student_cannot_approve_redemption(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['owner_id' => $teacher->id]);
        $reward = Reward::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
        $redemption = Redemption::query()->create([
            'user_id' => $student->id,
            'reward_id' => $reward->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending',
            'redeemed_at' => now(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/redemptions/'.$redemption->id.'/approve')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'rate@pgaev.edu.co',
            'password' => 'password123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'rate@pgaev.edu.co',
                'password' => 'wrong',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'rate@pgaev.edu.co',
            'password' => 'wrong',
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_submit_is_throttled_after_ten_attempts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['owner_id' => $teacher->id]);
        $activity = Activity::factory()->create([
            'course_id' => $course->id,
            'is_published' => true,
            'due_at' => now()->addDay(),
            'max_attempts' => 1,
        ]);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($student, 'sanctum')
                ->postJson('/api/activities/'.$activity->id.'/submit', [
                    'content' => 'intento',
                    'idempotency_key' => (string) Str::uuid(),
                ]);
        }

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/activities/'.$activity->id.'/submit', [
                'content' => 'intento extra',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_response_includes_security_headers_and_request_id(): void
    {
        $response = $this->getJson('/api/health', [
            'X-Request-Id' => 'bench-request-1',
        ]);

        $response->assertOk()
            ->assertHeader('X-Request-Id', 'bench-request-1')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
