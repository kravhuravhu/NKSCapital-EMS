<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrolled
{
    /**
     * Require that the signed-in user has enrolled a second factor
     * (TOTP app or a passkey) before accessing portal routes.
     *
     * The security page itself is exempt so the user can enroll.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Impersonation is an admin-controlled action that already passed 2FA
        // on the admin side. Do not gate the target user's pages on enrollment.
        if (session()->has('impersonated_by_user_id')) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Public routes and API are not gated by enrollment.
        if ($request->is('api/*') || $request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        // The security page is where the user enrolls, so it is exempt.
        if ($request->routeIs('portal.employee.security')) {
            return $next($request);
        }

        $hasPasskey = method_exists($user, 'passkeys') && $user->passkeys()->exists();
        $hasTotp = (bool) $user->two_factor_enabled && !empty($user->two_factor_secret);

        if ($hasPasskey || $hasTotp) {
            return $next($request);
        }

        if ($request->routeIs('portal.security') || $request->routeIs('portal.employee.security')) {
            return $next($request);
        }

        return redirect()
            ->route('portal.security')
            ->with('error', 'You must enable a second factor (authenticator app or passkey) before continuing.');
    }
}