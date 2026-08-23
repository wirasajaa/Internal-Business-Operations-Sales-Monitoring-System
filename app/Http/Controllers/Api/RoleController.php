<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return response()->json(['data' => $roles]);
    }

    public function permissions()
    {
        return response()->json(['data' => Permission::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        // guard_name is forced to 'web' to match how every existing Role/Permission was
        // seeded — without this, Spatie infers the guard from the current HTTP context
        // (here 'sanctum', since this endpoint sits behind auth:sanctum), which would
        // silently create a Role that no existing Permission can be attached to.
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permission_ids'] ?? []);

        return response()->json(['data' => $role->load('permissions')], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permission_ids'] ?? []);

        return response()->json(['data' => $role->fresh()->load('permissions')]);
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Role masih dipakai oleh user lain, tidak dapat dihapus.',
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }
}
