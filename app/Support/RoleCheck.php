<?php

namespace App\Support;

use App\Models\User;

class RoleCheck
{
    /**
     * Returns true when the user has any of the requested roles, taking into
     * account both the users.role column and Spatie role names.
     *
     * operational_manager and director are treated as equivalent for
     * authorization purposes in both directions.
     */
    public static function has(?User $user, array $roles): bool
    {
        if (!$user) {
            return false;
        }

        if (empty($roles)) {
            return true;
        }

        $userRoles = $user->getRoleNames()->toArray();

        if (!empty($user->role) && !in_array($user->role, $userRoles, true)) {
            $userRoles[] = $user->role;
        }

        if (in_array('operational_manager', $userRoles, true) && !in_array('director', $userRoles, true)) {
            $userRoles[] = 'director';
        }

        if (in_array('director', $userRoles, true) && !in_array('operational_manager', $userRoles, true)) {
            $userRoles[] = 'operational_manager';
        }

        return !empty(array_intersect($userRoles, $roles));
    }

    /**
     * Group used for portal navigation and dashboard routing.
     */
    public static function group(?User $user): string
    {
        if (!$user) {
            return 'employee';
        }

        return RouteRole::groupForUser($user);
    }
}