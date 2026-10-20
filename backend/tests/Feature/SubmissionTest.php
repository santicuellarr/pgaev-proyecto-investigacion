<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\User;
use App\Services\SubmissionService;
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

    public function test_grading_awards_points_and_notifies(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createSubmissionContext();

        $activity->update(['base_points' => 50]);

        $submissionId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Respuesta para calificar.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->json('submission.id');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/submissions/{$submissionId}/grade", [
                'score' => 80,
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', 'graded');

        $this->assertDatabaseHas('point_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'source_id' => $submissionId,
            'source_type' => 'submission_graded',
            'delta' => 40,
        ]);

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 40,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Actividad calificada',
            'message' => 'Tu actividad Tarea 1 fue calificada (40 pts)',
        ]);
    }

    public function test_regrade_awards_only_delta(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createSubmissionContext();

        $activity->update(['base_points' => 50]);

        $submissionId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Respuesta para recalificar.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->json('submission.id');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/submissions/{$submissionId}/grade", ['score' => 80])
            ->assertOk();

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/submissions/{$submissionId}/grade", ['score' => 100])
            ->assertOk();

        $this->assertDatabaseHas('point_logs', [
            'source_id' => $submissionId,
            'source_type' => 'submission_graded',
            'delta' => 40,
        ]);

        $this->assertDatabaseHas('point_logs', [
            'source_id' => $submissionId,
            'source_type' => 'submission_graded',
            'delta' => 10,
        ]);

        $this->assertDatabaseCount('point_logs', 2);

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 50,
        ]);
    }

    public function test_grade_triggers_level_up(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createSubmissionContext();

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

        $submissionId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Respuesta para subir de nivel.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->json('submission.id');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/submissions/{$submissionId}/grade", ['score' => 100])
            ->assertOk();

        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 100,
            'current_level' => 2,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => '¡Subiste de nivel!',
        ]);
    }

    public function test_idempotency_is_scoped_per_user(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createSubmissionContext();

        $otherStudent = User::factory()->create(['role' => 'student']);

        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $sharedKey = (string) Str::uuid();

        $firstId = $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Envío del primer estudiante.',
                'idempotency_key' => $sharedKey,
            ])
            ->assertCreated()
            ->json('submission.id');

        $secondId = $this->actingAs($otherStudent, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Envío del segundo estudiante.',
                'idempotency_key' => $sharedKey,
            ])
            ->assertCreated()
            ->json('submission.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertDatabaseCount('activity_submissions', 2);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Reintento del primer estudiante.',
                'idempotency_key' => $sharedKey,
            ])
            ->assertOk()
            ->assertJsonPath('submission.id', $firstId);

        $this->assertDatabaseCount('activity_submissions', 2);
    }

    public function test_submission_rolls_back_on_failure(): void
    {
        ['student' => $student, 'activity' => $activity] = $this->createSubmissionContext();

        ActivitySubmission::creating(function () {
            throw new \RuntimeException('forced failure');
        });

        try {
            app(SubmissionService::class)->submitActivity(
                $activity->id,
                $student->id,
                'Este envío debe revertirse.',
                (string) Str::uuid(),
            );

            $this->fail('Se esperaba que el envío fallara y revirtiera la transacción.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced failure', $exception->getMessage());
        } finally {
            ActivitySubmission::flushEventListeners();
        }

        $this->assertDatabaseCount('activity_submissions', 0);
    }
}
