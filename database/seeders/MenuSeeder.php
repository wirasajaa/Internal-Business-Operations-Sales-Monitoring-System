<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'menus.view', 'menus.create', 'menus.update', 'menus.delete',
            'sales.view',
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name]));

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $adminRole->givePermissionTo($permissions);

        Menu::firstOrCreate(
            ['label' => 'Dashboard'],
            ['path' => '/dashboard', 'permission_name' => null, 'order' => 0]
        );

        Menu::firstOrCreate(
            ['label' => 'Menu Management'],
            ['path' => '/settings/menus', 'permission_name' => 'menus.view', 'order' => 10]
        );

        Menu::firstOrCreate(
            ['label' => 'Sales Order'],
            ['path' => '/sales/orders', 'permission_name' => 'sales.view', 'order' => 20]
        );

        $accessMenu = Menu::firstOrCreate(
            ['label' => 'Manajemen Akses', 'parent_id' => null],
            ['path' => null, 'permission_name' => null, 'order' => 15]
        );

        Menu::firstOrCreate(
            ['label' => 'Users', 'parent_id' => $accessMenu->id],
            ['path' => '/settings/users', 'permission_name' => 'users.view', 'order' => 1]
        );

        Menu::firstOrCreate(
            ['label' => 'Roles', 'parent_id' => $accessMenu->id],
            ['path' => '/settings/roles', 'permission_name' => 'roles.view', 'order' => 2]
        );

        Menu::firstOrCreate(
            ['label' => 'Permissions', 'parent_id' => $accessMenu->id],
            ['path' => '/settings/permissions', 'permission_name' => 'permissions.view', 'order' => 3]
        );
    }
}
