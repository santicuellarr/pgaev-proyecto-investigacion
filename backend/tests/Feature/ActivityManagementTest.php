<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, course: Course, activity: Activity}
     */
    private function createContext(bool $published = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de actividades',
            'invite_code' => 'ACT456',
            'is_active' => true,
            'status' => 'published',
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $activity = Activity::create([
            'course_id' => $course->id,
            'title' => 'Borrador de quiz',
            'type' => 'quiz',
            'base_points' => 50,
            'max_attempts' => 2,
            'due_at' => now()->addDays(5),
            'is_published' => $published,
        ]);

        return compact('teacher', 'student', 'course', 'activity');
    }

    public function test_teacher_can_publish_activity_and_student_can_see_it(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createContext(false);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$course->id}/activities")
            ->assertOk()
            ->assertJsonCount(0, 'activities');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/activities/{$activity->id}/publish", [
                'is_published' => true,
            ])
            ->assertOk()
            ->assertJsonPath('activity.is_published', true);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$course->id}/activities")
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.id', $activity->id);
    }

    public function test_teacher_cannot_delete_activity_with_submissions(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'activity' => $activity] = $this->createContext(true);

        ActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'content' => ['text' => 'Envío que bloquea el borrado.'],
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/activities/{$activity->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activity']);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'deleted_at' => null,
        ]);
    }

    public function test_teacher_can_delete_activity_without_submissions(): void
    {
        ['teacher' => $teacher, 'activity' => $activity] = $this->createContext(false);

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/activities/{$activity->id}")
            ->assertOk();

        $this->assertSoftDeleted('activities', [
            'id' => $activity->id,
        ]);
    }
}
