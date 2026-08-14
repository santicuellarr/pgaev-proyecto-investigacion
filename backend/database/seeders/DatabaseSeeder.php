<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Notification;
use App\Models\PointLog;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $teacher = User::create([
            'name' => 'Prof. Carlos Mendoza',
            'email' => 'docente@pgaev.edu.co',
            'password' => $password,
            'role' => 'teacher',
            'email_verified_at' => now(),
            'bio' => 'Docente de Arquitectura de Software con enfoque en gamificación educativa.',
        ]);

        $students = collect([
            ['name' => 'Ana García', 'email' => 'estudiante1@pgaev.edu.co'],
            ['name' => 'Luis Ramírez', 'email' => 'estudiante2@pgaev.edu.co'],
            ['name' => 'María Torres', 'email' => 'estudiante3@pgaev.edu.co'],
        ])->map(fn (array $student) => User::create([
            'name' => $student['name'],
            'email' => $student['email'],
            'password' => $password,
            'role' => 'student',
            'email_verified_at' => now(),
        ]));

        $course = Course::create([
            'owner_id' => $teacher->id,
            'title' => 'Arquitectura de Software Virtual',
            'description' => 'Curso demostrativo de la PGAEV para la sustentación final. Incluye actividades gamificadas, recompensas canjeables y analíticas en tiempo real.',
            'invite_code' => 'PGAEV1',
            'is_active' => true,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->addMonths(4),
        ]);

        foreach ($students as $index => $student) {
            Enrollment::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'status' => 'active',
                'enrolled_at' => now()->subDays(7 - $index),
            ]);
        }

        $levels = [
            ['level_number' => 1, 'name' => 'Novato', 'points_required' => 0],
            ['level_number' => 2, 'name' => 'Aprendiz', 'points_required' => 100],
            ['level_number' => 3, 'name' => 'Experto', 'points_required' => 250],
        ];

        foreach ($levels as $level) {
            Level::create([
                'course_id' => $course->id,
                'level_number' => $level['level_number'],
                'name' => $level['name'],
                'points_required' => $level['points_required'],
            ]);
        }

        $activities = [
            [
                'title' => 'Quiz: Patrones de Diseño',
                'type' => 'quiz',
                'base_points' => 50,
                'max_attempts' => 2,
                'due_at' => now()->addDays(10),
            ],
            [
                'title' => 'Tarea: Diagrama de Casos de Uso',
                'type' => 'tarea',
                'base_points' => 75,
                'max_attempts' => 3,
                'due_at' => now()->addDays(14),
            ],
            [
                'title' => 'Proyecto: Prototipo Funcional',
                'type' => 'proyecto',
                'base_points' => 100,
                'max_attempts' => 1,
                'due_at' => now()->addDays(21),
            ],
        ];

        $createdActivities = collect($activities)->map(fn (array $activity) => Activity::create([
            'course_id' => $course->id,
            'title' => $activity['title'],
            'type' => $activity['type'],
            'base_points' => $activity['base_points'],
            'max_attempts' => $activity['max_attempts'],
            'due_at' => $activity['due_at'],
            'is_published' => true,
        ]));

        Reward::create([
            'course_id' => $course->id,
            'name' => 'Punto Extra en Parcial',
            'cost_points' => 100,
            'stock' => 15,
            'needs_approval' => false,
            'available_from' => now(),
            'available_to' => now()->addMonths(3),
        ]);

        Reward::create([
            'course_id' => $course->id,
            'name' => 'Asesoría Individual',
            'cost_points' => 50,
            'stock' => 10,
            'needs_approval' => true,
            'available_from' => now(),
            'available_to' => now()->addMonths(3),
        ]);

        $studentStats = [
            ['total_points' => 125, 'current_level' => 2, 'completed_tasks' => 2],
            ['total_points' => 75, 'current_level' => 1, 'completed_tasks' => 1],
            ['total_points' => 40, 'current_level' => 1, 'completed_tasks' => 1],
        ];

        foreach ($students as $index => $student) {
            $stats = $studentStats[$index];

            UserCourseStat::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'total_points' => $stats['total_points'],
                'current_level' => $stats['current_level'],
                'streak_days' => $index + 1,
                'last_activity_at' => now()->subDays($index),
            ]);

            PointLog::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'source_id' => $createdActivities[0]->id,
                'source_type' => 'activity_submission',
                'delta' => min(50, $stats['total_points']),
                'balance_after' => min(50, $stats['total_points']),
                'created_at' => now()->subDays(3),
            ]);

            if ($stats['total_points'] > 50) {
                PointLog::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'source_id' => $createdActivities[1]->id,
                    'source_type' => 'activity_submission',
                    'delta' => $stats['total_points'] - 50,
                    'balance_after' => $stats['total_points'],
                    'created_at' => now()->subDays(1),
                ]);
            }

            ActivitySubmission::create([
                'activity_id' => $createdActivities[0]->id,
                'user_id' => $student->id,
                'attempt_number' => 1,
                'status' => 'graded',
                'content' => ['text' => 'Respuesta demo al quiz de patrones de diseño.'],
                'score' => 90,
                'idempotency_key' => "seed-{$student->id}-activity-1",
                'submitted_at' => now()->subDays(3),
                'graded_at' => now()->subDays(2),
                'ai_feedback' => 'Buen análisis de los patrones GoF. Profundiza en el patrón Observer para la próxima entrega.',
            ]);

            if ($stats['completed_tasks'] >= 2) {
                ActivitySubmission::create([
                    'activity_id' => $createdActivities[1]->id,
                    'user_id' => $student->id,
                    'attempt_number' => 1,
                    'status' => 'graded',
                    'content' => ['text' => 'Diagrama de casos de uso entregado para revisión docente.'],
                    'score' => 85,
                    'idempotency_key' => "seed-{$student->id}-activity-2",
                    'submitted_at' => now()->subDays(1),
                    'graded_at' => now()->subHours(12),
                    'ai_feedback' => 'El diagrama cubre los actores principales. Considera agregar relaciones include/extend donde aplique.',
                ]);
            } elseif ($stats['completed_tasks'] === 1 && $index === 2) {
                ActivitySubmission::create([
                    'activity_id' => $createdActivities[1]->id,
                    'user_id' => $student->id,
                    'attempt_number' => 1,
                    'status' => 'submitted',
                    'content' => ['text' => 'Borrador del diagrama de casos de uso en revisión.'],
                    'score' => null,
                    'idempotency_key' => "seed-{$student->id}-activity-2",
                    'submitted_at' => now()->subHours(6),
                    'ai_feedback' => 'Retroalimentación pendiente de revisión docente.',
                ]);
            }
        }

        Notification::create([
            'user_id' => $students[0]->id,
            'title' => '¡Subiste de nivel!',
            'message' => 'Felicidades, alcanzaste el nivel 2 en Arquitectura de Software Virtual.',
            'is_read' => false,
            'created_at' => now()->subDay(),
        ]);

        Notification::create([
            'user_id' => $students[1]->id,
            'title' => 'Actividad calificada',
            'message' => 'Tu Quiz: Patrones de Diseño fue calificado con 90 puntos.',
            'is_read' => true,
            'created_at' => now()->subDays(2),
        ]);
    }
}
