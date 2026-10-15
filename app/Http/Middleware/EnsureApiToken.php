<?php

namespace App\Http\Middleware;

use App\Support\Api;
use App\Support\TokenIssuer;
use Closure;
use Illuminate\Http\Request;

class EnsureApiToken
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && !Api::token()) {
            TokenIssuer::issue($request->user());
        }

        return $next($request);
    }
}