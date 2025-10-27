<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NetworkMonitoringService
{
    protected array $networkThresholds = [
        'response_time' => 300, // ms
        'timeout' => 30, // seconds
        'error_rate' => 5, // percentage
        'throughput' => 1000, // requests per minute
    ];

    protected array $networkHistory = [];

    public function __construct()
    {
        $this->initializeNetworkHistory();
    }

    /**
     * Initialize network history
     */
    protected function initializeNetworkHistory(): void
    {
        $this->networkHistory = [
            'response_times' => [],
            'error_rates' => [],
            'throughput' => [],
            'timeouts' => [],
            'connection_errors' => [],
        ];
    }

    /**
     * Monitor API endpoint
     */
    public function monitorApiEndpoint(string $url, array $options = []): array
    {
        $startTime = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = Http::timeout($this->networkThresholds['timeout'])
                ->withOptions($options)
                ->get($url);
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            $result = [
                'url' => $url,
                'response_time' => $responseTime,
                'status_code' => $response->status(),
                'success' => $response->successful(),
                'error' => null,
                'timestamp' => now(),
            ];

            // Record response time
            $this->recordResponseTime($url, $responseTime);

            // Record error if not successful
            if (!$response->successful()) {
                $this->recordError($url, "HTTP {$response->status()}", $response->body());
            }

            return $result;

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;

            $error = $e->getMessage();
            $this->recordError($url, $error, $responseTime);

            return [
                'url' => $url,
                'response_time' => $responseTime,
                'status_code' => 0,
                'success' => false,
                'error' => $error,
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Monitor multiple endpoints
     */
    public function monitorMultipleEndpoints(array $urls): array
    {
        $results = [];
        
        foreach ($urls as $url) {
            $results[] = $this->monitorApiEndpoint($url);
        }
        
        return $results;
    }

    /**
     * Record response time
     */
    public function recordResponseTime(string $url, float $responseTime): void
    {
        $this->networkHistory['response_times'][] = [
            'url' => $url,
            'response_time' => $responseTime,
            'timestamp' => now(),
        ];

        // Log if exceeds threshold
        if ($responseTime > $this->networkThresholds['response_time']) {
            Log::warning('Network response time exceeded threshold', [
                'url' => $url,
                'response_time' => $responseTime,
                'threshold' => $this->networkThresholds['response_time'],
            ]);
        }

        // Store in cache for real-time monitoring
        $this->storeNetworkMetric('response_time', $url, $responseTime);
    }

    /**
     * Record error
     */
    public function recordError(string $url, string $error, mixed $context = null): void
    {
        $this->networkHistory['error_rates'][] = [
            'url' => $url,
            'error' => $error,
            'context' => $context,
            'timestamp' => now(),
        ];

        // Log error
        Log::error('Network error occurred', [
            'url' => $url,
            'error' => $error,
            'context' => $context,
        ]);

        // Store in cache for real-time monitoring
        $this->storeNetworkMetric('error_rate', $url, 1);
    }

    /**
     * Record timeout
     */
    public function recordTimeout(string $url, float $timeout): void
    {
        $this->networkHistory['timeouts'][] = [
            'url' => $url,
            'timeout' => $timeout,
            'timestamp' => now(),
        ];

        // Log timeout
        Log::warning('Network timeout occurred', [
            'url' => $url,
            'timeout' => $timeout,
            'threshold' => $this->networkThresholds['timeout'],
        ]);

        // Store in cache for real-time monitoring
        $this->storeNetworkMetric('timeout', $url, $timeout);
    }

    /**
     * Record connection error
     */
    public function recordConnectionError(string $url, string $error): void
    {
        $this->networkHistory['connection_errors'][] = [
            'url' => $url,
            'error' => $error,
            'timestamp' => now(),
        ];

        // Log connection error
        Log::error('Network connection error occurred', [
            'url' => $url,
            'error' => $error,
        ]);

        // Store in cache for real-time monitoring
        $this->storeNetworkMetric('connection_error', $url, 1);
    }

    /**
     * Record throughput
     */
    public function recordThroughput(string $url, int $requests): void
    {
        $this->networkHistory['throughput'][] = [
            'url' => $url,
            'requests' => $requests,
            'timestamp' => now(),
        ];

        // Store in cache for real-time monitoring
        $this->storeNetworkMetric('throughput', $url, $requests);
    }

    /**
     * Store network metric in cache
     */
    protected function storeNetworkMetric(string $type, string $url, mixed $value): void
    {
        $cacheKey = "network_metric_{$type}_{$url}_" . now()->format('Y-m-d-H-i-s');
        Cache::put($cacheKey, $value, 300); // 5 minutes
        
        // Store cache key for retrieval
        $cacheKeys = Cache::get('network_metrics_keys', []);
        $cacheKeys[] = $cacheKey;
        Cache::put('network_metrics_keys', $cacheKeys, 300);
    }

    /**
     * Get network statistics
     */
    public function getNetworkStats(): array
    {
        $stats = [];

        // Response time statistics
        if (!empty($this->networkHistory['response_times'])) {
            $responseTimes = collect($this->networkHistory['response_times'])
                ->pluck('response_time')
                ->toArray();
            
            $stats['response_time'] = [
                'avg' => round(array_sum($responseTimes) / count($responseTimes), 2),
                'min' => min($responseTimes),
                'max' => max($responseTimes),
                'p95' => $this->calculatePercentile($responseTimes, 95),
                'count' => count($responseTimes),
            ];
        }

        // Error rate statistics
        if (!empty($this->networkHistory['error_rates'])) {
            $errorCount = count($this->networkHistory['error_rates']);
            $totalRequests = $errorCount + count($this->networkHistory['response_times']);
            
            $stats['error_rate'] = [
                'error_count' => $errorCount,
                'total_requests' => $totalRequests,
                'error_percentage' => $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0,
            ];
        }

        // Throughput statistics
        if (!empty($this->networkHistory['throughput'])) {
            $throughput = collect($this->networkHistory['throughput'])
                ->pluck('requests')
                ->toArray();
            
            $stats['throughput'] = [
                'avg' => round(array_sum($throughput) / count($throughput), 2),
                'min' => min($throughput),
                'max' => max($throughput),
                'total' => array_sum($throughput),
                'count' => count($throughput),
            ];
        }

        // Timeout statistics
        if (!empty($this->networkHistory['timeouts'])) {
            $timeouts = collect($this->networkHistory['timeouts'])
                ->pluck('timeout')
                ->toArray();
            
            $stats['timeouts'] = [
                'count' => count($timeouts),
                'avg_timeout' => round(array_sum($timeouts) / count($timeouts), 2),
                'max_timeout' => max($timeouts),
            ];
        }

        // Connection error statistics
        if (!empty($this->networkHistory['connection_errors'])) {
            $connectionErrors = collect($this->networkHistory['connection_errors'])
                ->groupBy('error')
                ->map(function ($items) {
                    return count($items);
                })
                ->toArray();
            
            $stats['connection_errors'] = [
                'total_count' => count($this->networkHistory['connection_errors']),
                'error_types' => $connectionErrors,
            ];
        }

        return $stats;
    }

    /**
     * Get network recommendations
     */
    public function getNetworkRecommendations(): array
    {
        $recommendations = [];
        $stats = $this->getNetworkStats();

        // Response time recommendations
        if (isset($stats['response_time']) && $stats['response_time']['avg'] > $this->networkThresholds['response_time']) {
            $recommendations[] = [
                'type' => 'high_response_time',
                'priority' => 'high',
                'message' => 'Network response time is above threshold. Consider optimizing network requests, implementing caching, or using CDN.',
                'current_value' => $stats['response_time']['avg'],
                'threshold' => $this->networkThresholds['response_time'],
            ];
        }

        // Error rate recommendations
        if (isset($stats['error_rate']) && $stats['error_rate']['error_percentage'] > $this->networkThresholds['error_rate']) {
            $recommendations[] = [
                'type' => 'high_error_rate',
                'priority' => 'high',
                'message' => 'Network error rate is above threshold. Consider implementing retry mechanisms, error handling, or monitoring.',
                'current_value' => $stats['error_rate']['error_percentage'],
                'threshold' => $this->networkThresholds['error_rate'],
            ];
        }

        // Throughput recommendations
        if (isset($stats['throughput']) && $stats['throughput']['avg'] < $this->networkThresholds['throughput']) {
            $recommendations[] = [
                'type' => 'low_throughput',
                'priority' => 'medium',
                'message' => 'Network throughput is below threshold. Consider optimizing network requests or implementing connection pooling.',
                'current_value' => $stats['throughput']['avg'],
                'threshold' => $this->networkThresholds['throughput'],
            ];
        }

        // Timeout recommendations
        if (isset($stats['timeouts']) && $stats['timeouts']['count'] > 0) {
            $recommendations[] = [
                'type' => 'timeouts_occurring',
                'priority' => 'high',
                'message' => 'Network timeouts are occurring. Consider increasing timeout values or optimizing network requests.',
                'current_value' => $stats['timeouts']['count'],
                'threshold' => 0,
            ];
        }

        // Connection error recommendations
        if (isset($stats['connection_errors']) && $stats['connection_errors']['total_count'] > 0) {
            $recommendations[] = [
                'type' => 'connection_errors',
                'priority' => 'high',
                'message' => 'Network connection errors are occurring. Consider implementing retry mechanisms or checking network connectivity.',
                'current_value' => $stats['connection_errors']['total_count'],
                'threshold' => 0,
            ];
        }

        return $recommendations;
    }

    /**
     * Get network thresholds
     */
    public function getNetworkThresholds(): array
    {
        return $this->networkThresholds;
    }

    /**
     * Set network thresholds
     */
    public function setNetworkThresholds(array $thresholds): void
    {
        $this->networkThresholds = array_merge($this->networkThresholds, $thresholds);
    }

    /**
     * Calculate percentile
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $weight = $index - $lower;
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * Get real-time network metrics
     */
    public function getRealTimeMetrics(): array
    {
        $metrics = [];
        
        // Get cached metrics from last 5 minutes
        $cacheKeys = Cache::get('network_metrics_keys', []);
        
        foreach ($cacheKeys as $key) {
            $value = Cache::get($key);
            if ($value !== null) {
                $metrics[] = [
                    'key' => $key,
                    'value' => $value,
                    'timestamp' => now(),
                ];
            }
        }
        
        return $metrics;
    }

    /**
     * Clear network history
     */
    public function clearHistory(): void
    {
        $this->initializeNetworkHistory();
        Cache::forget('network_metrics_keys');
    }

    /**
     * Export network data
     */
    public function exportNetworkData(): array
    {
        return [
            'timestamp' => now(),
            'history' => $this->networkHistory,
            'stats' => $this->getNetworkStats(),
            'recommendations' => $this->getNetworkRecommendations(),
            'thresholds' => $this->getNetworkThresholds(),
        ];
    }

    /**
     * Test network connectivity
     */
    public function testConnectivity(string $url): array
    {
        $startTime = microtime(true);
        $result = [
            'url' => $url,
            'success' => false,
            'response_time' => 0,
            'error' => null,
            'timestamp' => now(),
        ];

        try {
            $response = Http::timeout(10)->get($url);
            $endTime = microtime(true);
            
            $result['success'] = $response->successful();
            $result['response_time'] = ($endTime - $startTime) * 1000;
            $result['status_code'] = $response->status();
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = ($endTime - $startTime) * 1000;
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get network health status
     */
    public function getNetworkHealthStatus(): array
    {
        $stats = $this->getNetworkStats();
        $recommendations = $this->getNetworkRecommendations();
        
        $healthScore = 100;
        
        // Deduct points for issues
        if (isset($stats['response_time']) && $stats['response_time']['avg'] > $this->networkThresholds['response_time']) {
            $healthScore -= 20;
        }
        
        if (isset($stats['error_rate']) && $stats['error_rate']['error_percentage'] > $this->networkThresholds['error_rate']) {
            $healthScore -= 30;
        }
        
        if (isset($stats['timeouts']) && $stats['timeouts']['count'] > 0) {
            $healthScore -= 25;
        }
        
        if (isset($stats['connection_errors']) && $stats['connection_errors']['total_count'] > 0) {
            $healthScore -= 25;
        }
        
        $healthScore = max(0, $healthScore);
        
        return [
            'health_score' => $healthScore,
            'status' => $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical'),
            'recommendations_count' => count($recommendations),
            'critical_issues' => count(array_filter($recommendations, fn($r) => $r['priority'] === 'critical')),
            'timestamp' => now(),
        ];
    }
}
