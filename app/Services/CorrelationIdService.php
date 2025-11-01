<?php

namespace App\Services;
use Illuminate\Support\Facades\Auth;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CorrelationIdService
{
    /**
     * Generate a unique correlation ID
     */
    public static function generate(): string
    {
        return 'req_' . Str::uuid()->toString();
    }

    /**
     * Get correlation ID from request headers or generate new one
     */
    public static function getOrGenerate(Request $request): string
    {
        $correlationId = $request->header('X-Correlation-ID') 
            ?? $request->header('X-Request-ID')
            ?? $request->header('X-Trace-ID')
            ?? self::generate();

        return $correlationId;
    }

    /**
     * Set correlation ID in request
     */
    public static function setInRequest(Request $request, string $correlationId): void
    {
        $request->headers->set('X-Correlation-ID', $correlationId);
    }

    /**
     * Add correlation ID to log context
     */
    public static function addToLogContext(string $correlationId): void
    {
        Log::withContext(['correlation_id' => $correlationId]);
    }

    /**
     * Get correlation ID from current request context
     */
    public static function getCurrent(): ?string
    {
        try {
            $request = app('request');
            if ($request) {
                return self::getOrGenerate($request);
            }
        } catch (\Exception $e) {
            // Request not available in this context (e.g., CLI)
            return null;
        }
        return null;
    }

    /**
     * Create structured log entry with correlation ID
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $correlationId = self::getCurrent();
        if ($correlationId) {
            $context['correlation_id'] = $correlationId;
        }
        
        Log::log($level, $message, $context);
    }

    /**
     * Log request start
     */
    public static function logRequestStart(Request $request, string $correlationId): void
    {
        self::log('info', 'Request started', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log request end
     */
    public static function logRequestEnd(Request $request, int $statusCode, float $duration, string $correlationId): void
    {
        self::log('info', 'Request completed', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log error with correlation ID
     */
    public static function logError(\Throwable $exception, Request $request = null): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($request) {
            $context['request'] = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => Auth::id(),
            ];
        }

        self::log('error', 'Exception occurred', $context);
    }
}
