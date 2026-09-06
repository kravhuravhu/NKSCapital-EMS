<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user requires 2FA
        if ($user->requiresTwoFactor() && !$user->two_factor_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA is required for this action. Please enable 2FA first.',
                'requires_2fa_setup' => true
            ], 403);
        }

        return $next($request);
    }
}