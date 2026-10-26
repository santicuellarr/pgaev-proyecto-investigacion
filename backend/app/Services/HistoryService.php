<?php

namespace App\Services;

use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\User;
use App\Models\UserBadge;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class HistoryService
{
    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForUser(
        User $viewer,
        User $subject,
        ?Course $course,
        ?string $type,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        int $page,
        string $path,
        array $query,
    ): LengthAwarePaginator {
        $this->ensureViewerCanAccess($viewer, $subject, $course);

        $items = $this->collectEntries($subject, $course, $type, $from, $to)
            ->sortByDesc(fn (array $entry) => $entry['occurred_at'].'-'.$entry['type'].'-'.($entry['meta']['id'] ?? 0))
            ->values();

        $total = $items->count();
        $pageItems = $items
            ->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)
            ->values();

        return new Paginator($pageItems, $total, self::PER_PAGE, $page, [
            'path' => $path,
            'query' => $query,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectEntries(
        User $subject,
        ?Course $course,
        ?string $type,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): Collection {
        $entries = collect();

        if ($type === null || $type === 'point_log') {
            $entries = $entries->concat($this->pointLogEntries($subject, $course, $from, $to));
        }

        if ($type === null || $type === 'submission') {
            $entries = $entries->concat($this->submissionEntries($subject, $course, $from, $to));
        }

        if ($type === null || $type === 'badge') {
            $entries = $entries->concat($this->badgeEntries($subject, $course, $from, $to));
        }

        if ($type === null || $type === 'redemption') {
            $entries = $entries->concat($this->redemptionEntries($subject, $course, $from, $to));
        }

        return $entries;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pointLogEntries(User $subject, ?Course $course, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $query = PointLog::query()->where('user_id', $subject->id);

        if ($course) {
            $query->where('course_id', $course->id);
        }

        $this->constrainDate($query, 'created_at', $from, $to);

        return $query->get()->map(fn (PointLog $log) => [
            'type' => 'point_log',
            'title' => $this->pointLogTitle($log->source_type),
            'delta' => $log->delta,
            'balance_after' => $log->balance_after,
            'occurred_at' => $log->created_at?->toIso8601String(),
            'meta' => [
                'id' => $log->id,
                'course_id' => $log->course_id,
                'source_id' => $log->source_id,
                'source_type' => $log->source_type,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function submissionEntries(User $subject, ?Course $course, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $query = ActivitySubmission::query()
            ->with('activity:id,course_id,title')
            ->where('user_id', $subject->id);

        if ($course) {
            $query->whereHas('activity', fn ($activityQuery) => $activityQuery->where('course_id', $course->id));
        }

        $this->constrainDate($query, 'submitted_at', $from, $to);

        return $query->get()->map(fn (ActivitySubmission $submission) => [
            'type' => 'submission',
            'title' => sprintf(
                'Intento %d: %s',
                $submission->attempt_number,
                $submission->activity?->title ?? 'Actividad',
            ),
            'delta' => null,
            'balance_after' => null,
            'occurred_at' => $submission->submitted_at?->toIso8601String(),
            'meta' => [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'course_id' => $submission->activity?->course_id,
                'attempt_number' => $submission->attempt_number,
                'status' => $submission->status,
                'score' => $submission->score,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function badgeEntries(User $subject, ?Course $course, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $query = UserBadge::query()
            ->with('badge:id,name,course_id')
            ->where('user_id', $subject->id);

        if ($course) {
            $query->where('course_id', $course->id);
        }

        $this->constrainDate($query, 'awarded_at', $from, $to);

        return $query->get()->map(fn (UserBadge $userBadge) => [
            'type' => 'badge',
            'title' => 'Insignia: '.($userBadge->badge?->name ?? 'Insignia'),
            'delta' => null,
            'balance_after' => null,
            'occurred_at' => $userBadge->awarded_at?->toIso8601String(),
            'meta' => [
                'id' => $userBadge->id,
                'badge_id' => $userBadge->badge_id,
                'course_id' => $userBadge->course_id,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function redemptionEntries(User $subject, ?Course $course, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $query = Redemption::query()
            ->with('reward:id,name,course_id,cost_points')
            ->where('user_id', $subject->id);

        if ($course) {
            $query->whereHas('reward', fn ($rewardQuery) => $rewardQuery->where('course_id', $course->id));
        }

        $this->constrainDate($query, 'redeemed_at', $from, $to);

        return $query->get()->map(fn (Redemption $redemption) => [
            'type' => 'redemption',
            'title' => 'Canje: '.($redemption->reward?->name ?? 'Recompensa'),
            'delta' => $redemption->reward ? -$redemption->reward->cost_points : null,
            'balance_after' => null,
            'occurred_at' => $redemption->redeemed_at?->toIso8601String(),
            'meta' => [
                'id' => $redemption->id,
                'reward_id' => $redemption->reward_id,
                'course_id' => $redemption->reward?->course_id,
                'status' => $redemption->status,
            ],
        ]);
    }

    private function constrainDate($query, string $column, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    private function pointLogTitle(string $sourceType): string
    {
        return match ($sourceType) {
            'submission_graded' => 'Puntos por calificación',
            'activity_submission' => 'Puntos de actividad',
            'attendance' => 'Asistencia a encuentro sincrónico',
            'forum_participation' => 'Participación en foro',
            'redemption' => 'Canje de recompensa',
            default => 'Movimiento de puntos',
        };
    }

    private function ensureViewerCanAccess(User $viewer, User $subject, ?Course $course): void
    {
        if ($viewer->role === 'admin') {
            return;
        }

        if ($viewer->id === $subject->id) {
            if ($course && ! $this->viewerBelongsToCourse($viewer, $course)) {
                abort(403, 'No tienes acceso al historial de este curso.');
            }

            return;
        }

        if ($course && $viewer->role === 'teacher' && $course->owner_id === $viewer->id) {
            $isEnrolled = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('user_id', $subject->id)
                ->exists();

            if ($isEnrolled) {
                return;
            }
        }

        abort(403, 'No tienes permiso para consultar este historial.');
    }

    private function viewerBelongsToCourse(User $viewer, Course $course): bool
    {
        if ($viewer->role === 'teacher' && $course->owner_id === $viewer->id) {
            return true;
        }

        if ($viewer->role !== 'student') {
            return false;
        }

        return Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->exists();
    }
}
