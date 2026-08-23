<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('name')->paginate(20);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $role = Role::findOrFail($data['role_id']);
        $user->syncRoles([$role]);

        return response()->json(['data' => $user->load('roles')], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $role = Role::findOrFail($data['role_id']);

        // Block an Administrator from removing their own Administrator role
        // if doing so would leave zero active Administrators in the system.
        if ($user->id === $request->user()->id
            && $user->hasRole('Administrator')
            && $role->name !== 'Administrator'
            && $this->activeAdministratorCount() <= 1) {
            return response()->json([
                'message' => 'Tidak dapat melepas role Administrator dari akun sendiri karena akan membuat sistem kehilangan seluruh Administrator aktif.',
            ], 422);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles([$role]);

        return response()->json(['data' => $user->fresh()->load('roles')]);
    }

    public function activate(Request $request, User $user)
    {
        $user->update(['is_active' => true]);

        return response()->json(['data' => $user->fresh()->load('roles')]);
    }

    public function deactivate(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Tidak dapat menonaktifkan akun sendiri.',
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json(['data' => $user->fresh()->load('roles')]);
    }

    private function activeAdministratorCount(): int
    {
        return User::role('Administrator')->where('is_active', true)->count();
    }
}
