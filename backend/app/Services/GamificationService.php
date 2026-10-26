<?php

namespace App\Services;

use App\Events\LevelUpEvent;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Level;
use App\Models\Notification;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\UserBadge;
use App\Models\UserCourseStat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GamificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function awardPoints(int $userId, int $courseId, int $points, string $sourceType, ?int $sourceId = null): PointLog
    {
        return DB::transaction(function () use ($userId, $courseId, $points, $sourceType, $sourceId) {
            $stats = $this->lockCourseStats($userId, $courseId);

            $balanceAfter = $stats->total_points + $points;

            $stats->update([
                'total_points' => $balanceAfter,
                'last_activity_at' => now(),
            ]);

            $pointLog = PointLog::create([
                'user_id' => $userId,
                'course_id' => $courseId,
                'source_id' => $sourceId ?? 0,
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

            $needsApproval = (bool) $reward->needs_approval;

            $redemption = Redemption::create([
                'user_id' => $userId,
                'reward_id' => $reward->id,
                'idempotency_key' => $idempotencyKey,
                'status' => $needsApproval ? 'pending' : 'approved',
                'redeemed_at' => now(),
                'resolved_at' => $needsApproval ? null : now(),
            ]);

            $this->notificationService->createNotification(
                $userId,
                $needsApproval ? 'Canje pendiente' : 'Canje realizado',
                sprintf(
                    '%s la recompensa %s.',
                    $needsApproval ? 'Solicitaste' : 'Canjeaste',
                    $reward->name,
                ),
                Notification::TYPE_REDEMPTION,
            );

            Log::info('reward.redeemed', [
                'redemption_id' => $redemption->id,
                'user_id' => $userId,
                'reward_id' => $reward->id,
                'status' => $redemption->status,
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

        $this->ensureFirstSubmissionBadge($courseId);

        if ($submissionCount < 1) {
            return $awardedBadges;
        }

        $stats = UserCourseStat::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        $badges = Badge::query()
            ->where(function ($query) use ($courseId) {
                $query->where('course_id', $courseId)->orWhereNull('course_id');
            })
            ->get();

        foreach ($badges as $badge) {
            $ruleType = $badge->rule_type ?: $badge->trigger_type;

            if ($ruleType === Badge::RULE_MANUAL) {
                continue;
            }

            if (! $this->badgeRuleMatches($ruleType, (int) ($badge->rule_value ?? 1), $submissionCount, $stats)) {
                continue;
            }

            $awarded = $this->grantBadge($userId, $courseId, $badge);

            if ($awarded) {
                $awardedBadges->push($awarded);
            }
        }

        return $awardedBadges;
    }

    public function grantBadge(int $userId, int $courseId, Badge $badge): ?UserBadge
    {
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

        if (! $userBadge->wasRecentlyCreated) {
            return null;
        }

        $this->notificationService->createNotification(
            $userId,
            'Nueva insignia',
            sprintf('Obtuviste la insignia %s.', $badge->name),
            Notification::TYPE_BADGE,
        );

        return $userBadge->load('badge');
    }

    private function ensureFirstSubmissionBadge(int $courseId): Badge
    {
        return Badge::firstOrCreate(
            [
                'course_id' => $courseId,
                'trigger_type' => Badge::RULE_FIRST_SUBMISSION,
            ],
            [
                'name' => 'Primer Envío',
                'description' => 'Otorgada al enviar tu primera actividad en el curso.',
                'icon' => '🥇',
                'trigger_config' => ['event' => 'first_submission'],
                'rule_type' => Badge::RULE_FIRST_SUBMISSION,
                'rule_value' => 1,
            ],
        );
    }

    private function badgeRuleMatches(string $ruleType, int $ruleValue, int $submissionCount, ?UserCourseStat $stats): bool
    {
        $points = (int) ($stats?->total_points ?? 0);
        $level = (int) ($stats?->current_level ?? 1);

        return match ($ruleType) {
            Badge::RULE_FIRST_SUBMISSION => $submissionCount >= 1,
            Badge::RULE_SUBMISSIONS_COUNT => $submissionCount >= $ruleValue,
            Badge::RULE_POINTS_TOTAL => $points >= $ruleValue,
            Badge::RULE_LEVEL_REACHED => $level >= $ruleValue,
            default => false,
        };
    }

    private function lockCourseStats(int $userId, int $courseId): UserCourseStat
    {
        try {
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
        } catch (QueryException) {
            $stats = UserCourseStat::query()
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->firstOrFail();
        }

        return UserCourseStat::query()
            ->whereKey($stats->id)
            ->lockForUpdate()
            ->firstOrFail();
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

        $this->checkBadges($userId, $courseId);

        return true;
    }
}
