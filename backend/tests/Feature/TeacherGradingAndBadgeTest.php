<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherGradingAndBadgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, otherTeacher: User, student: User, course: Course, activity: Activity}
     */
    private function createContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso docente',
            'invite_code' => 'TCH001',
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
            'title' => 'Quiz evaluable',
            'type' => 'quiz',
            'base_points' => 50,
            'max_attempts' => 5,
            'is_published' => true,
        ]);

        return compact('teacher', 'otherTeacher', 'student', 'course', 'activity');
    }

    public function test_teacher_can_list_activity_submissions_and_foreign_teacher_is_forbidden(): void
    {
        ['teacher' => $teacher, 'otherTeacher' => $otherTeacher, 'student' => $student, 'activity' => $activity] = $this->createContext();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Respuesta para listar.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/activities/{$activity->id}/submissions")
            ->assertOk()
            ->assertJsonCount(1, 'submissions')
            ->assertJsonPath('submissions.0.user_id', $student->id)
            ->assertJsonPath('submissions.0.status', 'submitted');

        $this->actingAs($otherTeacher, 'sanctum')
            ->getJson("/api/activities/{$activity->id}/submissions")
            ->assertForbidden();
    }

    public function test_submissions_count_rule_awards_badge(): void
    {
        ['student' => $student, 'course' => $course, 'activity' => $activity] = $this->createContext();

        Badge::create([
            'course_id' => $course->id,
            'name' => 'Constante',
            'description' => 'Dos envíos',
            'icon' => '📚',
            'trigger_type' => Badge::RULE_SUBMISSIONS_COUNT,
            'rule_type' => Badge::RULE_SUBMISSIONS_COUNT,
            'rule_value' => 2,
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Primer envío.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->assertFalse(
            UserBadge::query()
                ->where('user_id', $student->id)
                ->whereHas('badge', fn ($query) => $query->where('name', 'Constante'))
                ->exists(),
        );

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/submit", [
                'content' => 'Segundo envío.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $student->id,
        ]);

        $this->assertTrue(
            UserBadge::query()
                ->where('user_id', $student->id)
                ->whereHas('badge', fn ($query) => $query->where('name', 'Constante'))
                ->exists(),
        );
    }

    public function test_teacher_can_award_manual_badge_and_student_is_notified(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course] = $this->createContext();

        $badge = Badge::create([
            'course_id' => $course->id,
            'name' => 'Mención especial',
            'description' => 'Otorgada a mano',
            'icon' => '⭐',
            'trigger_type' => Badge::RULE_MANUAL,
            'rule_type' => Badge::RULE_MANUAL,
            'rule_value' => null,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/badges/{$badge->id}/award/{$student->id}")
            ->assertCreated()
            ->assertJsonPath('user_badge.badge.name', 'Mención especial');

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $student->id,
            'badge_id' => $badge->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Nueva insignia',
            'message' => 'Obtuviste la insignia Mención especial.',
        ]);
    }
}
