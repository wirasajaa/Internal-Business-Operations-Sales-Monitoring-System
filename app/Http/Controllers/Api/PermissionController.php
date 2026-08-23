<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Models\Menu;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        return response()->json(['data' => $permissions]);
    }

    public function store(StorePermissionRequest $request)
    {
        // guard_name forced to 'web' — see RoleController::store for why relying on
        // Spatie's context-inferred guard is unsafe inside an auth:sanctum route.
        $permission = Permission::create([...$request->validated(), 'guard_name' => 'web']);

        return response()->json(['data' => $permission], 201);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission->update($request->validated());

        return response()->json(['data' => $permission->fresh()]);
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'Permission masih dipakai oleh role lain, tidak dapat dihapus.',
            ], 422);
        }

        if (Menu::where('permission_name', $permission->name)->exists()) {
            return response()->json([
                'message' => 'Permission masih dirujuk oleh menu, tidak dapat dihapus.',
            ], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Permission berhasil dihapus.']);
    }
}
