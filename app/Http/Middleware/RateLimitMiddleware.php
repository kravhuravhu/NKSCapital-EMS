<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $limiter = app(RateLimiter::class);
        $key = $request->user() ? 'user_' . $request->user()->id : 'ip_' . $request->ip();

        if ($limiter->tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $limiter->availableIn($key)
            ], 429);
        }

        $limiter->hit($key, $decayMinutes * 60);

        return $next($request);
    }
}