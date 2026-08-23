<?php

namespace Tests\Feature\Menu;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create(['password' => 'password', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('t')->plainTextToken;
    }

    public function test_admin_can_list_menus(): void
    {
        $user = $this->makeUserWithPermissions(['menus.view']);
        Menu::factory()->create(['label' => 'Dashboard']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/menus')
            ->assertStatus(200)
            ->assertJsonPath('data.0.label', 'Dashboard');
    }

    public function test_listing_menus_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/menus')
            ->assertStatus(403);
    }

    public function test_can_create_a_menu(): void
    {
        $user = $this->makeUserWithPermissions(['menus.create']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/menus', ['label' => 'Laporan', 'path' => '/laporan'])
            ->assertStatus(201)
            ->assertJsonPath('data.label', 'Laporan');

        $this->assertDatabaseHas('menus', ['label' => 'Laporan']);
    }

    public function test_creating_a_menu_requires_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/menus', ['label' => 'Laporan'])
            ->assertStatus(403);
    }

    public function test_menu_depth_cannot_exceed_three_levels(): void
    {
        $user = $this->makeUserWithPermissions(['menus.create']);

        $level1 = Menu::factory()->create(['label' => 'L1']);
        $level2 = Menu::factory()->create(['label' => 'L2', 'parent_id' => $level1->id]);
        $level3 = Menu::factory()->create(['label' => 'L3', 'parent_id' => $level2->id]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/menus', ['label' => 'L4', 'parent_id' => $level3->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_can_update_a_menu(): void
    {
        $user = $this->makeUserWithPermissions(['menus.update']);
        $menu = Menu::factory()->create(['label' => 'Lama']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/menus/{$menu->id}", ['label' => 'Baru'])
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'Baru');
    }

    public function test_menu_cannot_become_its_own_parent(): void
    {
        $user = $this->makeUserWithPermissions(['menus.update']);
        $menu = Menu::factory()->create(['label' => 'Menu']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/menus/{$menu->id}", ['label' => 'Menu', 'parent_id' => $menu->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_can_delete_a_menu_without_children(): void
    {
        $user = $this->makeUserWithPermissions(['menus.delete']);
        $menu = Menu::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/menus/{$menu->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_deleting_a_menu_with_children_is_rejected(): void
    {
        $user = $this->makeUserWithPermissions(['menus.delete']);
        $parent = Menu::factory()->create();
        Menu::factory()->create(['parent_id' => $parent->id]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/menus/{$parent->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('menus', ['id' => $parent->id]);
    }

    public function test_navigation_hides_branch_the_user_lacks_permission_for(): void
    {
        $user = $this->makeUserWithPermissions([]);
        Menu::factory()->create(['label' => 'Rahasia', 'path' => '/rahasia', 'permission_name' => 'secret.view']);
        Menu::factory()->create(['label' => 'Umum', 'path' => '/umum', 'permission_name' => null]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/menus/navigation')
            ->assertStatus(200);

        $labels = collect($response->json('data'))->pluck('label');
        $this->assertTrue($labels->contains('Umum'));
        $this->assertFalse($labels->contains('Rahasia'));
    }

    public function test_navigation_hides_empty_dropdown_parent(): void
    {
        $user = $this->makeUserWithPermissions([]);
        $parent = Menu::factory()->create(['label' => 'Pengaturan', 'path' => null, 'permission_name' => null]);
        Menu::factory()->create([
            'label' => 'Sub Rahasia',
            'parent_id' => $parent->id,
            'permission_name' => 'secret.view',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/menus/navigation')
            ->assertStatus(200);

        $labels = collect($response->json('data'))->pluck('label');
        $this->assertFalse($labels->contains('Pengaturan'));
    }

    public function test_navigation_keeps_dropdown_parent_with_a_visible_child(): void
    {
        $user = $this->makeUserWithPermissions([]);
        $parent = Menu::factory()->create(['label' => 'Pengaturan', 'path' => null, 'permission_name' => null]);
        Menu::factory()->create([
            'label' => 'Sub Umum',
            'parent_id' => $parent->id,
            'path' => '/pengaturan/umum',
            'permission_name' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/menus/navigation')
            ->assertStatus(200);

        $tree = collect($response->json('data'));
        $parentNode = $tree->firstWhere('label', 'Pengaturan');
        $this->assertNotNull($parentNode);
        $this->assertCount(1, $parentNode['children']);
    }
}
