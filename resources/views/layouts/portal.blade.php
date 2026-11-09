<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal' }} | {{ config('nks.app_name', 'Employee Management System') }} - {{ config('nks.brand_sub', 'NKS Capital') }}</title>
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
    <a href="#main-content" class="sr-only">Skip to main content</a>

    <div id="nks-page-loader" class="nks-page-loader" aria-hidden="true">
        <div class="nks-page-loader-bar"></div>
    </div>

    <x-nks.shell :title="$title ?? null" :breadcrumb="$breadcrumb ?? null">
        {{ $slot ?? '' }}
        @yield('content')
    </x-nks.shell>

    @livewireScripts
</body>
</html>