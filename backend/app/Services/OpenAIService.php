<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private const DEFAULT_FEEDBACK = 'Retroalimentación pendiente de revisión docente.';

    public function generateFeedback(string $submissionContent, string $activityTitle): string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            Log::error('OpenAI API key is not configured.');

            return self::DEFAULT_FEEDBACK;
        }

        try {
            $response = Http::timeout(10)
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
                Log::error('OpenAI API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return self::DEFAULT_FEEDBACK;
            }

            return $response->json('choices.0.message.content') ?? self::DEFAULT_FEEDBACK;
        } catch (\Throwable $exception) {
            Log::error('OpenAI API exception.', [
                'message' => $exception->getMessage(),
            ]);

            return self::DEFAULT_FEEDBACK;
        }
    }
}
