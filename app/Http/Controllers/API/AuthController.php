<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Models\QrLoginChallenge;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use App\Services\QrLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use App\Models\Passkey;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otp,
        protected QrLoginService $qr,
    ) {}

    /**
     * layer 1 of login. Validates password, issues an OTP, returns the
     * challenge token the client must send with the code. Passwords alone
     * never authenticate.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            AuditService::logWarning('LOGIN_UNKNOWN_EMAIL', 'users', 0, [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Account is deactivated'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            AuditService::logWarning('LOGIN_BAD_PASSWORD', 'users', $user->id, [
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }

        $issued = $this->otp->issue($user, 'login');

        AuditService::log(
            action: 'LOGIN_PASSWORD_OK_OTP_SENT',
            tableName: 'users',
            recordId: $user->id,
            logType: 'info'
        );

        $hasPasskeys = $this->userHasPasskeys($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Password verified. Enter the 6-digit code sent to your email.',
            'data' => [
                'challenge_token' => $issued['challenge_token'],
                'expires_at' => $issued['expires_at']->toIso8601String(),
                'requires_otp' => true,
                'passkey_available' => $hasPasskeys,
                'passkey_count' => $hasPasskeys ? $user->passkeys()->count() : 0,
            ],
        ], 200);
    }

    /**
     * Step 2 of login. Verifies the OTP and issues the Sanctum token.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $this->otp->verify($request->challenge_token, $request->code);
        } catch (\RuntimeException $e) {
            AuditService::logWarning('LOGIN_OTP_FAILED', 'users', 0, [
                'challenge_token' => $request->challenge_token,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        if (!$user->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Account is deactivated'], 401);
        }

        $token = $user->createToken('api-login', ['*'], now()->addDays(7))->plainTextToken;

        $user->last_login_at = now();
        $user->save();

        AuditService::log(
            action: 'LOGIN_SUCCESS_OTP',
            tableName: 'users',
            recordId: $user->id,
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ],
        ], 200);
    }

    /**
     * Resend the OTP for an existing challenge.
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = LoginOtp::where('challenge_token', $request->challenge_token)->first();

        if (!$otp) {
            return response()->json(['status' => 'error', 'message' => 'Invalid challenge'], 404);
        }

        if ($otp->isConsumed()) {
            return response()->json(['status' => 'error', 'message' => 'This challenge has already been used'], 422);
        }

        if ($otp->isExhausted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many attempts. Please sign in again to get a fresh challenge.',
            ], 422);
        }

        $issued = $this->otp->issue($otp->user, 'login');

        return response()->json([
            'status' => 'success',
            'message' => 'A new code has been sent to your email.',
            'data' => [
                'challenge_token' => $issued['challenge_token'],
                'expires_at' => $issued['expires_at']->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Initiate a cross-device QR handoff. The desktop shows the returned
     * qr_url. A phone already signed in as the same user opens the URL and
     * approves, and the desktop polls qrStatus for the token.
     */
    public function qrInitiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = LoginOtp::where('challenge_token', $request->challenge_token)->first();

        if (!$otp || $otp->isConsumed() || $otp->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired challenge'], 422);
        }

        $challenge = $this->qr->initiate($otp->user, $otp->challenge_token);

        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge' => $challenge->challenge,
                'qr_url' => route('auth.qr.landing', ['challenge' => $challenge->challenge]),
                'expires_at' => $challenge->expires_at->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Approve a QR handoff from the phone. Requires auth.
     */
    public function qrApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->qr->approve($request->challenge, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        AuditService::log(
            action: 'QR_LOGIN_APPROVED',
            tableName: 'users',
            recordId: $request->user()->id,
            newValues: ['challenge' => $request->challenge],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Login approved. You can close this page.',
        ], 200);
    }

    /**
     * Poll the status of a QR challenge. Returns the token once approved.
     */
    public function qrStatus(Request $request, string $challenge)
    {
        $qr = QrLoginChallenge::where('challenge', $challenge)->first();

        if (!$qr) {
            return response()->json(['status' => 'error', 'message' => 'Challenge not found'], 404);
        }

        if ($qr->isPending() && $qr->isExpired()) {
            $qr->status = 'expired';
            $qr->save();
        }

        if ($qr->status === 'pending') {
            return response()->json(['status' => 'success', 'data' => ['status' => 'pending']], 200);
        }

        if ($qr->status === 'expired') {
            return response()->json(['status' => 'success', 'data' => ['status' => 'expired']], 200);
        }

        if ($qr->status === 'consumed') {
            return response()->json(['status' => 'success', 'data' => ['status' => 'consumed']], 200);
        }

        // approved
        $user = $qr->user;
        $token = $user->createToken('api-login-qr', ['*'], now()->addDays(7))->plainTextToken;
        $user->last_login_at = now();
        $user->save();

        $qr->status = 'consumed';
        $qr->consumed_at = now();
        $qr->save();

        AuditService::log(
            action: 'LOGIN_SUCCESS_QR',
            tableName: 'users',
            recordId: $user->id,
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'status' => 'approved',
                'user' => $this->userPayload($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['status' => 'success', 'message' => 'Logged out successfully'], 200);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('api-refresh', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => ['token' => $token, 'token_type' => 'Bearer'],
        ], 200);
    }

    public function permissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'roles' => $user->getRoleNames(),
                'permissions_direct' => $user->getDirectPermissions()->pluck('name'),
            ],
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent to your email',
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Unable to send reset link'], 500);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                if (\Schema::hasColumn('users', 'password_changed_at')) {
                    $user->password_changed_at = now();
                }
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['status' => 'success', 'message' => 'Password reset successfully'], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid token or email'], 400);
    }

    /**
     * Generate a TOTP secret and recovery codes. Does NOT enable 2FA yet,
     * verification is required via verify2FA.
     */
    public function enable2FA(Request $request)
    {
        $user = $request->user();

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

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422);
        }

        if ($user->two_factor_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Two factor is already enabled. Disable it first to regenerate.',
            ], 422);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $codes = collect(range(1, 8))->map(fn () => Str::random(10))->all();

        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->two_factor_enabled = false;
        $user->save();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'NKS Capital'),
            $user->email,
            $secret
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'secret' => $secret,
                'qr_url' => $qrUrl,
                'recovery_codes' => $codes,
            ],
        ], 200);
    }

    /**
     * Confirm a TOTP secret. Enables 2FA on success.
     */
    public function verify2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Call enable2FA first to generate a secret.',
            ], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid code'], 422);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        AuditService::log(
            action: 'TWO_FACTOR_ENABLED',
            tableName: 'users',
            recordId: $user->id,
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'message' => 'Two factor enabled'], 200);
    }

    public function disable2FA(Request $request)
    {
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

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditService::log(
            action: 'TWO_FACTOR_DISABLED',
            tableName: 'users',
            recordId: $user->id,
            logType: 'warning'
        );

        return response()->json(['status' => 'success', 'message' => 'Two factor disabled'], 200);
    }

    protected function userHasPasskeys(User $user): bool
    {
        if (!class_exists(Passkey::class)) {
            return false;
        }

        try {
            return $user->passkeys()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function userPayload(User $user): array
    {
        $hasPasskeys = $this->userHasPasskeys($user);

        return [
            'id' => $user->id,
            'employee_number' => $user->employee_number,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_type' => $user->employee_type,
            'service_type' => $user->service_type,
            'department' => $user->department,
            'position' => $user->position,
            'profile_photo_url' => $user->profile_photo_url,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'has_passkeys' => $hasPasskeys,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}