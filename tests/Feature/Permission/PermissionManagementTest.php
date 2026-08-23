<?php

namespace Tests\Feature\Permission;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
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

    public function test_admin_can_list_permissions(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.view']);
        Permission::firstOrCreate(['name' => 'sample.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/permissions')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'sample.view']);
    }

    public function test_listing_permissions_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/permissions')
            ->assertStatus(403);
    }

    public function test_can_create_a_permission(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.create']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/permissions', ['name' => 'sample.view'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'sample.view');
    }

    public function test_creating_a_permission_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/permissions', ['name' => 'sample.view'])
            ->assertStatus(403);
    }

    public function test_can_update_a_permission(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.update']);
        $permission = Permission::firstOrCreate(['name' => 'old.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/permissions/{$permission->id}", ['name' => 'new.view'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'new.view');
    }

    public function test_can_delete_an_unused_permission(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.delete']);
        $permission = Permission::firstOrCreate(['name' => 'unused.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_deleting_a_permission_assigned_to_a_role_is_rejected(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.delete']);
        $permission = Permission::firstOrCreate(['name' => 'inuse.view']);
        Role::create(['name' => 'Viewer'])->givePermissionTo($permission);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_deleting_a_permission_referenced_by_a_menu_is_rejected(): void
    {
        $user = $this->makeUserWithPermissions(['permissions.delete']);
        $permission = Permission::firstOrCreate(['name' => 'menu.gated']);
        Menu::factory()->create(['permission_name' => 'menu.gated']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }
}
