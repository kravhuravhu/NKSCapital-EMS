<?php

namespace App\Support;

use App\Models\User;

class TokenIssuer
{
    public static function issue(User $user): string
    {
        $user->tokens()->where('name', 'portal')->delete();

        $token = $user->createToken('portal')->plainTextToken;

        Api::setToken($token);

        return $token;
    }
}