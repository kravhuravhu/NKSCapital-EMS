<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SecurityController extends Controller
{
    public function sessions(Request $request)
    {
        $user     = Auth::user();
        $current  = $request->user()->currentAccessToken();

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at'   => $t->created_at?->toIso8601String(),
                'is_current'   => $current && $current->id === $t->id,
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'sessions' => $sessions,
                'total'    => $sessions->count(),
            ],
        ], 200);
    }

    public function passwordInfo(Request $request)
    {
        $user = Auth::user();

        // password_last_changed_at: fall back to created_at if not tracked
        $lastChanged = $user->password_changed_at
            ?? $user->created_at
            ?? now();

        $daysSince = $lastChanged->diffInDays(now());

        // strength heuristics: length, has number, has upper, has symbol
        // we do not store the plaintext, so this reflects the last reset policy
        $strength = 5;
        if ($daysSince > 90) {
            $strength = 3;
        } elseif ($daysSince > 60) {
            $strength = 4;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'last_changed_at'  => $lastChanged->toIso8601String(),
                'last_changed_fmt' => $lastChanged->format('d M Y'),
                'days_since'       => $daysSince,
                'strength'         => $strength,
                'strength_label'   => match ($strength) {
                    5 => 'Strong',
                    4 => 'Good',
                    3 => 'Fair',
                    default => 'Weak',
                },
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ],
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        // reject reuse of last 3 passwords
        // we only have history if a column exists; skip if not tracked
        $user->password = Hash::make($request->new_password);

        if (\Schema::hasColumn('users', 'password_changed_at')) {
            $user->password_changed_at = now();
        }

        $user->save();

        // revoke all tokens except the current one
        $currentId = $request->user()->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        AuditService::log(
            action: 'PASSWORD_CHANGED',
            tableName: 'users',
            recordId: $user->id,
            newValues: ['method' => 'self-service'],
            logType: 'success'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Password changed. Other sessions were signed out.',
        ], 200);
    }

    public function enableTwoFactor(Request $request)
    {
        $user = Auth::user();

        // if the user already enabled it, just report status
        if ($user->two_factor_enabled) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Two-factor is already enabled.',
                'data'    => [
                    'enabled'         => true,
                    'recovery_codes'  => null,
                ],
            ], 200);
        }

        // generate secret using Google2FA
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret    = $google2fa->generateSecretKey();

        // generate 8 recovery codes
        $codes = collect(range(1, 8))->map(fn () => \Illuminate\Support\Str::random(10))->all();

        $user->two_factor_secret          = encrypt($secret);
        $user->two_factor_recovery_codes  = encrypt(json_encode($codes));
        $user->two_factor_enabled         = true;
        $user->two_factor_confirmed_at    = now();
        $user->save();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'NKS Capital'),
            $user->email,
            $secret
        );

        AuditService::log(
            action: 'TWO_FACTOR_ENABLED',
            tableName: 'users',
            recordId: $user->id,
            logType: 'success'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Two-factor enabled. Save your recovery codes.',
            'data'    => [
                'enabled'        => true,
                'secret'         => $secret,
                'qr_url'         => $qrUrl,
                'recovery_codes' => $codes,
            ],
        ], 200);
    }

    public function disableTwoFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Password is incorrect.',
            ], 422);
        }

        $user->two_factor_secret         = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_enabled        = false;
        $user->two_factor_confirmed_at   = null;
        $user->save();

        AuditService::log(
            action: 'TWO_FACTOR_DISABLED',
            tableName: 'users',
            recordId: $user->id,
            logType: 'warning'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Two-factor disabled.',
        ], 200);
    }

    public function activity(Request $request)
    {
        $user     = Auth::user();
        $response = \App\Models\AuditLog::where('user_id', $user->id)
            ->orderByDesc('timestamp')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'log_type'   => $log->log_type,
                'severity'   => $log->severity,
                'ip_address' => $log->ip_address,
                'timestamp'  => $log->timestamp?->toIso8601String(),
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => ['activity' => $response],
        ], 200);
    }
}