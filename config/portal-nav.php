<?php

return [

    'groups' => [

        [
            'key' => 'overview',
            'label' => 'Overview',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'portal.dashboard',
                    'icon' => 'fa-solid fa-gauge-high',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin', 'recruiter'],
                ],
                [
                    'label' => 'Notifications',
                    'route' => 'portal.employee.notifications',
                    'icon' => 'fa-regular fa-bell',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                    'badge' => 'unread',
                ],
            ],
        ],

        [
            'key' => 'employees',
            'label' => 'Employees',
            'items' => [
                [
                    'label' => 'Employees',
                    'route' => 'portal.admin.employees',
                    'icon' => 'fa-solid fa-users',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'My Leave',
                    'route' => 'portal.employee.leave',
                    'icon' => 'fa-solid fa-umbrella-beach',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Half-day Notices',
                    'route' => 'portal.manager.half-day-notices',
                    'icon' => 'fa-solid fa-clock',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                    'badge' => 'half_day_pending',
                ],
                [
                    'label' => 'My Timesheets',
                    'route' => 'portal.employee.timesheet',
                    'icon' => 'fa-solid fa-clipboard-list',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Timesheet History',
                    'route' => 'portal.employee.timesheet-history',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'roles' => ['employee', 'manager', 'director', 'operational_manager', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Team Timesheets',
                    'route' => 'portal.manager.team-timesheets',
                    'icon' => 'fa-solid fa-table-list',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Pending Leave',
                    'route' => 'portal.manager.pending-leave',
                    'icon' => 'fa-solid fa-calendar-check',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                    'badge' => 'leave_pending',
                ],
                [
                    'label' => 'Pending L1',
                    'route' => 'portal.manager.pending-l1',
                    'icon' => 'fa-solid fa-user-check',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                    'badge' => 'l1_pending',
                ],
                [
                    'label' => 'Pending L2',
                    'route' => 'portal.director.pending-l2',
                    'icon' => 'fa-solid fa-user-shield',
                    'roles' => ['director', 'operational_manager', 'admin', 'super_admin'],
                    'badge' => 'l2_pending',
                ],
                [
                    'label' => 'Delegations',
                    'route' => 'portal.director.delegation',
                    'icon' => 'fa-solid fa-user-cog',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'My Profile',
                    'route' => 'portal.employee.profile',
                    'icon' => 'fa-solid fa-circle-user',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
            ],
        ],

        [
            'key' => 'recruitment',
            'label' => 'Recruitment',
            'items' => [
                [
                    'label' => 'Requisitions',
                    'route' => 'portal.recruiter.requisitions',
                    'icon' => 'fa-solid fa-briefcase',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Candidates',
                    'route' => 'portal.recruiter.candidates',
                    'icon' => 'fa-solid fa-user-plus',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Pipeline',
                    'route' => 'portal.recruiter.pipeline',
                    'icon' => 'fa-solid fa-columns',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Interviews',
                    'route' => 'portal.recruiter.interviews',
                    'icon' => 'fa-solid fa-comments',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Offers',
                    'route' => 'portal.recruiter.offers',
                    'icon' => 'fa-solid fa-file-signature',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Convert to Employee',
                    'route' => 'portal.recruiter.convert',
                    'icon' => 'fa-solid fa-user-check',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
            ],
        ],

        [
            'key' => 'business',
            'label' => 'Business',
            'items' => [
                [
                    'label' => 'Clients',
                    'route' => 'portal.admin.clients',
                    'icon' => 'fa-solid fa-building',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Client Templates',
                    'route' => 'portal.admin.client-templates',
                    'icon' => 'fa-solid fa-file-lines',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Projects',
                    'route' => 'portal.admin.projects',
                    'icon' => 'fa-solid fa-diagram-project',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'My Projects',
                    'route' => 'portal.employee.projects',
                    'icon' => 'fa-solid fa-diagram-project',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Manager Projects',
                    'route' => 'portal.manager.projects',
                    'icon' => 'fa-solid fa-diagram-project',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Assets',
                    'route' => 'portal.admin.assets',
                    'icon' => 'fa-solid fa-laptop',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'My Assets',
                    'route' => 'portal.employee.assets',
                    'icon' => 'fa-solid fa-laptop',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Team Assets',
                    'route' => 'portal.manager.team-assets',
                    'icon' => 'fa-solid fa-laptop-file',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Meetings',
                    'route' => 'portal.manager.meetings',
                    'icon' => 'fa-solid fa-chalkboard-user',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Team Calendar',
                    'route' => 'portal.manager.team-calendar',
                    'icon' => 'fa-solid fa-calendar-days',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
            ],
        ],

        [
            'key' => 'records',
            'label' => 'Records',
            'items' => [
                [
                    'label' => 'My Documents',
                    'route' => 'portal.employee.documents',
                    'icon' => 'fa-solid fa-folder-open',
                    'roles' => ['employee', 'manager', 'director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Contracts',
                    'route' => 'portal.admin.contracts',
                    'icon' => 'fa-solid fa-file-contract',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Team Reports',
                    'route' => 'portal.manager.team-reports',
                    'icon' => 'fa-solid fa-chart-line',
                    'roles' => ['manager', 'director', 'admin', 'super_admin'],
                ],
            ],
        ],

        [
            'key' => 'administration',
            'label' => 'Administration',
            'items' => [
                [
                    'label' => 'Leave Configuration',
                    'route' => 'portal.director.leave-config',
                    'icon' => 'fa-solid fa-sliders',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Leave Reasons',
                    'route' => 'portal.admin.leave-reasons',
                    'icon' => 'fa-solid fa-list',
                    'roles' => ['admin', 'super_admin', 'director'],
                ],
                [
                    'label' => 'Company Reports',
                    'route' => 'portal.director.company-reports',
                    'icon' => 'fa-solid fa-chart-pie',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Payroll Sync',
                    'route' => 'portal.director.payroll-export',
                    'icon' => 'fa-solid fa-file-export',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Audit Logs',
                    'route' => 'portal.director.audit-log',
                    'icon' => 'fa-solid fa-shield-halved',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Override Approvals',
                    'route' => 'portal.director.override-approvals',
                    'icon' => 'fa-solid fa-scale-balanced',
                    'roles' => ['director', 'admin', 'super_admin'],
                ],
                [
                    'label' => 'Employee Types',
                    'route' => 'portal.admin.employee-types',
                    'icon' => 'fa-solid fa-user-tag',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Roles',
                    'route' => 'portal.admin.roles',
                    'icon' => 'fa-solid fa-key',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'System Configuration',
                    'route' => 'portal.admin.system-config',
                    'icon' => 'fa-solid fa-gear',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Queue Monitor',
                    'route' => 'portal.admin.queue-monitor',
                    'icon' => 'fa-solid fa-list-check',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Backup and Restore',
                    'route' => 'portal.admin.backup-restore',
                    'icon' => 'fa-solid fa-database',
                    'roles' => ['admin', 'super_admin'],
                ],
                [
                    'label' => 'Recruiter Reports',
                    'route' => 'portal.recruiter.reports',
                    'icon' => 'fa-solid fa-chart-column',
                    'roles' => ['recruiter', 'manager', 'director', 'admin', 'super_admin'],
                ],
            ],
        ],
    ],
];