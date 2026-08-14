<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_course_with_generated_invite_code(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $response = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/courses', [
                'title' => 'Introducción a la Gamificación',
                'description' => 'Curso base para estudiantes nuevos.',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'course' => [
                    'id',
                    'title',
                    'description',
                    'invite_code',
                    'owner_id',
                    'is_active',
                ],
            ])
            ->assertJsonPath('course.owner_id', $teacher->id)
            ->assertJsonPath('course.title', 'Introducción a la Gamificación');

        $inviteCode = $response->json('course.invite_code');

        $this->assertIsString($inviteCode);
        $this->assertSame(6, strlen($inviteCode));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $inviteCode);

        $this->assertDatabaseHas('courses', [
            'owner_id' => $teacher->id,
            'title' => 'Introducción a la Gamificación',
            'invite_code' => $inviteCode,
        ]);
    }

    public function test_student_can_enroll_with_valid_invite_code(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Prueba',
            'invite_code' => 'K8P2W9',
            'is_active' => true,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/courses/enroll', [
                'invite_code' => 'K8P2W9',
            ]);

        $response->assertCreated()
            ->assertJsonPath('course.id', $course->id)
            ->assertJsonPath('enrollment.status', 'active')
            ->assertJsonPath('enrollment.user_id', $student->id);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_student_cannot_enroll_twice_in_same_course(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso Duplicado',
            'invite_code' => 'AB12CD',
            'is_active' => true,
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/courses/enroll', [
                'invite_code' => 'AB12CD',
            ])
            ->assertCreated();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/courses/enroll', [
                'invite_code' => 'AB12CD',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Ya estás inscrito en este curso.');

        $this->assertDatabaseCount('enrollments', 1);
    }
}
