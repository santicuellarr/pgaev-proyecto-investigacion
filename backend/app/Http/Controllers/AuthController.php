<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'user' => $result['user'],
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'user' => $result['user'],
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refresh($request->bearerToken());

        return response()->json([
            'user' => $result['user'],
            'token' => $result['token'],
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo ya está verificado.',
            ]);
        }

        $this->authService->resendVerification($user);

        return response()->json([
            'message' => 'Enlace de verificación enviado.',
        ]);
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = $this->authService->verifyFromSignedLink($id, $hash);

        return response()->json([
            'message' => 'Correo verificado correctamente.',
            'user' => $user,
        ]);
    }
}
