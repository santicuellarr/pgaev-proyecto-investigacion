<?php

namespace App\Services;

use App\Events\LevelUpEvent;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Level;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\UserBadge;
use App\Models\UserCourseStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GamificationService
{
    public function awardPoints(int $userId, int $courseId, int $points, string $sourceType): PointLog
    {
        return DB::transaction(function () use ($userId, $courseId, $points, $sourceType) {
            $stats = UserCourseStat::firstOrCreate(
                [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                ],
                [
                    'total_points' => 0,
                    'current_level' => 1,
                    'streak_days' => 0,
                ],
            );

            $stats = UserCourseStat::query()
                ->whereKey($stats->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = $stats->total_points + $points;

            $stats->update([
                'total_points' => $balanceAfter,
                'last_activity_at' => now(),
            ]);

            $pointLog = PointLog::create([
                'user_id' => $userId,
                'course_id' => $courseId,
                'source_id' => 0,
                'source_type' => $sourceType,
                'delta' => $points,
                'balance_after' => $balanceAfter,
                'created_at' => now(),
            ]);

            $this->checkLevelUp($userId, $courseId);

            return $pointLog;
        });
    }

    /**
     * @return array{redemption: Redemption, was_created: bool}
     */
    public function redeemReward(int $userId, int $rewardId, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($userId, $rewardId, $idempotencyKey) {
            $existingRedemption = Redemption::where('idempotency_key', $idempotencyKey)->first();

            if ($existingRedemption) {
                return [
                    'redemption' => $existingRedemption,
                    'was_created' => false,
                ];
            }

            $reward = Reward::query()
                ->whereKey($rewardId)
                ->lockForUpdate()
                ->firstOrFail();

            $stat = UserCourseStat::query()
                ->where('user_id', $userId)
                ->where('course_id', $reward->course_id)
                ->lockForUpdate()
                ->first();

            if (! $stat) {
                throw ValidationException::withMessages([
                    'points' => ['No tienes puntos registrados en este curso.'],
                ]);
            }

            if ($reward->stock <= 0) {
                throw ValidationException::withMessages([
                    'reward' => ['Esta recompensa no tiene stock disponible.'],
                ]);
            }

            if ($stat->total_points < $reward->cost_points) {
                throw ValidationException::withMessages([
                    'points' => ['No tienes puntos suficientes para canjear esta recompensa.'],
                ]);
            }

            $balanceAfter = $stat->total_points - $reward->cost_points;

            $stat->update([
                'total_points' => $balanceAfter,
                'last_activity_at' => now(),
            ]);

            PointLog::create([
                'user_id' => $userId,
                'course_id' => $reward->course_id,
                'source_id' => $reward->id,
                'source_type' => 'redemption',
                'delta' => -$reward->cost_points,
                'balance_after' => $balanceAfter,
                'created_at' => now(),
            ]);

            $reward->decrement('stock');

            $redemption = Redemption::create([
                'user_id' => $userId,
                'reward_id' => $reward->id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'approved',
                'redeemed_at' => now(),
                'resolved_at' => now(),
            ]);

            return [
                'redemption' => $redemption,
                'was_created' => true,
            ];
        });
    }

    /**
     * @return Collection<int, UserBadge>
     */
    public function checkBadges(int $userId, int $courseId): Collection
    {
        $awardedBadges = collect();

        $submissionCount = ActivitySubmission::query()
            ->where('user_id', $userId)
            ->whereHas('activity', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->count();

        if ($submissionCount < 1) {
            return $awardedBadges;
        }

        $badge = Badge::firstOrCreate(
            [
                'course_id' => $courseId,
                'trigger_type' => 'first_submission',
            ],
            [
                'name' => 'Primer Envío',
                'description' => 'Otorgada al enviar tu primera actividad en el curso.',
                'trigger_config' => ['event' => 'first_submission'],
            ],
        );

        $userBadge = UserBadge::firstOrCreate(
            [
                'user_id' => $userId,
                'badge_id' => $badge->id,
            ],
            [
                'course_id' => $courseId,
                'awarded_at' => now(),
            ],
        );

        if ($userBadge->wasRecentlyCreated) {
            $awardedBadges->push($userBadge->load('badge'));
        }

        return $awardedBadges;
    }

    public function checkLevelUp(int $userId, int $courseId): bool
    {
        $stats = UserCourseStat::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (! $stats) {
            return false;
        }

        $nextLevel = Level::query()
            ->where('course_id', $courseId)
            ->where('points_required', '<=', $stats->total_points)
            ->where('level_number', '>', $stats->current_level)
            ->orderByDesc('level_number')
            ->first();

        if (! $nextLevel) {
            return false;
        }

        $stats->update([
            'current_level' => $nextLevel->level_number,
        ]);

        event(new LevelUpEvent($userId, $courseId, $nextLevel->level_number));

        return true;
    }
}
