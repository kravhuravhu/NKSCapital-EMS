<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\AuditLog;
use App\Services\AuditService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditLogMiddleware
{
    /**
     * Sensitive keys to mask in request/response payloads.
     */
    protected array $sensitiveKeys = [
        'password', 'password_confirmation', 'current_password',
        'new_password', 'token', 'access_token', 'refresh_token',
        'api_key', 'secret', 'two_factor_secret', '2fa_code',
        'recovery_codes', 'otp', 'pin', 'credit_card', 'cvv', 'ssn',
        'id_number', 'authorization', 'cookie', 'x-api-key',
    ];

    /**
     * HTTP methods that always produce a success log entry.
     */
    protected array $mutatingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Paths to skip entirely (health checks, metrics, debug).
     */
    protected array $skipPaths = [
        'up',
        'api/health',
        'api/metrics',
        '_debugbar',
        'horizon',
        'telescope',
    ];

    /**
     * Requests longer than this (ms) are flagged as slow.
     */
    protected int $slowThresholdMs = 2000;

    public function handle(Request $request, Closure $next): Response
    {
        // Skip noisy / non-business endpoints
        foreach ($this->skipPaths as $skip) {
            if ($request->is($skip) || $request->is($skip . '/*')) {
                return $next($request);
            }
        }

        $requestId = (string) Str::uuid();
        $sessionId = $this->resolveSessionId($request);
        $request->headers->set('X-Request-Id', $requestId);

        $startedAt = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $response = $next($request);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $peakMemoryKb = (int) round((memory_get_peak_usage() - $startMemory) / 1024);

            $this->logRequest(
                request: $request,
                response: $response,
                requestId: $requestId,
                sessionId: $sessionId,
                durationMs: $durationMs,
                peakMemoryKb: $peakMemoryKb
            );

            return $response;

        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $peakMemoryKb = (int) round((memory_get_peak_usage() - $startMemory) / 1024);

            // Log ALL exceptions — validation, auth, 404, 500, everything
            $this->logError(
                request: $request,
                exception: $e,
                requestId: $requestId,
                sessionId: $sessionId,
                durationMs: $durationMs,
                peakMemoryKb: $peakMemoryKb
            );

            throw $e;
        }
    }

    /**
     * Log a successful API request (mutations always; GETs on demand).
     */
    protected function logRequest(
        Request $request,
        Response $response,
        string $requestId,
        ?string $sessionId,
        int $durationMs,
        int $peakMemoryKb
    ): void {
        try {
            $isMutating = in_array($request->method(), $this->mutatingMethods, true);
            $status = $response->getStatusCode();

            // Only log GETs that fail (4xx/5xx) or are explicitly tracked endpoints
            if (!$isMutating && $status < 400 && !$this->shouldLogGet($request)) {
                return;
            }

            $maskedInput = $this->maskSensitive($request->all());
            $content = $response->getContent();
            $size = is_string($content) ? strlen($content) : 0;

            $severity = $this->resolveSeverityFromStatus($status);
            $logType = $this->resolveLogType($status);

            AuditService::log(
                action: $request->method() . ' ' . $request->path(),
                tableName: 'api_request',
                recordId: 0,
                oldValues: null,
                newValues: $maskedInput,
                userId: optional($request->user())->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                logType: $logType,
                httpStatus: $status,
                requestMethod: $request->method(),
                requestPath: '/' . ltrim($request->path(), '/'),
                requestId: $requestId,
                durationMs: $durationMs,
                responseStatus: $status,
                responseSize: $size,
                memoryPeakKb: $peakMemoryKb,
                isSlow: $durationMs > $this->slowThresholdMs,
                severity: $severity,
                sessionId: $sessionId
            );
        } catch (Throwable $e) {
            Log::warning('AuditLogMiddleware: failed to log success', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log an error/exception. Called for ANY uncaught throwable.
     */
    protected function logError(
        Request $request,
        Throwable $exception,
        string $requestId,
        ?string $sessionId,
        int $durationMs,
        int $peakMemoryKb
    ): void {
        try {
            $maskedInput = $this->maskSensitive($request->all());
            $status = $this->resolveHttpStatus($exception);

            $severity = $status >= 500 ? 'critical' : ($status === 404 ? 'warning' : 'error');

            AuditService::log(
                action: $request->method() . ' ' . $request->path() . ' FAILED',
                tableName: 'api_error',
                recordId: 0,
                oldValues: null,
                newValues: [
                    'input' => $maskedInput,
                    'exception_class' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
                userId: optional($request->user())->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                logType: 'error',
                httpStatus: $status,
                requestMethod: $request->method(),
                requestPath: '/' . ltrim($request->path(), '/'),
                requestId: $requestId,
                durationMs: $durationMs,
                errorMessage: $exception->getMessage(),
                errorTrace: $exception->getTraceAsString(),
                responseStatus: $status,
                memoryPeakKb: $peakMemoryKb,
                isSlow: $durationMs > $this->slowThresholdMs,
                severity: $severity,
                sessionId: $sessionId
            );
        } catch (Throwable $e) {
            Log::error('AuditLogMiddleware: failed to log error', [
                'original_error' => $exception->getMessage(),
                'logging_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recursively mask sensitive keys.
     */
    protected function maskSensitive(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, $this->sensitiveKeys, true)) {
                $masked[$key] = '***MASKED***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitive($value);
            } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                $masked[$key] = [
                    'file_name' => $value->getClientOriginalName(),
                    'file_size' => $value->getSize(),
                    'mime_type' => $value->getClientMimeType(),
                ];
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }

    /**
     * Resolve the current session id safely.
     */
    protected function resolveSessionId(Request $request): ?string
    {
        try {
            return $request->hasSession() ? $request->session()->getId() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve HTTP status from a Throwable.
     */
    protected function resolveHttpStatus(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return (int) $e->getStatusCode();
        }

        return match (true) {
            $e instanceof \Illuminate\Validation\ValidationException => 422,
            $e instanceof \Illuminate\Auth\AuthenticationException => 401,
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
            $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 404,
            $e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 405,
            $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    /**
     * Is this a GET that we explicitly track?
     */
    protected function shouldLogGet(Request $request): bool
    {
        // Log sensitive reads like downloads / reports / exports
        $trackedPrefixes = [
            'api/v1/contract/download',
            'api/v1/asset/report',
            'api/v1/timesheet/report',
            'api/v1/admin/audit',
            'api/v1/recruitment/report',
            'api/v1/recruitment/metrics',
            'api/v1/dashboard',
        ];
        foreach ($trackedPrefixes as $prefix) {
            if ($request->is($prefix) || $request->is($prefix . '/*')) {
                return true;
            }
        }
        return false;
    }

    protected function resolveSeverityFromStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => 'critical',
            $status >= 400 => 'warning',
            $status >= 300 => 'info',
            default => 'success',
        };
    }

    protected function resolveLogType(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'success',
        };
    }
}