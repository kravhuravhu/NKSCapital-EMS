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
        // URL::forceRootUrl() covers route() / redirect() / asset()
        // the config line below covers Storage::url() which reads the disk config directly
        if (!$this->app->runningInConsole() && $this->app->environment('local')) {
            $host = request()->getSchemeAndHttpHost();

            if ($host) {
                URL::forceRootUrl($host);
                config(['filesystems.disks.public.url' => rtrim($host, '/') . '/storage']);
            }
        }
    }
}
