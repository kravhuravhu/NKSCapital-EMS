<?php

return [
    'brand_name'    => env('NKS_BRAND_NAME', 'MyPortal'),
    'brand_sub'     => env('NKS_BRAND_SUB', 'NKS Capital'),
    'brand_url'     => env('NKS_BRAND_URL', 'https://myportal.nkscapital.co.za'),
    'company_name'  => env('NKS_COMPANY_NAME', 'NKS Capital (Pty) Ltd'),
    'support_email' => env('NKS_SUPPORT_EMAIL', 'support@nkscapital.co.za'),

    'footer_links' => [
        ['label' => 'Privacy Policy', 'url' => '/privacy'],
        ['label' => 'Terms of Service', 'url' => '/terms'],
        ['label' => 'Support', 'url' => 'mailto:' . env('NKS_SUPPORT_EMAIL', 'support@nkscapital.co.za')],
    ],

    'notification_poll_seconds' => 60,
    'dashboard_cache_seconds'   => 60,

    'leave_policy' => [
        [
            'title' => 'Annual Leave',
            'desc'  => 'Accrued at 1.5 days per month',
        ],
        [
            'title' => 'Sick Leave',
            'desc'  => "Up to 30 days per 12 months, doctor's note required",
        ],
        [
            'title' => 'Family Leave',
            'desc'  => '3 days per year after 4 months of service',
        ],
        [
            'title' => 'Unpaid Leave',
            'desc'  => 'Subject to management approval',
        ],
    ],
];