<?php

namespace Tests\Feature;

use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAITest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_feedback_processes_openai_response_with_http_fake(): void
    {
        Config::set('services.openai.api_key', 'test-openai-key');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Tu respuesta demuestra comprensión del tema. Te sugiero profundizar en los ejemplos prácticos.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(OpenAIService::class);

        $feedback = $service->generateFeedback(
            'La gamificación mejora la motivación estudiantil mediante puntos y recompensas.',
            'Introducción a la Gamificación',
        );

        $this->assertSame(
            'Tu respuesta demuestra comprensión del tema. Te sugiero profundizar en los ejemplos prácticos.',
            $feedback,
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request['model'] === 'gpt-3.5-turbo'
                && $request['messages'][0]['role'] === 'system'
                && str_contains($request['messages'][0]['content'], 'Introducción a la Gamificación')
                && $request['messages'][1]['content'] === 'La gamificación mejora la motivación estudiantil mediante puntos y recompensas.';
        });
    }

    public function test_generate_feedback_returns_default_message_when_openai_fails(): void
    {
        Config::set('services.openai.api_key', 'test-openai-key');

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Service unavailable'],
            ], 503),
        ]);

        $service = app(OpenAIService::class);

        $feedback = $service->generateFeedback('Contenido de prueba', 'Tarea 1');

        $this->assertSame('Retroalimentación pendiente de revisión docente.', $feedback);
    }

    public function test_generate_feedback_returns_default_message_when_api_key_is_missing(): void
    {
        Config::set('services.openai.api_key', null);

        Http::fake();

        $service = app(OpenAIService::class);

        $feedback = $service->generateFeedback('Contenido de prueba', 'Tarea 1');

        $this->assertSame('Retroalimentación pendiente de revisión docente.', $feedback);
        Http::assertNothingSent();
    }

    public function test_ai_timeout_returns_default_message_and_logs(): void
    {
        Config::set('services.openai.api_key', 'test-openai-key');
        Config::set('services.openai.timeout', 2);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 2000 milliseconds');
        });

        $service = app(OpenAIService::class);

        $feedback = $service->generateFeedback('Contenido de prueba', 'Tarea 1', 42);

        $this->assertSame('Retroalimentación pendiente de revisión docente.', $feedback);
        $this->assertTrue(collect($logged)->contains(function ($event) {
            return $event->level === 'warning'
                && str_contains($event->message, 'OpenAI API timeout')
                && ($event->context['submission_id'] ?? null) === 42;
        }));
    }
}
