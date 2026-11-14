<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found | {{ config('nks.brand_name', 'NKS Capital') }}</title>
    <script>
        (function () {
            const stored = localStorage.getItem('nks-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/scss/app.scss'])
</head>
<body>
    <main class="nks-error-page">
        <div class="nks-error-card">
            <span class="nks-logo-mark" aria-hidden="true">NKS</span>
            <div class="nks-error-code">404</div>
            <h1>Page not found</h1>
            <p>The page you are looking for does not exist or has been moved.</p>
            <div class="nks-error-actions">
                @auth
                    <a href="{{ route('portal.dashboard') }}" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back to dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        Sign in
                    </a>
                @endauth
            </div>
        </div>
    </main>
</body>
</html>