<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Support\RoleCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function search(Request $request)
    {
        $this->requireAdmin();

        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $q = '%' . $request->q . '%';

        $users = User::where(function ($w) use ($q) {
                $w->where('first_name', 'like', $q)
                  ->orWhere('last_name', 'like', $q)
                  ->orWhere('email', 'like', $q)
                  ->orWhere('employee_number', 'like', $q);
            })
            ->orderBy('last_name')
            ->limit(20)
            ->get([
                'id', 'employee_number', 'first_name', 'last_name', 'email',
                'role', 'department', 'position', 'is_active', 'last_login_at',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => ['users' => $users],
        ], 200);
    }

    public function userDetail($id)
    {
        $this->requireAdmin();

        $user = User::with([
            'manager:id,first_name,last_name,employee_number',
            'client:id,company_name',
            'project:id,name,project_code',
        ])->findOrFail($id);

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'created_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
            ]);

        $auditSlice = AuditLog::where('user_id', $user->id)
            ->orderByDesc('timestamp')
            ->limit(10)
            ->get(['id', 'timestamp', 'action', 'log_type', 'ip_address', 'table_name', 'record_id']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'employee_number' => $user->employee_number,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'company_email' => $user->company_email,
                    'pending_company_email' => (bool) $user->pending_company_email,
                    'role' => $user->role,
                    'department' => $user->department,
                    'position' => $user->position,
                    'is_active' => (bool) $user->is_active,
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                    'two_factor_enabled' => (bool) $user->two_factor_enabled,
                    'manager_id' => $user->manager_id,
                    'manager_name' => $user->manager?->full_name,
                    'client_id' => $user->client_id,
                    'client_name' => $user->client?->company_name,
                    'project_id' => $user->project_id,
                    'project_name' => $user->project?->name,
                    'onboarding_progress' => app(OnboardingService::class)->progress($user),
                ],
                'sessions' => $sessions,
                'audit_slice' => $auditSlice,
            ],
        ], 200);
    }

    public function forceLogout(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $this->checkPassword($request, $actor);

        $user = User::findOrFail($id);
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        AuditService::log(
            action: 'SUPPORT_FORCE_LOGOUT',
            tableName: 'users',
            recordId: $user->id,
            newValues: ['actor_id' => $actor->id, 'deleted_sessions' => $count],
            logType: 'warning',
            severity: 'warning'
        );

        return response()->json([
            'status' => 'success',
            'message' => "Terminated {$count} session(s).",
            'data' => ['deleted_sessions' => $count],
        ], 200);
    }

    public function reset2fa(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $this->checkPassword($request, $actor);

        $user = User::findOrFail($id);

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditService::log(
            action: 'SUPPORT_2FA_RESET',
            tableName: 'users',
            recordId: $user->id,
            newValues: ['actor_id' => $actor->id],
            logType: 'warning',
            severity: 'warning'
        );

        return response()->json(['status' => 'success', 'message' => '2FA reset'], 200);
    }

    public function updateEmployeeNumber(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $this->checkPassword($request, $actor);

        $validator = Validator::make($request->all(), [
            'employee_number' => 'required|string|max:255|unique:users,employee_number,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $old = $user->employee_number;
        $user->employee_number = $request->employee_number;
        $user->save();

        AuditService::log(
            action: 'SUPPORT_EMPLOYEE_NUMBER_CHANGED',
            tableName: 'users',
            recordId: $user->id,
            oldValues: ['employee_number' => $old],
            newValues: ['employee_number' => $user->employee_number, 'actor_id' => $actor->id],
            logType: 'warning',
            severity: 'warning'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Employee number updated',
            'data' => ['employee_number' => $user->employee_number],
        ], 200);
    }

    public function relink(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $this->checkPassword($request, $actor);

        $validator = Validator::make($request->all(), [
            'manager_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $old = $user->only(['manager_id', 'client_id', 'project_id']);

        $user->manager_id = $request->manager_id;
        $user->client_id = $request->client_id;
        $user->project_id = $request->project_id;
        $user->save();

        AuditService::log(
            action: 'SUPPORT_RELINK',
            tableName: 'users',
            recordId: $user->id,
            oldValues: $old,
            newValues: array_merge($user->only(['manager_id', 'client_id', 'project_id']), ['actor_id' => $actor->id]),
            logType: 'warning',
            severity: 'warning'
        );

        return response()->json(['status' => 'success', 'message' => 'Relationships updated'], 200);
    }

    public function impersonate(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $target = User::findOrFail($id);

        if (RoleCheck::has($target, ['admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Cannot impersonate another administrator.'], 403);
        }

        if ((int) $actor->id === (int) $target->id) {
            return response()->json(['status' => 'error', 'message' => 'You cannot impersonate yourself.'], 422);
        }

        $this->checkPassword($request, $actor);

        $token = $target->createToken(
            'impersonation-' . now()->format('YmdHis'),
            ['*'],
            now()->addHours(2)
        )->plainTextToken;

        AuditService::log(
            action: 'SUPPORT_IMPERSONATE_STARTED',
            tableName: 'users',
            recordId: $target->id,
            newValues: ['actor_id' => $actor->id, 'target_id' => $target->id],
            logType: 'warning',
            severity: 'warning'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'target_user' => [
                    'id' => $target->id,
                    'full_name' => $target->full_name,
                    'email' => $target->email,
                    'role' => $target->role,
                ],
                'impersonation_token' => $token,
                'redirect_url' => route(\App\Support\RouteRole::dashboardRouteForUser($target)),
            ],
        ], 200);
    }

    protected function requireAdmin(): User
    {
        $user = Auth::user();

        if (! RoleCheck::has($user, ['admin', 'super_admin'])) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return $user;
    }

    protected function checkPassword(Request $request, User $user): void
    {
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            AuditService::logWarning('SUPPORT_PASSWORD_FAILED', 'users', $user->id, [
                'path' => $request->path(),
                'actor_id' => $user->id,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422));
        }
    }

    public function assignCompanyEmail(Request $request, $id)
    {
        $actor = $this->requireAdmin();
        $this->checkPassword($request, $actor);

        $validator = Validator::make($request->all(), [
            'company_email' => 'required|email|max:255|unique:users,email,' . $id,
            'update_login_email' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $old = [
            'company_email' => $user->company_email,
            'email' => $user->email,
            'pending_company_email' => (bool) $user->pending_company_email,
        ];

        $user->company_email = $request->company_email;
        $user->pending_company_email = false;

        if ($request->boolean('update_login_email')) {
            $user->email = $request->company_email;
        }

        $newTempPassword = null;
        if ($request->boolean('update_login_email')) {
            $newTempPassword = \Illuminate\Support\Str::random(12);
            $user->password = Hash::make($newTempPassword);
        }

        $user->save();

        AuditService::log(
            action: 'SUPPORT_COMPANY_EMAIL_ASSIGNED',
            tableName: 'users',
            recordId: $user->id,
            oldValues: $old,
            newValues: [
                'company_email' => $user->company_email,
                'login_email_updated' => $request->boolean('update_login_email'),
                'actor_id' => $actor->id,
            ],
            logType: 'warning',
            severity: 'warning'
        );

        if ($newTempPassword) {
            try {
                Mail::raw(
                    "Welcome to " . config('app.name') . "!\n\n"
                    . "Your company email is: {$user->email}\n"
                    . "Your login password: {$newTempPassword}\n\n"
                    . "Please sign in at " . url('/login') . " and change your password on first login.\n",
                    function ($mail) use ($user) {
                        $mail->to($user->email)->subject('Your NKS Capital credentials');
                    }
                );
            } catch (\Throwable $e) {
                AuditService::log(
                    action: 'CREDENTIALS_EMAIL_FAILED',
                    tableName: 'users',
                    recordId: $user->id,
                    newValues: ['email' => $user->email, 'error' => $e->getMessage()],
                    logType: 'error',
                    severity: 'error'
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company email assigned',
            'data' => [
                'company_email' => $user->company_email,
                'login_email' => $user->email,
                'temporary_password' => $newTempPassword,
            ],
        ], 200);
    }
}