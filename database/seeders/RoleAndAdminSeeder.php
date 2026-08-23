<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $dashboardView = Permission::firstOrCreate(['name' => 'dashboard.view']);

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $adminRole->givePermissionTo($dashboardView);

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@erp.local')],
            [
                'name' => 'Administrator',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'is_active' => true,
            ]
        );

        if (! $admin->hasRole('Administrator')) {
            $admin->assignRole($adminRole);
        }
    }
}
