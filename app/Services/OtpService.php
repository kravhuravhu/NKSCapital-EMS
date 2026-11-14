<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a new OTP for a user. Invalidates any previous unconsumed OTPs
     * for the same user and purpose.
     *
     * @return array{challenge_token: string, expires_at: \Carbon\Carbon}
     */
    public function issue(User $user, string $purpose = 'login'): array
    {
        LoginOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $challengeToken = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        LoginOtp::create([
            'user_id' => $user->id,
            'challenge_token' => $challengeToken,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => self::MAX_ATTEMPTS,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->sendByEmail($user, $code, $purpose);

        return ['challenge_token' => $challengeToken, 'expires_at' => $expiresAt];
    }

    /**
     * Verify a code against a challenge. Returns the user on success.
     * Consumes the challenge on success.
     *
     * @throws \RuntimeException
     */
    public function verify(string $challengeToken, string $code): User
    {
        $otp = LoginOtp::where('challenge_token', $challengeToken)->first();

        if (!$otp) {
            throw new \RuntimeException('Invalid or expired code.');
        }

        if ($otp->isConsumed()) {
            throw new \RuntimeException('This code has already been used.');
        }

        if ($otp->isExpired()) {
            throw new \RuntimeException('This code has expired.');
        }

        if ($otp->isExhausted()) {
            throw new \RuntimeException('Too many attempts. Please request a new code.');
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            throw new \RuntimeException('Incorrect code.');
        }

        $otp->consumed_at = now();
        $otp->save();

        return $otp->user;
    }

    /**
     * Invalidate all unconsumed OTPs for a user (used on disable or logout-everywhere).
     */
    public function invalidateAll(User $user): void
    {
        LoginOtp::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    protected function sendByEmail(User $user, string $code, string $purpose): void
    {
        $subject = $purpose === 'login'
            ? 'NKS Capital: your sign-in code'
            : 'NKS Capital: your verification code';

        try {
            Mail::raw(
                "Your NKS Capital verification code is: {$code}\n\n"
                . 'This code expires in ' . self::TTL_MINUTES . " minutes.\n"
                . "If you did not request this code, change your password immediately.\n",
                function ($mail) use ($user, $subject) {
                    $mail->to($user->email)->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            // Do not fail the login request; the code lives in the DB and the
            // admin can look at logs. But log loudly.
            Log::error('OTP email send failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}