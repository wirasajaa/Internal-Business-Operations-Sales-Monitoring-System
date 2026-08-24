<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(array $permissionNames, ?string $roleName = null): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name]));
        }

        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->assignRole($role);
        }

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    public function test_admin_can_list_users(): void
    {
        $user = $this->makeUserWithPermissions(['users.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_listing_users_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_manual_user_creation_is_disabled_in_favour_of_bpms_auto_provisioning(): void
    {
        $admin = $this->makeUserWithPermissions(['users.create']);
        $role = Role::create(['name' => 'Viewer']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/users', ['name' => 'Budi', 'role_id' => $role->id])
            ->assertStatus(409);
    }

    public function test_creating_a_user_requires_permission(): void
    {
        $admin = $this->makeUserWithPermissions([]);
        $role = Role::create(['name' => 'Viewer']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/users', ['name' => 'Budi', 'role_id' => $role->id])
            ->assertStatus(403);
    }

    public function test_can_update_a_user_and_change_role(): void
    {
        $admin = $this->makeUserWithPermissions(['users.update']);
        $target = User::factory()->create();
        $roleA = Role::create(['name' => 'RoleA']);
        $roleB = Role::create(['name' => 'RoleB']);
        $target->assignRole($roleA);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'role_id' => $roleB->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.roles.0.name', 'RoleB');
    }

    public function test_can_activate_and_deactivate_another_user(): void
    {
        $admin = $this->makeUserWithPermissions(['users.update', 'users.deactivate']);
        $target = User::factory()->create(['is_active' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->patchJson("/api/users/{$target->id}/deactivate")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->patchJson("/api/users/{$target->id}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_user_cannot_deactivate_self(): void
    {
        $admin = $this->makeUserWithPermissions(['users.deactivate']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->patchJson("/api/users/{$admin->id}/deactivate")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_last_administrator_cannot_remove_own_administrator_role(): void
    {
        $admin = $this->makeUserWithPermissions(['users.update'], 'Administrator');
        $otherRole = Role::create(['name' => 'Viewer']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/users/{$admin->id}", [
                'name' => $admin->name,
                'role_id' => $otherRole->id,
            ])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasRole('Administrator'));
    }

    public function test_administrator_can_change_own_role_when_another_active_administrator_exists(): void
    {
        $admin = $this->makeUserWithPermissions(['users.update'], 'Administrator');
        $this->makeUserWithPermissions([], 'Administrator');
        $otherRole = Role::create(['name' => 'Viewer']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/users/{$admin->id}", [
                'name' => $admin->name,
                'role_id' => $otherRole->id,
            ])
            ->assertStatus(200);

        $this->assertFalse($admin->fresh()->hasRole('Administrator'));
    }
}
