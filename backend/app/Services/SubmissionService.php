<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubmissionService
{
    public function __construct(
        private readonly OpenAIService $openAIService,
    ) {}

    /**
     * @return array{submission: ActivitySubmission, was_created: bool}
     */
    public function submitActivity(int $activityId, int $userId, string $content, string $idempotencyKey): array
    {
        $existingSubmission = ActivitySubmission::where('idempotency_key', $idempotencyKey)->first();

        if ($existingSubmission) {
            return [
                'submission' => $existingSubmission,
                'was_created' => false,
            ];
        }

        $activity = Activity::findOrFail($activityId);

        $this->ensureStudentIsEnrolled($activity, $userId);
        $this->ensureActivityIsPublished($activity);
        $this->ensureDueDateHasNotPassed($activity);
        $this->ensureAttemptsAreAvailable($activity, $userId);

        $attemptNumber = ActivitySubmission::where('activity_id', $activityId)
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

        $submission = $this->applyAiFeedback($submission);

        return [
            'submission' => $submission,
            'was_created' => true,
        ];
    }

    public function gradeSubmission(ActivitySubmission $submission, float $score, User $grader): ActivitySubmission
    {
        $this->ensureGraderCanGrade($submission, $grader);

        $submission->update([
            'score' => $score,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return $this->applyAiFeedback($submission->fresh(['activity']));
    }

    private function applyAiFeedback(ActivitySubmission $submission): ActivitySubmission
    {
        $submission->loadMissing('activity');

        $submissionContent = $submission->content['text'] ?? '';
        $activityTitle = $submission->activity->title;

        $aiFeedback = $this->openAIService->generateFeedback($submissionContent, $activityTitle);

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
