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
];