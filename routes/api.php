<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\EmployeeTypeController;
use App\Http\Controllers\API\EmployeeController;

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

    // ========================================
    // M2: Employee Foundation & Configuration
    // ========================================

    // Employee Type & Service Type APIs
    Route::prefix('employee')->group(function () {
        Route::post('type/assign', [EmployeeTypeController::class, 'assignEmployeeType']);
        Route::get('type/{id}', [EmployeeTypeController::class, 'getEmployeeType']);
        Route::post('client/assign', [EmployeeController::class, 'assignClient']);
        Route::post('project/assign', [EmployeeController::class, 'assignProject']);
        Route::post('role/assign', [EmployeeController::class, 'assignRoleToUser'])->middleware('role:admin,super_admin');
    });

    // Admin only - Employee Type Management
    Route::prefix('admin')->middleware(['role:admin,super_admin'])->group(function () {
        // Employee Types
        Route::get('employee-type/list', [EmployeeTypeController::class, 'listEmployeeTypes']);
        Route::post('employee-type/create', [EmployeeTypeController::class, 'createEmployeeType']);
        Route::get('service-type/list', [EmployeeTypeController::class, 'listServiceTypes']);
        Route::post('workflow/assign', [EmployeeTypeController::class, 'assignWorkflow']);
    });

    // Employee Management
    Route::prefix('employee')->middleware(['auth:sanctum'])->group(function () {
        Route::post('create', [EmployeeController::class, 'createEmployee'])->middleware('role:admin,super_admin');
        Route::get('profile/{id}', [EmployeeController::class, 'getProfile']);
        Route::put('update', [EmployeeController::class, 'updateProfile']);
        Route::put('update-full/{id}', [EmployeeController::class, 'updateFullProfile'])->middleware('role:admin,manager,director');
        Route::post('deactivate/{id}', [EmployeeController::class, 'deactivateEmployee'])->middleware('role:admin,super_admin');
        Route::post('upload-photo', [EmployeeController::class, 'uploadPhoto']);
        Route::get('list', [EmployeeController::class, 'listEmployees']);
        Route::get('history/{id}', [EmployeeController::class, 'getHistory']);
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