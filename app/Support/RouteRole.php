<?php

namespace App\Support;

use App\Models\User;

class RouteRole
{
    /**
     * Maps a role or Spatie role name to the route group that serves it.
     * operational_manager uses the director group.
     */
    public const GROUP_MAP = [
        'super_admin' => 'admin',
        'admin' => 'admin',
        'director' => 'director',
        'operational_manager' => 'director',
        'manager' => 'manager',
        'recruiter' => 'recruiter',
        'employee' => 'employee',
    ];

    /**
     * Higher values win when a user has several roles.
     * super_admin > admin > director > operational_manager > manager > recruiter > employee
     */
    public const PRIORITY = [
        'super_admin' => 70,
        'admin' => 60,
        'director' => 50,
        'operational_manager' => 45,
        'manager' => 40,
        'recruiter' => 30,
        'employee' => 10,
    ];

    public static function groupFor(?string $role): string
    {
        return self::GROUP_MAP[$role] ?? 'employee';
    }

    public static function groupForUser(?User $user): string
    {
        if (! $user) return 'employee';

        $roles = $user->getRoleNames()->toArray();

        if (! empty($user->role) && ! in_array($user->role, $roles, true)) {
            $roles[] = $user->role;
        }

        $best = null;
        $bestPriority = -1;

        foreach ($roles as $role) {
            $priority = self::PRIORITY[$role] ?? 0;

            if ($priority > $bestPriority) {
                $best = $role;
                $bestPriority = $priority;
            }
        }

        return self::groupFor($best);
    }

    public static function dashboardRouteForUser(?User $user): string
    {
        return 'portal.' . self::groupForUser($user) . '.dashboard';
    }
}