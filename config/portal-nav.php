<?php

return [

    'employee' => [
        ['label' => 'Dashboard',     'route' => 'portal.employee.dashboard', 'icon' => 'fa-solid fa-chart-simple'],
        ['label' => 'Timesheets',    'route' => 'portal.employee.timesheet', 'icon' => 'fa-solid fa-clipboard-list'],
        ['label' => 'Leave Requests','route' => 'portal.employee.leave',     'icon' => 'fa-solid fa-umbrella-beach'],
        ['label' => 'My Projects',   'route' => 'portal.employee.projects',  'icon' => 'fa-solid fa-chart-column'],
        ['label' => 'Loaned Assets', 'route' => 'portal.employee.assets',    'icon' => 'fa-solid fa-laptop'],
        ['label' => 'Documents',     'route' => 'portal.employee.documents', 'icon' => 'fa-solid fa-file-lines'],
        ['label' => 'My Profile',    'route' => 'portal.employee.profile',   'icon' => 'fa-solid fa-user-group'],
        ['label' => 'Notifications', 'route' => 'portal.employee.notifications', 'icon' => 'fa-solid fa-bell'],
    ],

    'manager' => [
        ['label' => 'Dashboard',        'route' => 'portal.manager.dashboard',       'icon' => 'fa-solid fa-chart-simple'],
        ['label' => 'Pending L1',       'route' => 'portal.manager.pending-l1',      'icon' => 'fa-solid fa-check-double',    'badge' => 'l1_pending'],
        ['label' => 'Team Timesheets',  'route' => 'portal.manager.team-timesheets', 'icon' => 'fa-solid fa-clipboard-list'],
        ['label' => 'Leave Requests',   'route' => 'portal.manager.pending-leave',   'icon' => 'fa-solid fa-umbrella-beach',  'badge' => 'leave_pending'],
        ['label' => 'Team Calendar',    'route' => 'portal.manager.team-calendar',   'icon' => 'fa-solid fa-calendar-days'],
        ['label' => 'Team Assets',      'route' => 'portal.manager.team-assets',     'icon' => 'fa-solid fa-laptop'],
        ['label' => 'Meetings',         'route' => 'portal.manager.meetings',        'icon' => 'fa-solid fa-chalkboard-user'],
        ['label' => 'Team Reports',     'route' => 'portal.manager.team-reports',    'icon' => 'fa-solid fa-chart-column'],
        ['label' => 'Projects',         'route' => 'portal.manager.projects',        'icon' => 'fa-solid fa-diagram-project'],
    ],

    'director' => [
        ['label' => 'Dashboard',         'route' => 'portal.director.dashboard',        'icon' => 'fa-solid fa-chart-simple'],
        ['label' => 'Pending L2',        'route' => 'portal.director.pending-l2',       'icon' => 'fa-solid fa-certificate', 'badge' => 'l2_pending'],
        ['label' => 'Audit Log',         'route' => 'portal.director.audit-log',        'icon' => 'fa-solid fa-shield-halved'],
        ['label' => 'Delegations',       'route' => 'portal.director.delegation',       'icon' => 'fa-solid fa-user-cog'],
        ['label' => 'Payroll Export',    'route' => 'portal.director.payroll-export',   'icon' => 'fa-solid fa-file-export'],
        ['label' => 'Company Reports',   'route' => 'portal.director.company-reports',  'icon' => 'fa-solid fa-chart-pie'],
        ['label' => 'Overrides',         'route' => 'portal.director.override-approvals', 'icon' => 'fa-solid fa-scale-balanced'],
        ['label' => 'Leave Config',      'route' => 'portal.director.leave-config',     'icon' => 'fa-solid fa-sliders'],
    ],

    'admin' => [
        ['label' => 'Dashboard',         'route' => 'portal.admin.dashboard',         'icon' => 'fa-solid fa-chart-simple'],
        ['label' => 'Employees',         'route' => 'portal.admin.employees',         'icon' => 'fa-solid fa-users-cog'],
        ['label' => 'Employee Types',    'route' => 'portal.admin.employee-types',    'icon' => 'fa-solid fa-user-tag'],
        ['label' => 'Roles',             'route' => 'portal.admin.roles',             'icon' => 'fa-solid fa-shield-halved'],
        ['label' => 'Clients',           'route' => 'portal.admin.clients',           'icon' => 'fa-solid fa-building'],
        ['label' => 'Client Templates',  'route' => 'portal.admin.client-templates',  'icon' => 'fa-solid fa-file-lines'],
        ['label' => 'Projects',          'route' => 'portal.admin.projects',          'icon' => 'fa-solid fa-diagram-project'],
        ['label' => 'Assets',            'route' => 'portal.admin.assets',            'icon' => 'fa-solid fa-laptop'],
        ['label' => 'Contracts',         'route' => 'portal.admin.contracts',         'icon' => 'fa-solid fa-file-contract'],
        ['label' => 'System Config',     'route' => 'portal.admin.system-config',     'icon' => 'fa-solid fa-sliders'],
        ['label' => 'Queue Monitor',     'route' => 'portal.admin.queue-monitor',     'icon' => 'fa-solid fa-list-check'],
        ['label' => 'Backup / Restore',  'route' => 'portal.admin.backup-restore',    'icon' => 'fa-solid fa-database'],
    ],

    'recruiter' => [
        ['label' => 'Dashboard',            'route' => 'portal.recruiter.dashboard',    'icon' => 'fa-solid fa-chart-simple'],
        ['label' => 'Requisitions',         'route' => 'portal.recruiter.requisitions', 'icon' => 'fa-solid fa-briefcase'],
        ['label' => 'Candidates',           'route' => 'portal.recruiter.candidates',   'icon' => 'fa-solid fa-user-plus'],
        ['label' => 'Pipeline',             'route' => 'portal.recruiter.pipeline',     'icon' => 'fa-solid fa-columns'],
        ['label' => 'Interviews',           'route' => 'portal.recruiter.interviews',   'icon' => 'fa-solid fa-comments'],
        ['label' => 'Offers',               'route' => 'portal.recruiter.offers',       'icon' => 'fa-solid fa-file-signature'],
        ['label' => 'Convert to Employee',  'route' => 'portal.recruiter.convert',      'icon' => 'fa-solid fa-user-check'],
        ['label' => 'Reports',              'route' => 'portal.recruiter.reports',      'icon' => 'fa-solid fa-chart-line'],
    ],
];