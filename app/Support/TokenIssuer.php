<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Session;

class TokenIssuer
{
    public static function issue(User $user): string
    {
        $deviceKey = self::deviceKey();

        // Only revoke the token for this device, never other devices.
        $user->tokens()->where('name', $deviceKey)->delete();

        $token = $user->createToken($deviceKey, ['*'], now()->addDays(7))->plainTextToken;

        Api::setToken($token);

        return $token;
    }

    protected static function deviceKey(): string
    {
        $sessionId = Session::getId() ?: 'no-session';
        $ua = substr(hash('sha256', request()->userAgent() ?? 'unknown'), 0, 12);
        return 'portal-device-' . substr(hash('sha256', $sessionId . '|' . $ua), 0, 24);
    }
}