<?php

namespace App\Support;

class RoleLabels
{
    public static function label(?string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Administrator',
            'director' => 'Director',
            'operational_manager' => 'Operational Manager',
            'manager' => 'Manager',
            'recruiter' => 'Recruiter',
            'employee' => 'Employee',
            default => 'Employee',
        };
    }
}