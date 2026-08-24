<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Seeds the Administrator role/permission only. A user can no longer be fabricated
     * here — identity (id, name) must come from a real bpms.users row via login. Assign
     * this role to the first real bpms-authenticated user through User Management.
     */
    public function run(): void
    {
        $dashboardView = Permission::firstOrCreate(['name' => 'dashboard.view']);

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $adminRole->givePermissionTo($dashboardView);
    }
}
