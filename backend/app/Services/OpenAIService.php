<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function defaultFeedback(): string
    {
        return (string) config(
            'services.openai.default_feedback',
            'Retroalimentación pendiente de revisión docente.',
        );
    }

    public function generateFeedback(string $submissionContent, string $activityTitle, ?int $submissionId = null): string
    {
        $apiKey = config('services.openai.api_key');
        $context = array_filter([
            'submission_id' => $submissionId,
        ], fn ($value) => $value !== null);

        if (empty($apiKey)) {
            Log::warning('OpenAI API key is not configured.', $context);

            return $this->defaultFeedback();
        }

        try {
            $timeout = max(1, (int) config('services.openai.timeout', 10));

            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Eres un tutor universitario pedagógico. Analiza la respuesta del estudiante para la actividad {$activityTitle} y proporciona una retroalimentación constructiva de máximo 2 párrafos",
                        ],
                        [
                            'role' => 'user',
                            'content' => $submissionContent,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI API request failed.', $context + [
                    'status' => $response->status(),
                ]);

                return $this->defaultFeedback();
            }

            return $response->json('choices.0.message.content') ?? $this->defaultFeedback();
        } catch (ConnectionException $exception) {
            Log::warning('OpenAI API timeout.', $context + [
                'message' => $exception->getMessage(),
            ]);

            return $this->defaultFeedback();
        } catch (\Throwable $exception) {
            Log::warning('OpenAI API exception.', $context + [
                'message' => $exception->getMessage(),
            ]);

            return $this->defaultFeedback();
        }
    }
}
