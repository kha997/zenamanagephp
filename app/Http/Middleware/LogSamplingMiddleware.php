<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log Sampling Middleware
 * 
 * Reduces log noise by sampling successful (2xx) responses.
 * All errors (4xx, 5xx) are always logged.
 * 
 * Sampling rates:
 * - 2xx responses: 10% (1 in 10 requests)
 * - 3xx responses: 50% (1 in 2 requests)
 * - 4xx/5xx responses: 100% (all requests)
 */
class LogSamplingMiddleware
{
    /**
     * Sampling rates by status code range
     */
    private const SAMPLING_RATES = [
        '2xx' => 0.1,  // 10% of 2xx responses
        '3xx' => 0.5,  // 50% of 3xx responses
        '4xx' => 1.0,  // 100% of 4xx responses
        '5xx' => 1.0,  // 100% of 5xx responses
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $statusCode = $response->getStatusCode();
        $statusRange = $this->getStatusRange($statusCode);
        
        // Determine if we should log this request
        $shouldLog = $this->shouldLog($statusRange, $statusCode);

        if (!$shouldLog) {
            // Suppress logging for this request by removing log context
            // Note: This doesn't prevent other middleware from logging,
            // but we can set a flag in request attributes
            $request->attributes->set('log_sampled', true);
        }

        return $response;
    }

    /**
     * Get status code range (2xx, 3xx, 4xx, 5xx)
     */
    private function getStatusRange(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return '2xx';
        }
        if ($statusCode >= 300 && $statusCode < 400) {
            return '3xx';
        }
        if ($statusCode >= 400 && $statusCode < 500) {
            return '4xx';
        }
        if ($statusCode >= 500) {
            return '5xx';
        }
        
        return '2xx'; // Default
    }

    /**
     * Determine if request should be logged based on sampling rate
     */
    private function shouldLog(string $statusRange, int $statusCode): bool
    {
        // Always log errors
        if ($statusRange === '4xx' || $statusRange === '5xx') {
            return true;
        }

        // Check sampling rate
        $samplingRate = self::SAMPLING_RATES[$statusRange] ?? 1.0;
        
        // Generate random number between 0 and 1
        $random = mt_rand() / mt_getrandmax();
        
        return $random <= $samplingRate;
    }

    /**
     * Get sampling rate for a status range
     */
    public static function getSamplingRate(string $statusRange): float
    {
        return self::SAMPLING_RATES[$statusRange] ?? 1.0;
    }
}
