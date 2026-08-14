<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, course: Course}
     */
    private function createReportContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Ana Estudiante',
            'email' => 'ana@example.com',
        ]);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Reportes',
            'invite_code' => 'REP001',
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
            'total_points' => 150,
            'current_level' => 2,
            'streak_days' => 0,
        ]);

        $activity = Activity::create([
            'course_id' => $course->id,
            'title' => 'Tarea analítica',
            'type' => 'tarea',
            'base_points' => 100,
            'max_attempts' => 3,
            'due_at' => now()->addDays(5),
            'is_published' => true,
        ]);

        ActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'content' => ['text' => 'Respuesta calificada'],
            'score' => 95,
            'idempotency_key' => 'report-test-submission-1',
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        return compact('teacher', 'student', 'course');
    }

    public function test_export_course_csv_returns_text_csv_headers(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->createReportContext();

        $response = $this->actingAs($teacher, 'sanctum')
            ->get("/api/courses/{$course->id}/analytics/export");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        $this->assertStringContainsString('Nombre Estudiante', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('Puntos Totales', $content);
        $this->assertStringContainsString('Nivel Actual', $content);
        $this->assertStringContainsString('Tareas Completadas', $content);
        $this->assertStringContainsString('Ana Estudiante', $content);
        $this->assertStringContainsString('ana@example.com', $content);
        $this->assertStringContainsString(',150,2,1', $content);
    }

    public function test_student_cannot_export_course_csv(): void
    {
        ['student' => $student, 'course' => $course] = $this->createReportContext();

        $this->actingAs($student, 'sanctum')
            ->get("/api/courses/{$course->id}/analytics/export")
            ->assertForbidden();
    }
}
