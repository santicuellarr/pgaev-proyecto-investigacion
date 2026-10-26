<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, course: Course}
     */
    private function createProgressContext(int $points, int $level): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de progreso',
            'invite_code' => 'PRG001',
            'is_active' => true,
            'status' => 'published',
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        foreach ([
            [1, 'Novato', 0],
            [2, 'Aprendiz', 100],
            [3, 'Experto', 250],
        ] as [$number, $name, $required]) {
            Level::create([
                'course_id' => $course->id,
                'level_number' => $number,
                'name' => $name,
                'points_required' => $required,
            ]);
        }

        UserCourseStat::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => $points,
            'current_level' => $level,
            'streak_days' => 3,
        ]);

        return compact('student', 'course');
    }

    public function test_progress_reports_next_level_threshold(): void
    {
        ['student' => $student, 'course' => $course] = $this->createProgressContext(150, 2);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/me/progress?course_id={$course->id}")
            ->assertOk()
            ->assertJsonPath('current_level.level_number', 2)
            ->assertJsonPath('current_level.name', 'Aprendiz')
            ->assertJsonPath('current_level.points_required', 100)
            ->assertJsonPath('next_level.level_number', 3)
            ->assertJsonPath('next_level.points_required', 250)
            ->assertJsonPath('points_to_next', 100)
            ->assertJsonPath('total_points', 150)
            ->assertJsonPath('is_max_level', false)
            ->assertJsonPath('rank', 1)
            ->assertJsonPath('streak_days', 3);
    }

    public function test_progress_at_max_level_has_no_next_threshold(): void
    {
        ['student' => $student, 'course' => $course] = $this->createProgressContext(300, 3);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/me/progress?course_id={$course->id}")
            ->assertOk()
            ->assertJsonPath('current_level.level_number', 3)
            ->assertJsonPath('next_level', null)
            ->assertJsonPath('points_to_next', 0)
            ->assertJsonPath('progress_percent', 100)
            ->assertJsonPath('is_max_level', true);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        ['student' => $student] = $this->createProgressContext(0, 1);

        Notification::create([
            'user_id' => $student->id,
            'title' => 'Actividad calificada',
            'message' => 'Nota 90',
            'type' => Notification::TYPE_GRADE,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Notification::create([
            'user_id' => $student->id,
            'title' => 'Nueva insignia',
            'message' => 'Primer Envío',
            'type' => Notification::TYPE_BADGE,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('updated', 2);

        $this->assertEquals(0, Notification::query()->where('user_id', $student->id)->where('is_read', false)->count());
    }
}
