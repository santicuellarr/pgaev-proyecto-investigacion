<?php

use App\Http\Middleware\EnsureEmailVerifiedWhenRequired;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            RequestId::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'verified.when_required' => EnsureEmailVerifiedWhenRequired::class,
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ], $exception->status);
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'No autenticado.',
                    'errors' => [],
                ], 401);
            }

            if ($exception instanceof AuthorizationException) {
                return response()->json([
                    'message' => $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'Esta acción no está autorizada.',
                    'errors' => [],
                ], 403);
            }

            if ($exception instanceof ThrottleRequestsException) {
                return response()->json([
                    'message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
                    'errors' => [],
                ], 429);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $message = $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Error.';

                return response()->json([
                    'message' => $message,
                    'errors' => [],
                ], $exception->getStatusCode());
            }

            return null;
        });
    })->create();
