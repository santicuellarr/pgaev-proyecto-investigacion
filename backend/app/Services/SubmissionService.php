<?php

namespace App\Services;

use App\Jobs\GenerateAiFeedbackJob;
use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubmissionService
{
    public const GRADED_SOURCE_TYPE = 'submission_graded';

    public function __construct(
        private readonly OpenAIService $openAIService,
        private readonly GamificationService $gamificationService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @return array{submission: ActivitySubmission, was_created: bool}
     */
    public function submitActivity(int $activityId, int $userId, string $content, string $idempotencyKey): array
    {
        $result = DB::transaction(function () use ($activityId, $userId, $content, $idempotencyKey) {
            $existingSubmission = ActivitySubmission::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingSubmission) {
                return [
                    'submission' => $existingSubmission,
                    'was_created' => false,
                ];
            }

            $activity = Activity::query()
                ->whereKey($activityId)
                ->lockForUpdate()
                ->firstOrFail();

            ActivitySubmission::query()
                ->where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();

            $this->ensureStudentIsEnrolled($activity, $userId);
            $this->ensureActivityIsPublished($activity);
            $this->ensureDueDateHasNotPassed($activity);
            $this->ensureAttemptsAreAvailable($activity, $userId);

            $attemptNumber = ActivitySubmission::query()
                ->where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->count() + 1;

            $submission = ActivitySubmission::create([
                'activity_id' => $activityId,
                'user_id' => $userId,
                'attempt_number' => $attemptNumber,
                'status' => 'submitted',
                'content' => ['text' => $content],
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ]);

            return [
                'submission' => $submission,
                'was_created' => true,
            ];
        });

        if ($result['was_created']) {
            $result['submission'] = $this->applyAiFeedback($result['submission']);
            $result['submission']->loadMissing('activity');
            $this->gamificationService->checkBadges(
                $userId,
                $result['submission']->activity->course_id,
            );
        }

        return $result;
    }

    /**
     * @return array{submission: ActivitySubmission, points_awarded: int}
     */
    public function gradeSubmission(ActivitySubmission $submission, float $score, User $grader, ?string $teacherFeedback = null): array
    {
        $this->ensureGraderCanGrade($submission, $grader);

        $pointsAwarded = 0;

        $graded = DB::transaction(function () use ($submission, $score, $teacherFeedback, &$pointsAwarded) {
            $lockedSubmission = ActivitySubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activity = Activity::query()
                ->whereKey($lockedSubmission->activity_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSubmission->update([
                'score' => $score,
                'status' => 'graded',
                'graded_at' => now(),
                'teacher_feedback' => $teacherFeedback,
            ]);

            $pointsForScore = $this->pointsForScore($activity, $score);
            $alreadyAwarded = (int) PointLog::query()
                ->where('source_type', self::GRADED_SOURCE_TYPE)
                ->where('source_id', $lockedSubmission->id)
                ->sum('delta');

            $delta = $pointsForScore - $alreadyAwarded;
            $pointsAwarded = $delta;

            if ($delta !== 0) {
                $this->gamificationService->awardPoints(
                    $lockedSubmission->user_id,
                    $activity->course_id,
                    $delta,
                    self::GRADED_SOURCE_TYPE,
                    $lockedSubmission->id,
                );
            }

            return $lockedSubmission->fresh(['activity']);
        });

        $this->notificationService->createNotification(
            $graded->user_id,
            'Actividad calificada',
            sprintf(
                'Tu actividad %s fue calificada (%d pts)',
                $graded->activity->title,
                $this->pointsForScore($graded->activity, (float) $graded->score),
            ),
            Notification::TYPE_GRADE,
        );

        $this->gamificationService->checkBadges($graded->user_id, $graded->activity->course_id);

        Log::info('submission.graded', [
            'submission_id' => $graded->id,
            'user_id' => $graded->user_id,
            'activity_id' => $graded->activity_id,
            'score' => $graded->score,
            'points_awarded' => $pointsAwarded,
        ]);

        return [
            'submission' => $this->applyAiFeedback($graded),
            'points_awarded' => $pointsAwarded,
        ];
    }

    public function regenerateAiFeedback(ActivitySubmission $submission, User $teacher): ActivitySubmission
    {
        $this->ensureGraderCanGrade($submission, $teacher);

        return $this->applyAiFeedback($submission);
    }

    /**
     * Convierte la nota (0–max_score) en puntos del curso.
     * max_score no está en la tabla activities; se usa Activity::DEFAULT_MAX_SCORE (100).
     */
    public function pointsForScore(Activity $activity, float $score): int
    {
        $maxScore = Activity::DEFAULT_MAX_SCORE;

        return (int) round($activity->base_points * $score / $maxScore);
    }

    private function applyAiFeedback(ActivitySubmission $submission): ActivitySubmission
    {
        $submission->loadMissing('activity');

        $submissionContent = $submission->content['text'] ?? '';
        $activityTitle = $submission->activity->title;

        if (config('services.openai.queue')) {
            GenerateAiFeedbackJob::dispatch($submission->id);

            return $submission->fresh();
        }

        $aiFeedback = $this->openAIService->generateFeedback(
            $submissionContent,
            $activityTitle,
            $submission->id,
        );

        $submission->update([
            'ai_feedback' => $aiFeedback,
        ]);

        return $submission->fresh();
    }

    private function ensureGraderCanGrade(ActivitySubmission $submission, User $grader): void
    {
        if (! in_array($grader->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden calificar envíos.');
        }

        $submission->loadMissing('activity.course');

        if ($grader->role !== 'admin' && $submission->activity->course->owner_id !== $grader->id) {
            abort(403, 'No tienes permiso para calificar este envío.');
        }
    }

    private function ensureStudentIsEnrolled(Activity $activity, int $userId): void
    {
        $isEnrolled = Enrollment::where('course_id', $activity->course_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            throw ValidationException::withMessages([
                'activity' => ['Debes estar inscrito en el curso para enviar esta actividad.'],
            ]);
        }
    }

    private function ensureActivityIsPublished(Activity $activity): void
    {
        if (! $activity->is_published) {
            throw ValidationException::withMessages([
                'activity' => ['Esta actividad no está publicada.'],
            ]);
        }
    }

    private function ensureDueDateHasNotPassed(Activity $activity): void
    {
        if ($activity->due_at && $activity->due_at->isPast()) {
            throw ValidationException::withMessages([
                'activity' => ['La fecha límite de esta actividad ya venció.'],
            ]);
        }
    }

    private function ensureAttemptsAreAvailable(Activity $activity, int $userId): void
    {
        $attemptCount = ActivitySubmission::where('activity_id', $activity->id)
            ->where('user_id', $userId)
            ->count();

        if ($attemptCount >= $activity->max_attempts) {
            throw ValidationException::withMessages([
                'activity' => ['Has alcanzado el número máximo de intentos para esta actividad.'],
            ]);
        }
    }
}
