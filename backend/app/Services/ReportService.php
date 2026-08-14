<?php

namespace App\Services;

use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserCourseStat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function exportCourseCsv(int $courseId, User $user): StreamedResponse
    {
        $course = Course::findOrFail($courseId);

        $this->ensureUserCanExportCourse($course, $user);

        $filename = sprintf('analytics-curso-%d.csv', $course->id);

        return response()->streamDownload(function () use ($course) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Nombre Estudiante',
                'Email',
                'Puntos Totales',
                'Nivel Actual',
                'Tareas Completadas',
            ]);

            $enrollments = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('status', 'active')
                ->with('user')
                ->orderBy('enrolled_at')
                ->get();

            foreach ($enrollments as $enrollment) {
                $student = $enrollment->user;

                $stats = UserCourseStat::query()
                    ->where('user_id', $student->id)
                    ->where('course_id', $course->id)
                    ->first();

                $completedTasks = ActivitySubmission::query()
                    ->where('user_id', $student->id)
                    ->where('status', 'graded')
                    ->whereHas('activity', function ($query) use ($course) {
                        $query->where('course_id', $course->id);
                    })
                    ->count();

                fputcsv($handle, [
                    $student->name,
                    $student->email,
                    $stats?->total_points ?? 0,
                    $stats?->current_level ?? 1,
                    $completedTasks,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function ensureUserCanExportCourse(Course $course, User $user): void
    {
        if (! in_array($user->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden exportar analíticas del curso.');
        }

        if ($user->role !== 'admin' && $course->owner_id !== $user->id) {
            abort(403, 'No tienes permiso para exportar analíticas de este curso.');
        }
    }
}
