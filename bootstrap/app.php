<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TwoFactorMiddleware;
use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\EnsureApiToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            '2fa' => TwoFactorMiddleware::class,
            'audit' => AuditLogMiddleware::class,
            'rate.limit' => RateLimitMiddleware::class,
            'api.token' => EnsureApiToken::class,
        ]);

        // Audit EVERY API request globally
        $middleware->api(prepend: [
            AuditLogMiddleware::class,
        ]);

        // Ensure API token is issued for every authenticated user
        $middleware->web(append: [
            EnsureApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
