<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') | {{ config('nks.brand_name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="nks-skip-link">Skip to main content</a>

    <div id="nks-page-loader" class="nks-page-loader" aria-hidden="true">
        <div class="nks-page-loader__bar"></div>
    </div>

    <div id="nks-live-region" class="nks-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

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

        // announce server-pushed flash messages to screen readers when they arrive
        window.addEventListener('flash-announced', (e) => {
            const region = document.getElementById('nks-live-region');
            if (region && e.detail && e.detail.message) {
                region.textContent = e.detail.message;
            }
        });
    </script>
</body>
</html>