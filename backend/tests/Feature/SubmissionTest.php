<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, course: Course, activity: Activity}
     */
    private function createSubmissionContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Actividades',
            'invite_code' => 'ACT123',
            'is_active' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $activity = Activity::create([
            'course_id' => $course->id,
            'title' => 'Tarea 1',
            'type' => 'tarea',
            'base_points' => 100,
            'max_attempts' => 3,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        return compact('teacher', 'student', 'course', 'activity');
    }

    public function test_student_can_submit_activity_successfully(): void
    {
        ['student' => $student, 'activity' => $activity] = $this->createSubmissionContext();

        $idempotencyKey = (string) Str::uuid();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Esta es mi respuesta a la tarea.',
                'idempotency_key' => $idempotencyKey,
            ]);

        $response->assertCreated()
            ->assertJsonPath('submission.status', 'submitted')
            ->assertJsonPath('submission.attempt_number', 1)
            ->assertJsonPath('submission.idempotency_key', $idempotencyKey)
            ->assertJsonPath('submission.content.text', 'Esta es mi respuesta a la tarea.');

        $this->assertDatabaseHas('activity_submissions', [
            'activity_id' => $activity->id,
            'user_id' => $student->id,
            'status' => 'submitted',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function test_student_cannot_submit_activity_after_due_date(): void
    {
        ['student' => $student, 'activity' => $activity] = $this->createSubmissionContext();

        $activity->update([
            'due_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Intento tardío.',
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['activity']);

        $this->assertDatabaseCount('activity_submissions', 0);
    }

    public function test_duplicate_submission_with_same_idempotency_key_is_idempotent(): void
    {
        ['student' => $student, 'activity' => $activity] = $this->createSubmissionContext();

        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'content' => 'Respuesta idempotente.',
            'idempotency_key' => $idempotencyKey,
        ];

        $firstResponse = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", $payload);

        $firstResponse->assertCreated()
            ->assertJsonPath('submission.idempotency_key', $idempotencyKey);

        $secondResponse = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", $payload);

        $secondResponse->assertOk()
            ->assertJsonPath('submission.id', $firstResponse->json('submission.id'))
            ->assertJsonPath('submission.idempotency_key', $idempotencyKey);

        $this->assertDatabaseCount('activity_submissions', 1);
        $this->assertDatabaseHas('activity_submissions', [
            'activity_id' => $activity->id,
            'user_id' => $student->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'submitted',
        ]);
    }

    public function test_activity_show_includes_student_submission_with_ai_feedback(): void
    {
        ['student' => $student, 'activity' => $activity] = $this->createSubmissionContext();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Mi respuesta para retroalimentación.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/activities/{$activity->id}")
            ->assertOk()
            ->assertJsonPath('my_submission.content.text', 'Mi respuesta para retroalimentación.')
            ->assertJsonPath('my_submission.ai_feedback', 'Retroalimentación pendiente de revisión docente.');
    }
}
