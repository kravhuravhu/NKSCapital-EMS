<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RoleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API Routes - M1: Authentication & RBAC
|--------------------------------------------------------------------------
*/

// No authentication required
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
    Route::post('2fa/login-verify', [AuthController::class, 'loginVerify2FA']);
});

// Authentication required
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('permissions', [AuthController::class, 'permissions']);
        
        // 2FA routes (require password verification)
        Route::middleware(['password.confirm'])->group(function () {
            Route::post('2fa/enable', [AuthController::class, 'enable2FA']);
            Route::post('2fa/verify', [AuthController::class, 'verify2FA']);
            Route::post('2fa/disable', [AuthController::class, 'disable2FA']);
        });
    });

    // Role management - Admin only
    Route::prefix('admin/roles')
        ->middleware(['role:admin,super_admin'])
        ->group(function () {
            Route::post('create', [RoleController::class, 'createRole']);
            Route::get('list', [RoleController::class, 'listRoles']);
            Route::put('update/{id}', [RoleController::class, 'updateRole']);
            Route::delete('delete/{id}', [RoleController::class, 'deleteRole']);
            Route::post('assign/{userId}', [RoleController::class, 'assignRole']);
            Route::post('remove/{userId}', [RoleController::class, 'removeRole']);
            Route::get('permissions/{roleId}', [RoleController::class, 'getRolePermissions']);
            Route::post('permissions/assign', [RoleController::class, 'assignPermission']);
        });
});