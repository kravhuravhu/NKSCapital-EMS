<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmployeeType;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class EmployeeTypeController extends Controller
{
    /**
     * POST /api/v1/employee/type/assign
     * Assign employee type and service type to a user
     */
    public function assignEmployeeType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'employee_type' => 'required|in:ps,pr',
            'service_type' => 'required|in:permanent,contractor,temporary,intern',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        // Verify user has permission to assign types
        if (!Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - Only admins can assign employee types'
            ], 403);
        }

        $user->employee_type = $request->employee_type;
        $user->service_type = $request->service_type;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee type assigned successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'employee_type' => $user->employee_type,
                    'service_type' => $user->service_type,
                ]
            ]
        ], 200);
    }

    /**
     * GET /api/v1/employee/type/{id}
     * Get employee type details for a user
     */
    public function getEmployeeType($id)
    {
        $user = User::with(['employeeType', 'serviceType'])->findOrFail($id);

        // Check permission
        $authUser = Auth::user();
        if ($authUser->id !== $user->id && !in_array($authUser->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to view this profile'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'employee_type' => $user->employee_type,
                'employee_type_details' => $user->employeeType,
                'service_type' => $user->service_type,
                'service_type_details' => $user->serviceType,
                'workflow_type' => $user->employeeType->workflow_type ?? null,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/admin/employee-type/list
     * List all employee types (Admin only)
     */
    public function listEmployeeTypes(Request $request)
    {
        $types = EmployeeType::all();

        return response()->json([
            'status' => 'success',
            'data' => $types
        ], 200);
    }

    /**
     * POST /api/v1/admin/employee-type/create
     * Create a new employee type (Admin only)
     */
    public function createEmployeeType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_code' => 'required|string|unique:employee_types,type_code|in:PS,PR',
            'type_name' => 'required|string',
            'workflow_type' => 'required|in:PS_EXTERNAL,PR_INTERNAL',
            'has_external_approval' => 'nullable|boolean',
            'has_internal_approval' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $type = EmployeeType::create([
            'type_code' => $request->type_code,
            'type_name' => $request->type_name,
            'workflow_type' => $request->workflow_type,
            'has_external_approval' => $request->has_external_approval ?? false,
            'has_internal_approval' => $request->has_internal_approval ?? true,
            'description' => $request->description,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee type created successfully',
            'data' => $type
        ], 201);
    }

    /**
     * PUT /api/v1/admin/employee-type/update/{id}
     * Update an existing employee type's workflow flags.
     */
    public function updateEmployeeType(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - Only admins can update employee types',
            ], 403);
        }

        $type = EmployeeType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type_name' => 'sometimes|string|max:255',
            'workflow_type' => 'sometimes|in:PS_EXTERNAL,PR_INTERNAL',
            'has_external_approval' => 'nullable|boolean',
            'has_internal_approval' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $old = $type->toArray();
        $type->fill($request->only([
            'type_name',
            'workflow_type',
            'has_external_approval',
            'has_internal_approval',
            'description',
        ]));
        $type->save();

        AuditService::log(
            action: 'EMPLOYEE_TYPE_UPDATED',
            tableName: 'employee_types',
            recordId: $type->id,
            oldValues: $old,
            newValues: $type->fresh()->toArray(),
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Employee type updated',
            'data' => $type->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/employee-type/delete/{id}
     * Soft-delete an employee type by marking it inactive (adds is_active to model).
     */
    public function deleteEmployeeType($id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - Only admins can delete employee types',
            ], 403);
        }

        $type = EmployeeType::findOrFail($id);

        // do not delete a type that still has active employees assigned
        $inUse = User::where('employee_type', $type->type_code)
            ->where('is_active', true)
            ->exists();

        if ($inUse) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete an employee type that is still assigned to active employees.',
            ], 422);
        }

        AuditService::log(
            action: 'EMPLOYEE_TYPE_DELETED',
            tableName: 'employee_types',
            recordId: $type->id,
            oldValues: $type->toArray(),
            logType: 'warning'
        );

        $type->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee type deleted',
        ], 200);
    }

    /**
     * GET /api/v1/admin/service-type/list
     * List all service types (Admin only)
     */
    public function listServiceTypes(Request $request)
    {
        $types = ServiceType::all();

        return response()->json([
            'status' => 'success',
            'data' => $types
        ], 200);
    }

    /**
     * POST /api/v1/admin/workflow/assign
     * Assign workflow to employee (Admin only)
     */
    public function assignWorkflow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'workflow_type' => 'required|in:PS_EXTERNAL,PR_INTERNAL',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        // Update employee_type based on workflow
        if ($request->workflow_type === 'PS_EXTERNAL') {
            $user->employee_type = 'ps';
        } else {
            $user->employee_type = 'pr';
        }
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'employee_type' => $user->employee_type,
                'workflow_type' => $request->workflow_type,
            ]
        ], 200);
    }
}