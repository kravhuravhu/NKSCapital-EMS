<?php

namespace App\Services;

use App\Models\QrLoginChallenge;
use App\Models\User;
use Illuminate\Support\Str;

class QrLoginService
{
    public const TTL_MINUTES = 3;

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

    public function approve(string $challenge, User $approver): QrLoginChallenge
    {
        $qr = QrLoginChallenge::where('challenge', $challenge)->first();

        if (!$qr) {
            throw new \RuntimeException('Challenge not found.');
        }

        if ($qr->status !== 'pending') {
            throw new \RuntimeException('This challenge is no longer pending.');
        }

        if ($qr->isExpired()) {
            $qr->status = 'expired';
            $qr->save();
            throw new \RuntimeException('This challenge has expired.');
        }

        if ($qr->user_id === null) {
            $qr->user_id = $approver->id;
        } elseif ((int) $qr->user_id !== (int) $approver->id) {
            throw new \RuntimeException('You can only approve your own login.');
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