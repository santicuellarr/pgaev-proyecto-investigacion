<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointLog;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAndForumTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{teacher: User, student: User, outsider: User, course: Course, session: ClassSession, forum: Activity}
     */
    private function createContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso sincrónico',
            'invite_code' => 'SYNC01',
            'is_active' => true,
            'status' => 'published',
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
            'total_points' => 0,
            'current_level' => 1,
        ]);

        $session = ClassSession::create([
            'course_id' => $course->id,
            'title' => 'Encuentro 1',
            'starts_at' => now()->addDay(),
            'duration_min' => 60,
            'meeting_url' => null,
        ]);

        $forum = Activity::create([
            'course_id' => $course->id,
            'title' => 'Foro de discusión',
            'type' => 'foro',
            'base_points' => 12,
            'max_attempts' => 1,
            'is_published' => true,
        ]);

        return compact('teacher', 'student', 'outsider', 'course', 'session', 'forum');
    }

    public function test_attendance_awards_points_once_without_duplicating(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'session' => $session] = $this->createContext();

        $payload = [
            'attendances' => [
                ['user_id' => $student->id, 'status' => 'present'],
            ],
        ];

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/sessions/{$session->id}/attendance", $payload)
            ->assertOk()
            ->assertJsonPath('points_awarded.0.user_id', $student->id)
            ->assertJsonPath('points_awarded.0.points', 10);

        $this->assertDatabaseCount('point_logs', 1);
        $this->assertDatabaseHas('point_logs', [
            'user_id' => $student->id,
            'source_type' => 'attendance',
            'source_id' => $session->id,
            'delta' => 10,
        ]);
        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'total_points' => 10,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/sessions/{$session->id}/attendance", $payload)
            ->assertOk()
            ->assertJsonPath('points_awarded', []);

        $this->assertEquals(1, PointLog::query()->where('source_type', 'attendance')->count());
        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'total_points' => 10,
        ]);
    }

    public function test_forum_is_only_for_enrolled_students(): void
    {
        ['student' => $student, 'outsider' => $outsider, 'forum' => $forum] = $this->createContext();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/activities/{$forum->id}/posts")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/activities/{$forum->id}/posts", [
                'body' => 'Hola desde fuera.',
            ])
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/activities/{$forum->id}/posts")
            ->assertOk()
            ->assertJsonPath('posts', []);
    }

    public function test_forum_points_are_awarded_only_once_per_activity(): void
    {
        ['student' => $student, 'forum' => $forum] = $this->createContext();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$forum->id}/posts", [
                'body' => 'Mi primer aporte.',
            ])
            ->assertCreated()
            ->assertJsonPath('points_awarded', 12);

        $this->assertDatabaseHas('point_logs', [
            'user_id' => $student->id,
            'source_type' => 'forum_participation',
            'source_id' => $forum->id,
            'delta' => 12,
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/activities/{$forum->id}/posts", [
                'body' => 'Segundo comentario.',
            ])
            ->assertCreated()
            ->assertJsonPath('points_awarded', 0);

        $this->assertEquals(1, PointLog::query()->where('source_type', 'forum_participation')->count());
        $this->assertDatabaseHas('user_course_stats', [
            'user_id' => $student->id,
            'total_points' => 12,
        ]);
        $this->assertDatabaseCount('forum_posts', 2);
    }
}
