<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'permission:dashboard.view'])->group(function () {
    Route::get('dashboard/summary', function () {
        return response()->json(['message' => 'Selamat datang di dashboard.']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('menus/navigation', [MenuController::class, 'navigation']);

    Route::middleware('permission:menus.view')->get('menus', [MenuController::class, 'index']);
    Route::middleware('permission:menus.view')->get('menus/permissions', [MenuController::class, 'availablePermissions']);
    Route::middleware('permission:menus.create')->post('menus', [MenuController::class, 'store']);
    Route::middleware('permission:menus.update')->put('menus/{menu}', [MenuController::class, 'update']);
    Route::middleware('permission:menus.delete')->delete('menus/{menu}', [MenuController::class, 'destroy']);

    Route::middleware('permission:users.view')->get('users', [UserController::class, 'index']);
    Route::middleware('permission:users.create')->post('users', [UserController::class, 'store']);
    Route::middleware('permission:users.update')->put('users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:users.update')->patch('users/{user}/activate', [UserController::class, 'activate']);
    Route::middleware('permission:users.deactivate')->patch('users/{user}/deactivate', [UserController::class, 'deactivate']);

    Route::middleware('permission:roles.view')->get('roles', [RoleController::class, 'index']);
    Route::middleware('permission:roles.view')->get('roles/permissions', [RoleController::class, 'permissions']);
    Route::middleware('permission:roles.create')->post('roles', [RoleController::class, 'store']);
    Route::middleware('permission:roles.update')->put('roles/{role}', [RoleController::class, 'update']);
    Route::middleware('permission:roles.delete')->delete('roles/{role}', [RoleController::class, 'destroy']);

    Route::middleware('permission:permissions.view')->get('permissions', [PermissionController::class, 'index']);
    Route::middleware('permission:permissions.create')->post('permissions', [PermissionController::class, 'store']);
    Route::middleware('permission:permissions.update')->put('permissions/{permission}', [PermissionController::class, 'update']);
    Route::middleware('permission:permissions.delete')->delete('permissions/{permission}', [PermissionController::class, 'destroy']);

    Route::middleware('permission:sales.view')->get('sales/orders', [SalesOrderController::class, 'index']);
    Route::middleware('permission:sales.view')->get('sales/order-statuses', [SalesOrderController::class, 'statuses']);
    Route::middleware('permission:sales.view')->patch('sales/orders/{id}/status', [SalesOrderController::class, 'updateStatus']);
});
