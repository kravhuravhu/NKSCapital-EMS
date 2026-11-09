<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('nks.app_name', 'Employee Management System') }} - {{ config('nks.brand_sub', 'NKS Capital') }}</title>

    <script>
        (function () {
            const stored = localStorage.getItem('nks-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#auth-main" class="sr-only">Skip to main content</a>

    <main id="auth-main" class="nks-auth-shell">
        <div class="nks-auth-card">
            <div class="nks-auth-brand">
                <span class="nks-logo-mark" aria-hidden="true">NKS</span>
                <span class="nks-brand-name">CAPITAL</span>
            </div>

            {{ $slot }}
        </div>

        <p class="nks-auth-footer">
            &copy; {{ date('Y') }} NKS Capital (Pty) Ltd. All rights reserved.
        </p>
    </main>

    @livewireScripts
</body>
</html>