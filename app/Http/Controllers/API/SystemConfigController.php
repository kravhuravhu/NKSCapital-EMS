<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SystemConfigController extends Controller
{
    // known config schema,
    // anything outside this list is rejected on update
    protected array $schema = [
        // company
        'company.name' => 'string',
        'company.registration' => 'string',
        'company.vat' => 'string',
        'company.address' => 'string',
        'company.phone' => 'string',
        'company.email' => 'string',
        // leave
        'leave.year_start_month' => 'integer',
        'leave.carryover_max_days' => 'decimal',
        // timesheet
        'timesheet.deadline_day' => 'integer',
        'timesheet.max_regular_hours' => 'decimal',
        'timesheet.max_overtime_hours' => 'decimal',
        // payroll
        'payroll.default_hourly_rate' => 'decimal',
        'payroll.overtime_multiplier' => 'decimal',
        'payroll.ps_billing_multiplier' => 'decimal',
        'payroll.export_day' => 'integer',
        // notifications
        'notifications.email_enabled' => 'boolean',
        'notifications.digest_hour' => 'integer',
        'notifications.poll_seconds' => 'integer',
        // security
        'security.session_timeout_minutes' => 'integer',
        'security.password_reset_ttl_minutes' => 'integer',
        'security.max_login_attempts' => 'integer',
    ];

    /**
     * GET /api/v1/admin/system-config
     * Return every config, keyed by config_key for easy consumption.
     */
    public function index()
    {
        $this->requireAdmin();

        $configs = SystemConfig::with('updatedBy:id,first_name,last_name')
            ->orderBy('config_key')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $configs,
            'schema' => $this->schema,
        ], 200);
    }

    /**
     * PUT /api/v1/admin/system-config/update
     * Bulk update. Accepts an array of {config_key, config_value}. Upserts each.
     */
    public function update(Request $request)
    {
        $user = $this->requireAdmin();

        $validator = Validator::make($request->all(), [
            'configs' => 'required|array|min:1',
            'configs.*.config_key' => 'required|string',
            'configs.*.config_value' => 'nullable',
            'configs.*.description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = [];
        $errors = [];

        foreach ($request->configs as $entry) {
            $key = $entry['config_key'];

            if (!array_key_exists($key, $this->schema)) {
                $errors[$key] = "Unknown config key '{$key}'";
                continue;
            }

            $type = $this->schema[$key];
            $rawValue = $entry['config_value'] ?? null;
            $value = $this->coerce($rawValue, $type);

            if ($value === null && $type !== 'string') {
                $errors[$key] = "Invalid value for '{$key}' — expected {$type}";
                continue;
            }

            $config = SystemConfig::firstOrNew(['config_key' => $key]);
            $old = $config->exists ? $config->config_value : null;

            $config->config_type = $type;
            $config->config_value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            if (!empty($entry['description'])) {
                $config->description = $entry['description'];
            }
            $config->updated_by = $user->id;
            $config->save();

            if ($old !== $config->config_value) {
                AuditService::log(
                    action: 'SYSTEM_CONFIG_UPDATED',
                    tableName: 'system_configs',
                    recordId: $config->id,
                    oldValues: ['config_key' => $key, 'config_value' => $old],
                    newValues: ['config_key' => $key, 'config_value' => $config->config_value],
                    logType: 'success'
                );
            }

            $updated[] = $config->fresh();
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Some configs were rejected',
                'errors' => $errors,
                'data' => $updated,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration updated',
            'data' => $updated,
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/system-config/delete/{key}
     * Remove a config entry. Refuses keys in the schema.
     */
    public function destroy($key)
    {
        $this->requireAdmin();

        if (array_key_exists($key, $this->schema)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Schema configs cannot be deleted — update the value instead.',
            ], 422);
        }

        $config = SystemConfig::where('config_key', $key)->first();

        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Config not found',
            ], 404);
        }

        AuditService::log(
            action: 'SYSTEM_CONFIG_DELETED',
            tableName: 'system_configs',
            recordId: $config->id,
            oldValues: $config->toArray(),
            logType: 'warning'
        );

        $config->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Config deleted',
        ], 200);
    }

    /**
     * POST /api/v1/admin/system-config/verify-password
     * Re-authenticate the admin before sensitive saves.
     */
    public function verifyPassword(Request $request)
    {
        $user = $this->requireAdmin();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!\Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password is incorrect',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verified',
        ], 200);
    }

    // -------- helpers --------

    protected function requireAdmin(): \App\Models\User
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin', 'director'], true)) {
            AuditService::logWarning('SYSTEM_CONFIG_ACCESS_DENIED', 'system_configs', 0, [
                'actor_id' => $user->id,
                'role' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return $user;
    }

    protected function coerce($raw, string $type)
    {
        if ($raw === '' || $raw === null) {
            return $type === 'string' ? '' : null;
        }

        return match ($type) {
            'integer' => is_numeric($raw) ? (int) $raw : null,
            'decimal' => is_numeric($raw) ? (float) $raw : null,
            'boolean' => is_bool($raw) ? $raw : in_array($raw, ['1', 1, 'true', true], true),
            default   => (string) $raw,
        };
    }
}