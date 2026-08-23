<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MenuController;
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
});
