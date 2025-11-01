<?php

namespace App\Http\Middleware;

use App\Services\CorrelationIdService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ObservabilityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $correlationId = CorrelationIdService::getOrGenerate($request);
        
        // Set correlation ID in request
        CorrelationIdService::setInRequest($request, $correlationId);
        
        // Add to log context
        CorrelationIdService::addToLogContext($correlationId);
        
        // Log request start
        CorrelationIdService::logRequestStart($request, $correlationId);
        
        // Add correlation ID to response headers
        $response = $next($request);
        
        // Calculate duration
        $duration = microtime(true) - $startTime;
        
        // Log request end
        CorrelationIdService::logRequestEnd($request, $response->getStatusCode(), $duration, $correlationId);
        
        // Add correlation ID to response headers
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Response-Time', round($duration * 1000, 2) . 'ms');
        
        // Log performance metrics if slow
        if ($duration > 1.0) { // More than 1 second
            CorrelationIdService::log('warning', 'Slow request detected', [
                'duration_ms' => round($duration * 1000, 2),
                'threshold_ms' => 1000,
            ]);
        }
        
        return $response;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        // Log additional metrics after response is sent
        $this->logAdditionalMetrics($request, $response);
    }

    /**
     * Log additional metrics
     */
    protected function logAdditionalMetrics(Request $request, Response $response): void
    {
        $correlationId = CorrelationIdService::getCurrent();
        
        if (!$correlationId) {
            return;
        }

        // Log database query metrics if available
        if (class_exists('\Illuminate\Support\Facades\DB')) {
            $queryCount = \DB::getQueryLog();
            if (!empty($queryCount)) {
                CorrelationIdService::log('debug', 'Database queries executed', [
                    'query_count' => count($queryCount),
                    'queries' => array_map(function($query) {
                        return [
                            'sql' => $query['query'],
                            'bindings' => $query['bindings'],
                            'time' => $query['time']
                        ];
                    }, $queryCount)
                ]);
            }
        }

        // Log cache metrics if available
        if (class_exists('\Illuminate\Support\Facades\Cache')) {
            $cacheHits = cache()->get('observability_cache_hits_' . $correlationId, 0);
            $cacheMisses = cache()->get('observability_cache_misses_' . $correlationId, 0);
            
            if ($cacheHits > 0 || $cacheMisses > 0) {
                CorrelationIdService::log('debug', 'Cache metrics', [
                    'cache_hits' => $cacheHits,
                    'cache_misses' => $cacheMisses,
                    'hit_ratio' => $cacheHits > 0 ? round($cacheHits / ($cacheHits + $cacheMisses) * 100, 2) : 0,
                ]);
                
                // Clean up temporary cache keys
                cache()->forget('observability_cache_hits_' . $correlationId);
                cache()->forget('observability_cache_misses_' . $correlationId);
            }
        }

        // Log response size
        $responseSize = strlen($response->getContent());
        if ($responseSize > 1024 * 1024) { // More than 1MB
            CorrelationIdService::log('warning', 'Large response detected', [
                'response_size_bytes' => $responseSize,
                'response_size_mb' => round($responseSize / 1024 / 1024, 2),
            ]);
        }
    }
}
