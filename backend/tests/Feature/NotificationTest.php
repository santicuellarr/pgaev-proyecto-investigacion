<?php

namespace Tests\Feature;

use App\Events\LevelUpEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserCourseStat;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, course: Course}
     */
    private function createNotificationContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Curso de Notificaciones',
            'invite_code' => 'NOT001',
            'is_active' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

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

        UserCourseStat::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => 0,
            'current_level' => 1,
            'streak_days' => 0,
        ]);

        return compact('student', 'course');
    }

    public function test_level_up_event_creates_in_app_notification(): void
    {
        ['student' => $student, 'course' => $course] = $this->createNotificationContext();

        $service = app(GamificationService::class);
        $service->awardPoints($student->id, $course->id, 60, 'activity_submission');
        $service->awardPoints($student->id, $course->id, 50, 'activity_submission');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => '¡Subiste de nivel!',
            'is_read' => false,
        ]);

        $notification = Notification::where('user_id', $student->id)->first();

        $this->assertStringContainsString('nivel 2', $notification->message);
        $this->assertStringContainsString('Curso de Notificaciones', $notification->message);
    }

    public function test_user_can_list_notifications_with_unread_count(): void
    {
        ['student' => $student] = $this->createNotificationContext();

        Notification::create([
            'user_id' => $student->id,
            'title' => 'Nueva insignia',
            'message' => 'Obtuviste la insignia Primer Envío.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Notification::create([
            'user_id' => $student->id,
            'title' => 'Actividad calificada',
            'message' => 'Tu tarea fue calificada con 90 puntos.',
            'is_read' => true,
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Nueva insignia');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        ['student' => $student] = $this->createNotificationContext();

        $notification = Notification::create([
            'user_id' => $student->id,
            'title' => '¡Subiste de nivel!',
            'message' => 'Felicidades, alcanzaste el nivel 2.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('notification.is_read', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }
}
