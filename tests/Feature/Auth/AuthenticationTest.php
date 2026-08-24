<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /*
     * The test suite runs against in-memory SQLite (phpunit.xml), so the real
     * bpms.validate_login_apps Postgres function and bpms.users table cannot be
     * queried here — verified live against the real dev Postgres DB during
     * development. These tests mock DB::selectOne to exercise this controller's
     * own logic (envelope decoding, enabled/is_deleted checks, auto-provisioning).
     */
    private function mockValidateLoginApps(bool $result, string $msg = ''): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->with(Mockery::on(fn ($sql) => str_contains($sql, 'validate_login_apps')), Mockery::any())
            ->andReturn((object) ['result' => json_encode([
                'body' => null,
                'respon' => ['result' => $result, 'msg' => $msg],
            ])]);
    }

    private function mockBpmsUserLookup(?array $row): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->with(Mockery::on(fn ($sql) => str_contains($sql, 'bpms.users')), Mockery::any())
            ->andReturn($row ? (object) $row : null);
    }

    private function bpmsRow(array $overrides = []): array
    {
        return array_merge([
            'id' => '542',
            'first_name' => 'Staff',
            'last_name' => 'Warehouse',
            'enabled' => true,
            'is_deleted' => false,
        ], $overrides);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true], $attributes));
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    public function test_login_succeeds_with_valid_credentials_and_provisions_new_user(): void
    {
        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow(['id' => '542']));

        $response = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_at', 'user', 'roles', 'permissions'])
            ->assertJsonPath('user.id', '542')
            ->assertJsonPath('user.username', 'Warehouse2')
            ->assertJsonPath('user.name', 'Staff Warehouse')
            ->assertCookie('refresh_token');
    }

    public function test_login_reuses_the_existing_local_record_on_a_second_login(): void
    {
        $existing = $this->makeUser(['id' => '542', 'username' => 'Warehouse2', 'name' => 'Staff Warehouse']);

        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow(['id' => '542']));

        $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
        ])->assertStatus(200);

        $this->assertSame(1, User::where('username', 'Warehouse2')->count());
        $this->assertSame($existing->id, User::where('username', 'Warehouse2')->first()->id);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->mockValidateLoginApps(false, 'Gagal Login, Pastikan Username dan Password benar');

        $response = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Gagal Login, Pastikan Username dan Password benar');
    }

    public function test_login_fails_when_bpms_user_row_cannot_be_found_after_validation(): void
    {
        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup(null);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'ghost-user',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_user_disabled_in_bpms(): void
    {
        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow(['enabled' => false]));

        $response = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Akun tidak aktif.');
    }

    public function test_login_fails_for_user_deleted_in_bpms(): void
    {
        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow(['is_deleted' => true]));

        $response = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Akun tidak aktif.');
    }

    public function test_login_fails_when_locally_deactivated_despite_valid_bpms_credentials(): void
    {
        $this->makeUser(['id' => '542', 'username' => 'Warehouse2', 'is_active' => false]);

        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow(['id' => '542']));

        $response = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Akun tidak aktif.');
    }

    public function test_refresh_rotates_the_token_and_old_one_becomes_invalid(): void
    {
        $this->mockValidateLoginApps(true);
        $this->mockBpmsUserLookup($this->bpmsRow());

        $login = $this->postJson('/api/auth/login', [
            'username' => 'Warehouse2',
            'password' => 'whatever-bpms-verified',
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
        $user = $this->makeUser(['username' => 'user1']);
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

        $user = $this->makeUser(['username' => 'user1']);
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
        $user = $this->makeUser(['username' => 'user1']);
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

        $user = $this->makeUser(['username' => 'user1']);
        $user->assignRole($role);
        $token = $user->createToken('access-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/summary')
            ->assertStatus(200);
    }
}
