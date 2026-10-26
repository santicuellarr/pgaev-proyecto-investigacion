<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\User;
use App\Models\UserCourseStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RedemptionService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @return Collection<int, Redemption>
     */
    public function pendingForTeacher(User $teacher): Collection
    {
        $this->ensureTeacher($teacher);

        return Redemption::query()
            ->with(['user:id,name,email', 'reward.course'])
            ->where('status', 'pending')
            ->whereHas('reward.course', function ($query) use ($teacher) {
                if ($teacher->role !== 'admin') {
                    $query->where('owner_id', $teacher->id);
                }
            })
            ->latest('redeemed_at')
            ->get();
    }

    public function pendingCountForTeacher(User $teacher): int
    {
        return $this->pendingForTeacher($teacher)->count();
    }

    public function approve(Redemption $redemption, User $teacher): Redemption
    {
        $this->ensureTeacherOwnsRedemption($redemption, $teacher);

        if ($redemption->status !== 'pending') {
            throw ValidationException::withMessages([
                'redemption' => ['Este canje ya fue resuelto.'],
            ]);
        }

        $redemption->update([
            'status' => 'approved',
            'resolved_at' => now(),
            'rejection_reason' => null,
        ]);

        $updated = $redemption->fresh(['user', 'reward']);

        $this->notificationService->createNotification(
            $updated->user_id,
            'Canje aprobado',
            sprintf('Tu canje de %s fue aprobado.', $updated->reward?->name ?? 'la recompensa'),
            Notification::TYPE_REDEMPTION,
        );

        Log::info('redemption.approved', [
            'redemption_id' => $updated->id,
            'user_id' => $updated->user_id,
            'reward_id' => $updated->reward_id,
        ]);

        return $updated;
    }

    public function reject(Redemption $redemption, User $teacher, string $reason): Redemption
    {
        $this->ensureTeacherOwnsRedemption($redemption, $teacher);

        if ($redemption->status !== 'pending') {
            throw ValidationException::withMessages([
                'redemption' => ['Este canje ya fue resuelto.'],
            ]);
        }

        DB::transaction(function () use ($redemption, $reason) {
            $redemption->loadMissing('reward');
            $reward = $redemption->reward;

            $stat = UserCourseStat::query()
                ->where('user_id', $redemption->user_id)
                ->where('course_id', $reward->course_id)
                ->lockForUpdate()
                ->first();

            if ($stat) {
                $balanceAfter = $stat->total_points + $reward->cost_points;
                $stat->update(['total_points' => $balanceAfter]);

                PointLog::create([
                    'user_id' => $redemption->user_id,
                    'course_id' => $reward->course_id,
                    'source_id' => $reward->id,
                    'source_type' => 'redemption_rejected',
                    'delta' => $reward->cost_points,
                    'balance_after' => $balanceAfter,
                    'created_at' => now(),
                ]);
            }

            $reward->increment('stock');

            $redemption->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'resolved_at' => now(),
            ]);
        });

        $updated = $redemption->fresh(['user', 'reward']);

        $this->notificationService->createNotification(
            $updated->user_id,
            'Canje rechazado',
            sprintf('Tu canje de %s fue rechazado.', $updated->reward?->name ?? 'la recompensa'),
            Notification::TYPE_REDEMPTION,
        );

        return $updated;
    }

    private function ensureTeacher(User $teacher): void
    {
        if (! in_array($teacher->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden gestionar canjes.');
        }
    }

    private function ensureTeacherOwnsRedemption(Redemption $redemption, User $teacher): void
    {
        $this->ensureTeacher($teacher);
        $redemption->loadMissing('reward.course');

        if ($teacher->role !== 'admin' && $redemption->reward->course->owner_id !== $teacher->id) {
            abort(403, 'No tienes permiso para resolver este canje.');
        }
    }
}
