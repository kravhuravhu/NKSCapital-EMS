<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'NKS Capital EMS') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#auth-main" class="nks-skip-link">Skip to main content</a>

    <div class="auth-shell">
        <main id="auth-main" class="auth-card" role="main">
            <div class="auth-brand">
                <span>NKS</span>
                <strong>CAPITAL</strong>
                <small>Employee Management System</small>
            </div>
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>