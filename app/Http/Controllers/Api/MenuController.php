<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::whereNull('parent_id')
            ->orderBy('order')
            ->with(['children' => fn ($q) => $q->orderBy('order'), 'children.children' => fn ($q) => $q->orderBy('order')])
            ->get();

        return response()->json(['data' => $menus]);
    }

    public function store(StoreMenuRequest $request)
    {
        $menu = Menu::create($request->validated());

        return response()->json(['data' => $menu], 201);
    }

    public function update(UpdateMenuRequest $request, Menu $menu)
    {
        $menu->update($request->validated());

        return response()->json(['data' => $menu->fresh()]);
    }

    public function destroy(Menu $menu)
    {
        if ($menu->children()->exists()) {
            return response()->json([
                'message' => 'Hapus sub-menu terlebih dahulu sebelum menghapus menu ini.',
            ], 422);
        }

        $menu->delete();

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }

    public function availablePermissions()
    {
        return response()->json(['data' => Permission::orderBy('name')->pluck('name')]);
    }

    public function navigation(Request $request)
    {
        $roots = Menu::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order')
            ->with([
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('order'),
                'children.children' => fn ($q) => $q->where('is_active', true)->orderBy('order'),
            ])
            ->get();

        return response()->json(['data' => $this->filterForUser($roots, $request->user())]);
    }

    private function filterForUser($menus, $user): array
    {
        $result = [];

        foreach ($menus as $menu) {
            if ($menu->permission_name && ! $user->can($menu->permission_name)) {
                continue;
            }

            $children = $this->filterForUser($menu->children, $user);

            if (! $menu->path && empty($children)) {
                continue;
            }

            $result[] = [
                'id' => $menu->id,
                'label' => $menu->label,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'children' => $children,
            ];
        }

        return $result;
    }
}
