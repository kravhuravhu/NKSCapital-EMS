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
     * Actions that always require full logging (success + errors).
     */
    protected array $alwaysLogMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Paths to skip entirely (e.g., health checks, metrics).
     */
    protected array $skipPaths = [
        'up',
        'api/health',
        'api/metrics',
        '_debugbar',
        'horizon',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip noisy / non-business endpoints
        foreach ($this->skipPaths as $skip) {
            if ($request->is($skip) || $request->is($skip . '/*')) {
                return $next($request);
            }
        }

        $requestId = (string) Str::uuid();
        $request->headers->set('X-Request-Id', $requestId);

        $startedAt = microtime(true);
        $shouldLogSuccess = in_array($request->method(), $this->alwaysLogMethods, true);

        try {
            $response = $next($request);
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            // Log successful mutations (and optionally all requests)
            if ($shouldLogSuccess && $response->getStatusCode() < 400) {
                $this->logRequest(
                    request: $request,
                    response: $response,
                    requestId: $requestId,
                    durationMs: $duration,
                    logType: 'success'
                );
            }

            return $response;
        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            // Log ALL exceptions (including validation, auth, server errors)
            $this->logError(
                request: $request,
                exception: $e,
                requestId: $requestId,
                durationMs: $duration
            );

            throw $e;
        }
    }

    /**
     * Log a successful API request.
     */
    protected function logRequest(
        Request $request,
        Response $response,
        string $requestId,
        int $durationMs,
        string $logType
    ): void {
        try {
            $maskedInput = $this->maskSensitive($request->all());

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
                httpStatus: $response->getStatusCode(),
                requestMethod: $request->method(),
                requestPath: '/' . ltrim($request->path(), '/'),
                requestId: $requestId,
                durationMs: $durationMs
            );
        } catch (Throwable $e) {
            // Never let audit logging break the request
            Log::warning('AuditLogMiddleware: failed to log success', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log an error/exception.
     */
    protected function logError(
        Request $request,
        Throwable $exception,
        string $requestId,
        int $durationMs
    ): void {
        try {
            $maskedInput = $this->maskSensitive($request->all());
            $status = $this->resolveHttpStatus($exception);

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
                errorTrace: $exception->getTraceAsString()
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
     * Resolve HTTP status from exception.
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
}