<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\AuditService;

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
        // prevents route() from dropping :8000 during redirects
        if (!$this->app->runningInConsole() && $this->app->environment('local')) {
            $host = request()->getSchemeAndHttpHost();

            if ($host) {
                URL::forceRootUrl($host);
            }
        }
    }
}
