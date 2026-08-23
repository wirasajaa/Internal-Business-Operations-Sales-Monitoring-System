<?php

namespace Tests\Feature\Role;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create(['password' => 'password', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name]));
        }

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    public function test_admin_can_list_roles(): void
    {
        $user = $this->makeUserWithPermissions(['roles.view']);
        Role::create(['name' => 'Viewer']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/roles')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Viewer']);
    }

    public function test_listing_roles_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/roles')
            ->assertStatus(403);
    }

    public function test_can_list_permissions_for_role_form(): void
    {
        $user = $this->makeUserWithPermissions(['roles.view']);
        Permission::firstOrCreate(['name' => 'sample.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/roles/permissions')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'sample.view']);
    }

    public function test_can_create_a_role_with_permissions(): void
    {
        $user = $this->makeUserWithPermissions(['roles.create']);
        $permission = Permission::firstOrCreate(['name' => 'sample.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/roles', ['name' => 'Viewer', 'permission_ids' => [$permission->id]])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Viewer')
            ->assertJsonPath('data.permissions.0.name', 'sample.view');
    }

    public function test_creating_a_role_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/roles', ['name' => 'Viewer'])
            ->assertStatus(403);
    }

    public function test_can_update_a_role_name_and_permissions(): void
    {
        $user = $this->makeUserWithPermissions(['roles.update']);
        $role = Role::create(['name' => 'Old']);
        $permission = Permission::firstOrCreate(['name' => 'sample.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/roles/{$role->id}", ['name' => 'New', 'permission_ids' => [$permission->id]])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.permissions.0.name', 'sample.view');
    }

    public function test_can_delete_a_role_without_users(): void
    {
        $user = $this->makeUserWithPermissions(['roles.delete']);
        $role = Role::create(['name' => 'Unused']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_with_users_is_rejected(): void
    {
        $user = $this->makeUserWithPermissions(['roles.delete']);
        $role = Role::create(['name' => 'InUse']);
        User::factory()->create()->assignRole($role);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
