<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Sign in') | {{ config('nks.brand_name') }}</title>

        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <div class="auth-shell">
            <div class="auth-card">
                <div class="auth-brand">
                    <span>NKS</span>
                    <strong>Capital</strong>
                    <small>Employee Management System</small>
                </div>

                @yield('content')
                {{ $slot ?? '' }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>