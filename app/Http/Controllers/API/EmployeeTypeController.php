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