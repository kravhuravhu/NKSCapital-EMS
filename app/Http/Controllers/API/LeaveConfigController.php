<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveConfig;
use App\Models\User;
use App\Models\LeaveBalance;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LeaveConfigController extends Controller
{
    /**
     * GET /api/v1/admin/leave-config
     * Get all leave configurations
     */
    public function index()
    {
        $configs = LeaveConfig::all();

        return response()->json([
            'status' => 'success',
            'data' => $configs
        ], 200);
    }

    /**
     * PUT /api/v1/admin/leave-config/update
     * Update leave configuration (bulk or individual)
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Only Super Admin and Director can update
        if (!in_array($user->role, ['super_admin', 'director', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admin and Director can modify leave configuration'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'configs' => 'required|array',
            'configs.*.leave_type' => 'required|in:annual,sick,family,unpaid,study,maternity',
            'configs.*.default_entitlement' => 'nullable|numeric|min:0',
            'configs.*.accrual_rate' => 'nullable|numeric|min:0',
            'configs.*.accrual_frequency' => 'nullable|in:daily,weekly,monthly,yearly,none',
            'configs.*.max_carryover' => 'nullable|numeric|min:0',
            'configs.*.requires_attachment' => 'nullable|boolean',
            'configs.*.min_days_attachment' => 'nullable|integer|min:0',
            'configs.*.auto_approve' => 'nullable|boolean',
            'configs.*.min_service_months' => 'nullable|integer|min:0',
            'configs.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = [];
        foreach ($request->configs as $configData) {
            $config = LeaveConfig::where('leave_type', $configData['leave_type'])->first();

            if (!$config) {
                $config = new LeaveConfig();
                $config->leave_type = $configData['leave_type'];
            }

            $oldValues = $config->toArray();
            $config->fill($configData);
            $config->updated_by = $user->id;
            $config->save();

            AuditService::log(
                action: 'LEAVE_CONFIG_UPDATED',
                tableName: 'leave_configs',
                recordId: $config->id,
                oldValues: $oldValues,
                newValues: $config->toArray()
            );

            $updated[] = $config;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Leave configuration updated successfully',
            'data' => $updated
        ], 200);
    }

    /**
     * POST /api/v1/admin/leave-config/accrual-rate
     * Update accrual rate for a specific leave type
     */
    public function updateAccrualRate(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admin and Director can modify accrual rates'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|in:annual,sick,family',
            'accrual_rate' => 'required|numeric|min:0|max:10',
            'accrual_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'apply_to_existing' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $config = LeaveConfig::where('leave_type', $request->leave_type)->firstOrFail();

        $oldRate = $config->accrual_rate;
        $config->accrual_rate = $request->accrual_rate;
        if ($request->has('accrual_frequency')) {
            $config->accrual_frequency = $request->accrual_frequency;
        }
        $config->updated_by = $user->id;
        $config->save();

        AuditService::log(
            action: 'LEAVE_ACCRUAL_RATE_UPDATED',
            tableName: 'leave_configs',
            recordId: $config->id,
            oldValues: ['accrual_rate' => $oldRate],
            newValues: ['accrual_rate' => $request->accrual_rate]
        );

        // Apply to existing employees if requested
        $affected = 0;
        if ($request->apply_to_existing) {
            $affected = $this->recalculateAllBalances($request->leave_type);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Accrual rate updated successfully',
            'data' => [
                'config' => $config,
                'employees_affected' => $affected,
            ]
        ], 200);
    }

    /**
     * PUT /api/v1/admin/leave-config/entitlements
     * Update default entitlements
     */
    public function updateEntitlements(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admin and Director can modify entitlements'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'entitlements' => 'required|array',
            'entitlements.*.leave_type' => 'required|in:annual,sick,family',
            'entitlements.*.default_entitlement' => 'required|numeric|min:0',
            'entitlements.*.max_carryover' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = [];
        foreach ($request->entitlements as $ent) {
            $config = LeaveConfig::where('leave_type', $ent['leave_type'])->first();
            if ($config) {
                $old = $config->default_entitlement;
                $config->default_entitlement = $ent['default_entitlement'];
                if (isset($ent['max_carryover'])) {
                    $config->max_carryover = $ent['max_carryover'];
                }
                $config->updated_by = $user->id;
                $config->save();

                AuditService::log(
                    action: 'LEAVE_ENTITLEMENT_UPDATED',
                    tableName: 'leave_configs',
                    recordId: $config->id,
                    oldValues: ['default_entitlement' => $old],
                    newValues: ['default_entitlement' => $ent['default_entitlement']]
                );

                $updated[] = $config;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Entitlements updated successfully',
            'data' => $updated
        ], 200);
    }

    /**
     * POST /api/v1/admin/leave-config/reset-employee/{userId}
     * Reset leave balance for a specific employee
     */
    public function resetEmployee(Request $request, $userId)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admin and Director can reset balances'
            ], 403);
        }

        $employee = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'annual_balance' => 'nullable|numeric|min:0',
            'sick_balance' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldAnnual = $employee->leave_balance_annual;
        $oldSick = $employee->leave_balance_sick;

        // Update balances
        if ($request->has('annual_balance')) {
            $employee->leave_balance_annual = $request->annual_balance;
        }
        if ($request->has('sick_balance')) {
            $employee->leave_balance_sick = $request->sick_balance;
        }
        $employee->save();

        // Log to leave_balances
        if ($request->has('annual_balance')) {
            LeaveBalance::create([
                'user_id' => $employee->id,
                'leave_type' => 'annual',
                'balance_before' => $oldAnnual,
                'balance_after' => $request->annual_balance,
                'adjustment_reason' => $request->reason,
                'adjusted_by' => $user->id,
                'adjusted_at' => now(),
            ]);
        }

        if ($request->has('sick_balance')) {
            LeaveBalance::create([
                'user_id' => $employee->id,
                'leave_type' => 'sick',
                'balance_before' => $oldSick,
                'balance_after' => $request->sick_balance,
                'adjustment_reason' => $request->reason,
                'adjusted_by' => $user->id,
                'adjusted_at' => now(),
            ]);
        }

        AuditService::log(
            action: 'LEAVE_BALANCE_RESET',
            tableName: 'users',
            recordId: $employee->id,
            oldValues: ['annual' => $oldAnnual, 'sick' => $oldSick],
            newValues: [
                'annual' => $request->annual_balance,
                'sick' => $request->sick_balance,
                'reason' => $request->reason,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Leave balance reset successfully',
            'data' => [
                'employee' => $employee->full_name,
                'annual_balance' => $employee->leave_balance_annual,
                'sick_balance' => $employee->leave_balance_sick,
            ]
        ], 200);
    }

    /**
     * Recalculate all employee balances based on new accrual rate
     */
    private function recalculateAllBalances(string $leaveType): int
    {
        $users = User::where('is_active', true)
            ->whereIn('employee_type', ['ps', 'pr'])
            ->get();

        $affected = 0;
        foreach ($users as $user) {
            if ($leaveType === 'annual') {
                // Recalculate based on months of service
                $monthsOfService = $user->hire_date 
                    ? $user->hire_date->diffInMonths(now()) 
                    : 0;
                $config = LeaveConfig::where('leave_type', 'annual')->first();
                $newBalance = min($monthsOfService * $config->accrual_rate, $config->default_entitlement);
                $user->leave_balance_annual = $newBalance;
            } elseif ($leaveType === 'sick') {
                $config = LeaveConfig::where('leave_type', 'sick')->first();
                $user->leave_balance_sick = $config->default_entitlement;
            }
            $user->save();
            $affected++;
        }

        return $affected;
    }
}