<?php

namespace App\Services;

use App\Models\QrLoginChallenge;
use App\Models\User;
use Illuminate\Support\Str;

class QrLoginService
{
    public const TTL_MINUTES = 3;

    /**
     * Resolve an email address or employee number to an active user.
     * Returns null when nothing matches or the account is deactivated.
     */
    public function findActiveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $query = User::query();

        if (str_contains($identifier, '@')) {
            $query->where('email', $identifier);
        } else {
            $query->where('employee_number', $identifier);
        }

        $user = $query->first();

        return ($user && $user->is_active) ? $user : null;
    }

    /**
     * Identity-first challenge. user_id is set here and never rewritten.
     */
    public function start(User $user): QrLoginChallenge
    {
        return QrLoginChallenge::create([
            'challenge' => Str::random(64),
            'user_id' => $user->id,
            'challenge_token' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function initiate(User $user, string $challengeToken): QrLoginChallenge
    {
        QrLoginChallenge::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        return QrLoginChallenge::create([
            'challenge' => Str::random(64),
            'user_id' => $user->id,
            'challenge_token' => $challengeToken,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Approve a challenge. Only the user the challenge was created for may
     * approve it. A mismatch is thrown with code 403 so callers can map it.
     */
    public function approve(string $challenge, User $approver): QrLoginChallenge
    {
        $qr = QrLoginChallenge::where('challenge', $challenge)->first();

        if (!$qr) {
            throw new \RuntimeException('Challenge not found.', 404);
        }

        if ($qr->user_id === null) {
            throw new \RuntimeException('This sign-in request is not valid.', 422);
        }

        if ((int) $qr->user_id !== (int) $approver->id) {
            throw new \RuntimeException('This sign-in request belongs to a different account.', 403);
        }

        if ($qr->status !== 'pending') {
            throw new \RuntimeException('This challenge is no longer pending.', 422);
        }

        if ($qr->isExpired()) {
            $qr->status = 'expired';
            $qr->save();
            throw new \RuntimeException('This challenge has expired.', 422);
        }

        $qr->status = 'approved';
        $qr->approved_by_id = $approver->id;
        $qr->approved_at = now();
        $qr->save();

        return $qr;
    }

    public function expireStale(): int
    {
        return QrLoginChallenge::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
