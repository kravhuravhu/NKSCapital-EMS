<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Log API requests
        if ($request->user()) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'action' => $request->method() . ' ' . $request->path(),
                'table_name' => 'api_request',
                'record_id' => 0,
                'old_values' => null,
                'new_values' => json_encode($request->all()),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);
        }

        return $response;
    }
}