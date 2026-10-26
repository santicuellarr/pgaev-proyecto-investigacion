<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Santiago Test',
            'email' => 'santiago@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.email', 'santiago@example.com')
            ->assertJsonPath('user.role', 'student');

        $this->assertDatabaseHas('users', [
            'email' => 'santiago@example.com',
            'role' => 'student',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unverified_user_is_forbidden_on_protected_route_when_verification_is_required(): void
    {
        Config::set('pgaev.require_verification', true);

        $user = User::factory()->unverified()->create(['role' => 'student']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/courses')
            ->assertForbidden()
            ->assertJsonPath('message', 'Debes verificar tu correo electrónico.');
    }

    public function test_signed_verification_link_marks_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'student']);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Correo verificado correctamente.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_token_refresh_issues_new_token_and_revokes_the_old_one(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $oldToken = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($oldToken)
            ->postJson('/api/token/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $newToken = $response->json('token');

        $this->assertNotSame($oldToken, $newToken);

        $this->withToken($oldToken)
            ->getJson('/api/user')
            ->assertUnauthorized();

        $this->withToken($newToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_expired_token_returns_unauthorized(): void
    {
        Config::set('sanctum.expiration', 60);

        $user = User::factory()->create(['role' => 'student']);
        $newToken = $user->createToken('auth-token');
        $plainToken = $newToken->plainTextToken;

        $newToken->accessToken->forceFill([
            'created_at' => now()->subMinutes(61),
        ])->save();

        $this->withToken($plainToken)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_register_sends_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Nuevo Estudiante',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'nuevo@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }
}
