<?php

namespace App\Jobs;

use App\Models\ActivitySubmission;
use App\Services\OpenAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAiFeedbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $submissionId) {}

    public function handle(OpenAIService $openAIService): void
    {
        $submission = ActivitySubmission::query()->with('activity')->find($this->submissionId);

        if (! $submission) {
            return;
        }

        $feedback = $openAIService->generateFeedback(
            $submission->content['text'] ?? '',
            $submission->activity?->title ?? 'Actividad',
            $submission->id,
        );

        $submission->update([
            'ai_feedback' => $feedback,
        ]);
    }
}
