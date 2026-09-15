<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WorkflowConfig;
use App\Models\User;
use App\Models\PRTimesheet;
use App\Models\PSTimesheet;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WorkflowController extends Controller
{
    /**
     * GET /api/v1/admin/workflow/{type}
     * Get workflow configuration by type
     */
    public function getWorkflow($type)
    {
        if (!in_array($type, ['PS_EXTERNAL', 'PR_INTERNAL'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid workflow type. Must be PS_EXTERNAL or PR_INTERNAL'
            ], 422);
        }

        $workflow = WorkflowConfig::where('workflow_type', $type)->first();

        if (!$workflow) {
            return response()->json([
                'status' => 'error',
                'message' => 'Workflow configuration not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $workflow
        ], 200);
    }

    /**
     * GET /api/v1/admin/workflow/rules
     * Get all workflow rules
     */
    public function getRules()
    {
        $workflows = WorkflowConfig::all();

        return response()->json([
            'status' => 'success',
            'data' => $workflows
        ], 200);
    }

    /**
     * POST /api/v1/admin/workflow/config
     * Create or update workflow configuration
     */
    public function configureWorkflow(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'workflow_type' => 'required|in:PS_EXTERNAL,PR_INTERNAL',
            'approval_chain' => 'nullable|array',
            'notification_settings' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'escalation_rules' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = WorkflowConfig::updateOrCreate(
            ['workflow_type' => $request->workflow_type],
            [
                'approval_chain' => $request->approval_chain,
                'notification_settings' => $request->notification_settings,
                'validation_rules' => $request->validation_rules,
                'escalation_rules' => $request->escalation_rules,
                'is_active' => $request->is_active ?? true,
                'updated_by' => $user->id,
            ]
        );

        AuditService::log(
            action: 'WORKFLOW_CONFIGURED',
            tableName: 'workflow_configs',
            recordId: $workflow->id,
            newValues: $request->all()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow configured successfully',
            'data' => $workflow
        ], 200);
    }

    /**
     * PUT /api/v1/admin/workflow/update/{id}
     * Update workflow configuration
     */
    public function updateWorkflow(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $workflow = WorkflowConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'approval_chain' => 'nullable|array',
            'notification_settings' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'escalation_rules' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $workflow->toArray();
        $workflow->update($request->all());
        $workflow->updated_by = $user->id;
        $workflow->save();

        AuditService::log(
            action: 'WORKFLOW_UPDATED',
            tableName: 'workflow_configs',
            recordId: $workflow->id,
            oldValues: $oldValues,
            newValues: $request->all()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow updated successfully',
            'data' => $workflow
        ], 200);
    }

    /**
     * POST /api/v1/admin/workflow/test/{employeeId}
     * Test workflow routing for a specific employee
     */
    public function testWorkflow($employeeId)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $employee = User::with(['employeeType', 'serviceType', 'client', 'project'])->findOrFail($employeeId);

        if (!$employee->employee_type) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee has no employee type assigned'
            ], 422);
        }

        $workflow = WorkflowConfig::where('workflow_type', 
            $employee->employee_type === 'ps' ? 'PS_EXTERNAL' : 'PR_INTERNAL'
        )->first();

        // Simulate workflow
        $simulation = [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'employee_type' => $employee->employee_type,
                'service_type' => $employee->service_type,
            ],
            'workflow_type' => $workflow ? $workflow->workflow_type : 'Unknown',
            'workflow_steps' => [],
            'assigned_client' => $employee->client ? $employee->client->company_name : null,
            'assigned_project' => $employee->project ? $employee->project->name : null,
        ];

        if ($employee->employee_type === 'ps') {
            $simulation['workflow_steps'] = [
                ['step' => 1, 'name' => 'Employee creates timesheet', 'role' => 'PS Employee'],
                ['step' => 2, 'name' => 'Generate client template', 'role' => 'System'],
                ['step' => 3, 'name' => 'Download and send to client', 'role' => 'PS Employee'],
                ['step' => 4, 'name' => 'External client sign-off', 'role' => 'Client Manager'],
                ['step' => 5, 'name' => 'Upload signed PDF', 'role' => 'PS Employee'],
                ['step' => 6, 'name' => 'Email to client', 'role' => 'System'],
                ['step' => 7, 'name' => 'Store for L2 (no approval)', 'role' => 'Director'],
            ];
        } else {
            $simulation['workflow_steps'] = [
                ['step' => 1, 'name' => 'Employee creates timesheet', 'role' => 'PR Employee'],
                ['step' => 2, 'name' => 'Submit for L1 approval', 'role' => 'PR Employee'],
                ['step' => 3, 'name' => 'L1 approval (Project Manager)', 'role' => 'Project Manager', 'approver' => $employee->project?->projectManager?->full_name],
                ['step' => 4, 'name' => 'L2 approval (Director + 2FA)', 'role' => 'Director'],
                ['step' => 5, 'name' => 'PDF generation', 'role' => 'System'],
                ['step' => 6, 'name' => 'Payroll export', 'role' => 'System'],
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'simulation' => $simulation,
                'workflow_config' => $workflow,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/admin/workflow/assign
     * Assign workflow type to employee (used in M2)
     */
    public function assignWorkflow(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

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

        $employee = User::findOrFail($request->user_id);
        $oldType = $employee->employee_type;

        if ($request->workflow_type === 'PS_EXTERNAL') {
            $employee->employee_type = 'ps';
        } else {
            $employee->employee_type = 'pr';
        }
        $employee->save();

        AuditService::log(
            action: 'WORKFLOW_ASSIGNED',
            tableName: 'users',
            recordId: $employee->id,
            oldValues: ['employee_type' => $oldType],
            newValues: ['employee_type' => $employee->employee_type]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow assigned successfully',
            'data' => [
                'user_id' => $employee->id,
                'employee_type' => $employee->employee_type,
                'workflow_type' => $request->workflow_type,
            ]
        ], 200);
    }
}