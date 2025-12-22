<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Request Correlation Service
 * 
 * Provides request correlation tracking for error handling and logging.
 * This service manages correlation IDs across the request lifecycle.
 */
class RequestCorrelationService
{
    private static ?string $correlationId = null;

    /**
     * Get the current correlation ID
     */
    public function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = $this->generateCorrelationId();
        }
        
        return self::$correlationId;
    }

    /**
     * Set the correlation ID for the current request
     */
    public function setCorrelationId(string $correlationId): void
    {
        self::$correlationId = $correlationId;
    }

    /**
     * Generate a new correlation ID
     */
    public function generateCorrelationId(): string
    {
        return 'req_' . Str::random(8);
    }

    /**
     * Get correlation ID from request headers or generate new one
     */
    public function getOrGenerateFromRequest(Request $request): string
    {
        $correlationId = $request->header('X-Request-ID') 
            ?? $request->header('X-Correlation-ID')
            ?? $request->header('X-Trace-ID')
            ?? $this->generateCorrelationId();

        $this->setCorrelationId($correlationId);
        return $correlationId;
    }

    /**
     * Clear the current correlation ID
     */
    public function clearCorrelationId(): void
    {
        self::$correlationId = null;
    }

    /**
     * Check if correlation ID is set
     */
    public function hasCorrelationId(): bool
    {
        return self::$correlationId !== null;
    }
}
