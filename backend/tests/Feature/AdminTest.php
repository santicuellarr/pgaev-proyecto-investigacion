<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_change_user_role(): void
    {
        $admin = $this->createAdmin();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$student->id}/role", [
                'role' => User::ROLE_TEACHER,
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $student->id)
            ->assertJsonPath('user.role', User::ROLE_TEACHER);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => User::ROLE_TEACHER,
        ]);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/admin/overview')
            ->assertForbidden();

        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/admin/users/{$student->id}/status", [
                'status' => User::STATUS_SUSPENDED,
            ])
            ->assertForbidden();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'suspendido@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu cuenta está suspendida.');
    }

    public function test_admin_cannot_demote_own_role(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$admin->id}/role", [
                'role' => User::ROLE_TEACHER,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'No puedes quitarte tu propio rol de administrador.');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_can_list_users_filtered_by_role_and_query(): void
    {
        $admin = $this->createAdmin();
        User::factory()->create([
            'name' => 'Ana García',
            'email' => 'ana@pgaev.edu.co',
            'role' => User::ROLE_STUDENT,
        ]);
        User::factory()->create([
            'name' => 'Carlos Mendoza',
            'email' => 'carlos@pgaev.edu.co',
            'role' => User::ROLE_TEACHER,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?role=student&q=ana')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.email', 'ana@pgaev.edu.co');
    }

    public function test_admin_can_suspend_user_and_overview_counts_it(): void
    {
        $admin = $this->createAdmin();
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'is_active' => true,
        ]);
        $student->createToken('auth-token');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$student->id}/status", [
                'status' => User::STATUS_SUSPENDED,
            ])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('users_suspended', 1)
            ->assertJsonPath('users_by_role.admin', 1);
    }
}
