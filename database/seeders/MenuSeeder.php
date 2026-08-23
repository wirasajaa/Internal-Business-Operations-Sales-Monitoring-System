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
        $permissions = collect(['menus.view', 'menus.create', 'menus.update', 'menus.delete'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name]));

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
    }
}
