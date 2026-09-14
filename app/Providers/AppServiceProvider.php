<?php

namespace App\Providers;

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
        //
    }
}
