<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware để thu thập metrics cho monitoring
 */
class MetricsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return SymfonyResponse
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (!config('metrics.enabled')) {
            return $next($request);
        }

        $startTime = microtime(true);
        
        $response = $next($request);
        
        $this->recordMetrics($request, $response, $startTime);
        
        return $response;
    }

    /**
     * Ghi lại metrics cho request
     *
     * @param Request $request
     * @param SymfonyResponse $response
     * @param float $startTime
     * @return void
     */
    private function recordMetrics(Request $request, SymfonyResponse $response, float $startTime): void
    {
        try {
            $duration = microtime(true) - $startTime;
            $method = $request->getMethod();
            $route = $request->route()?->getName() ?? 'unknown';
            $statusCode = $response->getStatusCode();
            
            // HTTP requests counter
            $this->incrementCounter('http_requests_total', [
                'method' => $method,
                'route' => $route,
                'status_code' => $statusCode,
            ]);
            
            // HTTP duration histogram
            $this->recordHistogram('http_request_duration_seconds', $duration, [
                'method' => $method,
                'route' => $route,
            ]);
            
            // Memory usage gauge
            $this->setGauge('memory_usage_bytes', memory_get_usage(true), [
                'type' => 'allocated',
            ]);
            
            $this->setGauge('memory_usage_bytes', memory_get_peak_usage(true), [
                'type' => 'peak',
            ]);
            
        } catch (\Exception $e) {
            // Log error but don't break the request
            logger()->error('Failed to record metrics', [
                'error' => $e->getMessage(),
                'request' => $request->getPathInfo(),
            ]);
        }
    }

    /**
     * Tăng counter metric
     *
     * @param string $name
     * @param array $labels
     * @return void
     */
    private function incrementCounter(string $name, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);
        Redis::incr($key);
        Redis::expire($key, 3600); // Expire after 1 hour
    }

    /**
     * Ghi lại histogram metric
     *
     * @param string $name
     * @param float $value
     * @param array $labels
     * @return void
     */
    private function recordHistogram(string $name, float $value, array $labels = []): void
    {
        $buckets = config('metrics.prometheus.collectors.http_duration.buckets', []);
        
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $bucketLabels = array_merge($labels, ['le' => (string)$bucket]);
                $key = $this->buildMetricKey($name . '_bucket', $bucketLabels);
                Redis::incr($key);
                Redis::expire($key, 3600);
            }
        }
        
        // Record sum and count
        $sumKey = $this->buildMetricKey($name . '_sum', $labels);
        $countKey = $this->buildMetricKey($name . '_count', $labels);
        
        Redis::incrByFloat($sumKey, $value);
        Redis::incr($countKey);
        Redis::expire($sumKey, 3600);
        Redis::expire($countKey, 3600);
    }

    /**
     * Set gauge metric
     *
     * @param string $name
     * @param float $value
     * @param array $labels
     * @return void
     */
    private function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);
        Redis::set($key, $value);
        Redis::expire($key, 3600);
    }

    /**
     * Xây dựng key cho metric
     *
     * @param string $name
     * @param array $labels
     * @return string
     */
    private function buildMetricKey(string $name, array $labels = []): string
    {
        $namespace = config('metrics.prometheus.namespace', 'zenamanage');
        $labelString = '';
        
        if (!empty($labels)) {
            ksort($labels);
            $labelPairs = [];
            foreach ($labels as $key => $value) {
                $labelPairs[] = $key . '=' . $value;
            }
            $labelString = '{' . implode(',', $labelPairs) . '}';
        }
        
        return "metrics:{$namespace}:{$name}{$labelString}";
    }
}