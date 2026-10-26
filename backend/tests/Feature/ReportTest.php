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

        return compact('teacher', 'student', 'course', 'activity');
    }

    /**
     * @return array{teacher: User, student: User, second: User, course: Course, activity: Activity}
     */
    private function createTwoStudentContext(): array
    {
        $context = $this->createReportContext();
        $second = User::factory()->create([
            'role' => 'student',
            'name' => 'Luis Estudiante',
            'email' => 'luis@example.com',
        ]);

        Enrollment::create([
            'user_id' => $second->id,
            'course_id' => $context['course']->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $context['second'] = $second;

        return $context;
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

    public function test_analytics_json_has_expected_structure(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->createReportContext();

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/courses/{$course->id}/analytics")
            ->assertOk()
            ->assertJsonStructure([
                'course' => ['id', 'title'],
                'kpis' => [
                    'enrolled',
                    'average_score',
                    'total_points',
                    'attendance_rate',
                    'forum_participation_rate',
                    'redemptions_by_status' => ['pending', 'approved', 'rejected'],
                ],
                'completion_by_activity' => [
                    ['activity_id', 'title', 'type', 'enrolled', 'unique_submissions', 'completion_rate'],
                ],
                'points_distribution' => [
                    ['bucket', 'count'],
                ],
                'submissions_by_week' => [
                    ['week_start', 'count'],
                ],
                'low_participation',
                'leaderboard_top',
                'attendance_by_session',
                'forums',
            ])
            ->assertJsonPath('kpis.enrolled', 1)
            ->assertJsonPath('kpis.average_score', 95)
            ->assertJsonPath('kpis.total_points', 150)
            ->assertJsonPath('completion_by_activity.0.completion_rate', 100);
    }

    public function test_completion_rate_uses_unique_students(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->createTwoStudentContext();

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/courses/{$course->id}/analytics")
            ->assertOk()
            ->assertJsonPath('kpis.enrolled', 2)
            ->assertJsonPath('completion_by_activity.0.unique_submissions', 1)
            ->assertJsonPath('completion_by_activity.0.completion_rate', 50);
    }

    public function test_csv_types_include_expected_headers(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->createReportContext();

        $headers = [
            'completion' => '"Envíos únicos"',
            'distribution' => 'Rango,Estudiantes',
            'low_participation' => '"Completitud %"',
            'attendance' => '"Asistencia %"',
            'forums' => '"Participación %"',
        ];

        foreach ($headers as $type => $header) {
            $content = $this->actingAs($teacher, 'sanctum')
                ->get("/api/courses/{$course->id}/analytics/export?type={$type}")
                ->assertOk()
                ->streamedContent();

            $this->assertStringContainsString($header, $content, "CSV {$type} debe incluir el encabezado esperado.");
        }
    }

    public function test_outsider_cannot_view_course_analytics(): void
    {
        ['course' => $course] = $this->createReportContext();
        $other = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/courses/{$course->id}/analytics")
            ->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->get("/api/courses/{$course->id}/analytics/export?type=completion")
            ->assertForbidden();
    }
}
