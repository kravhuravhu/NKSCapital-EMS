<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ auth()->user()?->first_name ?? 'Portal' }}{{ auth()->user()?->last_name ? ' ' . auth()->user()->last_name : '' }} | {{ config('nks.brand_name') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div id="nks-page-loader" class="nks-page-loader" aria-hidden="true">
        <div class="nks-page-loader__bar"></div>
    </div>

    <x-nks.shell :title="$title ?? null" :breadcrumb="$breadcrumb ?? null">
        {{ $slot ?? '' }}
        @yield('content')
    </x-nks.shell>

    @livewireScripts

    <script>
        document.addEventListener('livewire:navigating', () => {
            document.getElementById('nks-page-loader')?.classList.add('is-active');
        });
        document.addEventListener('livewire:navigated', () => {
            document.getElementById('nks-page-loader')?.classList.remove('is-active');
        });
    </script>
</body>
</html>