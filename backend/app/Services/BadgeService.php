<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class BadgeService
{
    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * @return Collection<int, UserBadge>
     */
    public function getUserBadgesForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessBadges($course, $user);

        return UserBadge::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();
    }

    /**
     * @return Collection<int, Badge>
     */
    public function listForCourse(Course $course, User $user): Collection
    {
        $this->ensureUserCanAccessBadges($course, $user);

        return Badge::query()
            ->where(function ($query) use ($course) {
                $query->where('course_id', $course->id)->orWhereNull('course_id');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBadge(array $data, Course $course, User $teacher): Badge
    {
        $this->ensureTeacherOwnsCourse($course, $teacher);

        $ruleType = $data['rule_type'];

        return Badge::create([
            'course_id' => $course->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'trigger_type' => $ruleType,
            'trigger_config' => ['rule' => $ruleType, 'value' => $data['rule_value'] ?? null],
            'rule_type' => $ruleType,
            'rule_value' => $data['rule_value'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBadge(Badge $badge, array $data, User $teacher): Badge
    {
        $this->ensureTeacherCanManageBadge($badge, $teacher);

        $ruleType = $data['rule_type'] ?? $badge->rule_type;

        $badge->update([
            'name' => $data['name'] ?? $badge->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $badge->description,
            'icon' => array_key_exists('icon', $data) ? $data['icon'] : $badge->icon,
            'rule_type' => $ruleType,
            'rule_value' => array_key_exists('rule_value', $data) ? $data['rule_value'] : $badge->rule_value,
            'trigger_type' => $ruleType,
        ]);

        return $badge->fresh();
    }

    public function deleteBadge(Badge $badge, User $teacher): void
    {
        $this->ensureTeacherCanManageBadge($badge, $teacher);

        $badge->delete();
    }

    public function awardManually(Badge $badge, User $student, User $teacher): UserBadge
    {
        if ($student->role !== 'student') {
            abort(403, 'Solo se pueden otorgar insignias a estudiantes.');
        }

        $courseId = $badge->course_id;

        if (! $courseId) {
            throw ValidationException::withMessages([
                'badge' => ['Una insignia global debe otorgarse desde un curso.'],
            ]);
        }

        $course = Course::findOrFail($courseId);
        $this->ensureTeacherOwnsCourse($course, $teacher);

        $isEnrolled = Enrollment::query()
            ->where('course_id', $courseId)
            ->where('user_id', $student->id)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'El estudiante no está inscrito en este curso.');
        }

        $awarded = $this->gamificationService->grantBadge($student->id, $courseId, $badge);

        if (! $awarded) {
            $awarded = UserBadge::query()
                ->where('user_id', $student->id)
                ->where('badge_id', $badge->id)
                ->with('badge')
                ->firstOrFail();
        }

        return $awarded;
    }

    private function ensureTeacherCanManageBadge(Badge $badge, User $teacher): void
    {
        if (! $badge->course_id) {
            abort(403, 'No se pueden editar insignias globales desde el curso.');
        }

        $this->ensureTeacherOwnsCourse($badge->course, $teacher);
    }

    private function ensureTeacherOwnsCourse(Course $course, User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar insignias.');
        }

        if ($teacher->role !== 'admin' && $course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para gestionar insignias de este curso.');
        }
    }

    private function ensureUserCanAccessBadges(Course $course, User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'teacher' && $course->owner_id === $user->id) {
            return;
        }

        if ($user->role === 'student') {
            $isEnrolled = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($isEnrolled) {
                return;
            }
        }

        abort(403, 'No tienes acceso a las insignias de este curso.');
    }
}
