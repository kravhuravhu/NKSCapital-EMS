<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Roles that implicitly inherit the access of another role.
     * operational_manager is served by every route that accepts director.
     */
    protected array $inherits = [
        'operational_manager' => ['director'],
    ];

    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user = Auth::user();

        if (empty($roles)) {
            return $next($request);
        }

        $userRoles = $user->getRoleNames()->toArray();

        if (! empty($user->role) && ! in_array($user->role, $userRoles, true)) {
            $userRoles[] = $user->role;
        }

        foreach ($userRoles as $role) {
            foreach ($this->inherits[$role] ?? [] as $inherited) {
                if (! in_array($inherited, $userRoles, true)) {
                    $userRoles[] = $inherited;
                }
            }
        }

        if (! empty(array_intersect($userRoles, $roles))) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized - Insufficient permissions',
        ], 403);
    }
}