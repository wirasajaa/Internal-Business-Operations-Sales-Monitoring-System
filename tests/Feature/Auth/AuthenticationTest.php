<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = $this->makeUser(['email' => 'user@erp.local']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@erp.local',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_at', 'user', 'roles', 'permissions'])
            ->assertCookie('refresh_token');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->makeUser(['email' => 'user@erp.local']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@erp.local',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->makeUser(['email' => 'inactive@erp.local', 'is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@erp.local',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_refresh_rotates_the_token_and_old_one_becomes_invalid(): void
    {
        $this->makeUser(['email' => 'user@erp.local']);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'user@erp.local',
            'password' => 'password',
        ]);
        // The refresh cookie is set on unencrypted api routes (no EncryptCookies
        // middleware there), so read it back without attempting decryption.
        $refreshTokenValue = $login->getCookie('refresh_token', false)->getValue();

        $refresh = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshTokenValue)
            ->postJson('/api/auth/refresh');
        $refresh->assertStatus(200)->assertJsonStructure(['access_token']);

        // Reusing the same (now-revoked) refresh token cookie must fail.
        $reuse = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshTokenValue)
            ->postJson('/api/auth/refresh');
        $reuse->assertStatus(401);
    }

    public function test_refresh_fails_without_a_cookie(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_the_access_token(): void
    {
        $user = $this->makeUser(['email' => 'user@erp.local']);
        $token = $user->createToken('access-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // The auth guard memoizes its resolved user per test method; force it to
        // re-resolve so the second request re-checks the (now-deleted) token.
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_me_returns_roles_and_permissions(): void
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $permission = Permission::firstOrCreate(['name' => 'dashboard.view']);
        $role->givePermissionTo($permission);

        $user = $this->makeUser(['email' => 'user@erp.local']);
        $user->assignRole($role);
        $token = $user->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('roles.0', 'Administrator')
            ->assertJsonPath('permissions.0', 'dashboard.view');
    }

    public function test_protected_route_rejects_request_without_token(): void
    {
        $this->getJson('/api/dashboard/summary')->assertStatus(401);
    }

    public function test_protected_route_rejects_user_without_permission(): void
    {
        $user = $this->makeUser(['email' => 'user@erp.local']);
        $token = $user->createToken('access-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/summary')
            ->assertStatus(403);
    }

    public function test_protected_route_allows_user_with_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $permission = Permission::firstOrCreate(['name' => 'dashboard.view']);
        $role->givePermissionTo($permission);

        $user = $this->makeUser(['email' => 'user@erp.local']);
        $user->assignRole($role);
        $token = $user->createToken('access-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/summary')
            ->assertStatus(200);
    }
}
