<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * POST /api/v1/admin/roles/create
     */
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'guard_name' => 'nullable|string|in:web,api',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * GET /api/v1/admin/roles/list
     */
    public function listRoles(Request $request)
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'status' => 'success',
            'data' => $roles
        ], 200);
    }

    /**
     * PUT /api/v1/admin/roles/update/{id}
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $id,
            'guard_name' => 'nullable|string|in:web,api',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/roles/delete/{id}
     */
    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting critical roles
        if (in_array($role->name, ['super_admin', 'admin', 'employee'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete system role'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully'
        ], 200);
    }

    /**
     * POST /api/v1/admin/roles/assign/{userId}
     */
    public function assignRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->assignRole($request->role);

        return response()->json([
            'status' => "success",
            'message' => "Role assigned successfully",
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email']),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/admin/roles/remove/{userId}
     */
    public function removeRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->removeRole($request->role);

        return response()->json([
            'status' => 'success',
            'message' => 'Role removed successfully',
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email']),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/admin/roles/permissions/{roleId}
     */
    public function getRolePermissions($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/admin/roles/permissions/assign
     */
    public function assignPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::findByName($request->role);
        $role->syncPermissions($request->permissions);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions assigned successfully',
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/admin/roles/permissions/remove
     */
    public function removePermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name',
            'permission' => 'required|string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::findByName($request->role);
        $role->revokePermissionTo($request->permission);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission removed successfully',
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]
        ], 200);
    }
}