<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if ($user->is_active === false) {
            abort(403, 'Tu cuenta está suspendida.');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Acepta un Bearer aunque Sanctum ya lo considere expirado, siempre que el token exista.
     *
     * @return array{user: User, token: string}
     */
    public function refresh(?string $plainToken): array
    {
        if (! $plainToken) {
            abort(401, 'Unauthenticated.');
        }

        $accessToken = PersonalAccessToken::findToken($plainToken);

        if (! $accessToken || ! $accessToken->tokenable instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $user = $accessToken->tokenable;
        $accessToken->delete();

        if ($user->is_active === false) {
            abort(403, 'Tu cuenta está suspendida.');
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
        ];
    }

    public function resendVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }

    public function verifyFromSignedLink(int $id, string $hash): User
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'El enlace de verificación no es válido.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user->fresh();
    }
}
