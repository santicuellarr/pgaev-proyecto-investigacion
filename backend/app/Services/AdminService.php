<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminService
{
    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function listUsers(?string $role, ?string $search, int $page): LengthAwarePaginator
    {
        $query = User::query()->orderBy('name');

        if ($role) {
            $query->where('role', $role);
        }

        if ($search) {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($inner) use ($term) {
                $inner->whereRaw('LOWER(name) like ?', [$term])
                    ->orWhereRaw('LOWER(email) like ?', [$term]);
            });
        }

        return $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    public function updateRole(User $admin, User $user, string $role): User
    {
        if ($admin->id === $user->id && $role !== User::ROLE_ADMIN) {
            abort(403, 'No puedes quitarte tu propio rol de administrador.');
        }

        $user->update(['role' => $role]);

        return $user->fresh();
    }

    public function updateStatus(User $admin, User $user, string $status): User
    {
        $isActive = $status === User::STATUS_ACTIVE;

        if ($admin->id === $user->id && ! $isActive) {
            abort(403, 'No puedes suspender tu propia cuenta.');
        }

        $user->update(['is_active' => $isActive]);

        if (! $isActive) {
            $user->tokens()->delete();
        }

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $roleCounts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'users_total' => User::query()->count(),
            'users_active' => User::query()->where('is_active', true)->count(),
            'users_suspended' => User::query()->where('is_active', false)->count(),
            'users_by_role' => [
                'student' => (int) ($roleCounts[User::ROLE_STUDENT] ?? 0),
                'teacher' => (int) ($roleCounts[User::ROLE_TEACHER] ?? 0),
                'admin' => (int) ($roleCounts[User::ROLE_ADMIN] ?? 0),
            ],
            'courses_total' => Course::query()->count(),
            'enrollments_active' => Enrollment::query()->where('status', 'active')->count(),
            'activities_published' => Activity::query()->where('is_published', true)->count(),
            'submissions_total' => ActivitySubmission::query()->count(),
            'redemptions_pending' => Redemption::query()->where('status', 'pending')->count(),
        ];
    }
}
