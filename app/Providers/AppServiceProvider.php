<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\AuditService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // local: keep generated URLs on the current host + port
        // URL::forceRootUrl() covers route() / redirect() / asset()
        // the config line below covers Storage::url() which reads the disk config directly
        if (!$this->app->runningInConsole() && $this->app->environment('local')) {
            $host = request()->getSchemeAndHttpHost();

            if ($host) {
                URL::forceRootUrl($host);
                config(['filesystems.disks.public.url' => rtrim($host, '/') . '/storage']);
            }
        }

        // rate limiter
        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')) . '|' . $request->ip());
        });

        RateLimiter::for('api-otp', function (Request $request) {
            $token = (string) $request->input('challenge_token');
            return Limit::perMinute(5)->by($token !== '' ? $token : $request->ip());
        });

        RateLimiter::for('api-qr', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api-payroll-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('meeting-checkin', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
