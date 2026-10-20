<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, otherStudent: User, course: Course, activity: Activity}
     */
    private function createHistoryContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Historial',
            'invite_code' => 'HST001',
            'is_active' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $activity = Activity::create([
            'course_id' => $course->id,
            'title' => 'Tarea de historial',
            'type' => 'tarea',
            'base_points' => 50,
            'max_attempts' => 3,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        return compact('teacher', 'student', 'otherStudent', 'course', 'activity');
    }

    public function test_student_history_can_be_filtered_by_type(): void
    {
        ['student' => $student, 'course' => $course, 'activity' => $activity] = $this->createHistoryContext();

        $this->seedStudentActions($student, $course, $activity);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/me/history?type=badge&course_id='.$course->id);

        $response->assertOk()
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('history.0.type', 'badge')
            ->assertJsonPath('history.0.title', 'Insignia: Primer Envío');
    }

    public function test_student_history_can_be_filtered_by_date(): void
    {
        ['student' => $student, 'course' => $course] = $this->createHistoryContext();

        PointLog::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'source_id' => 1,
            'source_type' => 'activity_submission',
            'delta' => 10,
            'balance_after' => 10,
            'created_at' => now()->subDays(10),
        ]);

        PointLog::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'source_id' => 2,
            'source_type' => 'activity_submission',
            'delta' => 20,
            'balance_after' => 30,
            'created_at' => now(),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/me/history?course_id={$course->id}&type=point_log&from={$from}&to={$to}");

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('history.0.delta', 20);
    }

    public function test_student_history_is_paginated_twenty_per_page(): void
    {
        ['student' => $student, 'course' => $course] = $this->createHistoryContext();

        for ($index = 1; $index <= 21; $index++) {
            PointLog::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'source_id' => $index,
                'source_type' => 'activity_submission',
                'delta' => $index,
                'balance_after' => $index,
                'created_at' => now()->subMinutes(21 - $index),
            ]);
        }

        $firstPage = $this->actingAs($student, 'sanctum')
            ->getJson("/api/me/history?course_id={$course->id}&type=point_log&page=1");

        $firstPage->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 21)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(20, 'history');

        $secondPage = $this->actingAs($student, 'sanctum')
            ->getJson("/api/me/history?course_id={$course->id}&type=point_log&page=2");

        $secondPage->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(1, 'history');
    }

    public function test_student_cannot_view_another_student_history(): void
    {
        ['student' => $student, 'otherStudent' => $otherStudent, 'course' => $course] = $this->createHistoryContext();

        $this->actingAs($otherStudent, 'sanctum')
            ->getJson("/api/courses/{$course->id}/students/{$student->id}/history")
            ->assertForbidden();
    }

    public function test_teacher_can_view_student_history(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'activity' => $activity] = $this->createHistoryContext();

        $this->seedStudentActions($student, $course, $activity);

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/courses/{$course->id}/students/{$student->id}/history")
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonCount(4, 'history');
    }

    private function seedStudentActions(User $student, Course $course, Activity $activity): void
    {
        ActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'content' => ['text' => 'Respuesta de historial.'],
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now()->subHour(),
        ]);

        PointLog::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'source_id' => 99,
            'source_type' => 'submission_graded',
            'delta' => 40,
            'balance_after' => 40,
            'created_at' => now()->subMinutes(30),
        ]);

        $badge = Badge::create([
            'course_id' => $course->id,
            'name' => 'Primer Envío',
            'description' => 'Primera entrega',
            'trigger_type' => 'first_submission',
            'trigger_config' => ['event' => 'first_submission'],
        ]);

        UserBadge::create([
            'user_id' => $student->id,
            'badge_id' => $badge->id,
            'course_id' => $course->id,
            'awarded_at' => now()->subMinutes(20),
        ]);

        $reward = Reward::create([
            'course_id' => $course->id,
            'name' => 'Punto extra',
            'cost_points' => 10,
            'stock' => 5,
            'needs_approval' => false,
        ]);

        Redemption::create([
            'user_id' => $student->id,
            'reward_id' => $reward->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'approved',
            'redeemed_at' => now()->subMinutes(10),
        ]);
    }
}
