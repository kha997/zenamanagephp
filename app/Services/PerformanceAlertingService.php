<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * PerformanceAlertingService
 * 
 * Real-time performance monitoring and alerting system
 * with threshold monitoring, automated suggestions, and regression detection.
 * 
 * Features:
 * - Real-time performance threshold monitoring
 * - Automated performance optimization suggestions
 * - Performance regression detection
 * - Historical data analysis
 * - Multi-channel alerting (email, notifications, logs)
 */
class PerformanceAlertingService
{
    private const ALERT_CACHE_TTL = 300; // 5 minutes
    private const METRICS_CACHE_TTL = 60; // 1 minute
    private const ALERT_COOLDOWN = 900; // 15 minutes
    
    // Performance thresholds
    private const THRESHOLDS = [
        'api_response_time' => 300, // 300ms
        'database_query_time' => 100, // 100ms
        'memory_usage' => 80, // 80%
        'cpu_usage' => 85, // 85%
        'error_rate' => 5, // 5%
        'request_count' => 1000, // 1000 requests per minute
    ];
    
    /**
     * Check performance thresholds and send alerts if needed
     */
    public function checkPerformanceThresholds(): void
    {
        try {
            $metrics = $this->getCurrentMetrics();
            $alerts = [];
            
            foreach ($metrics as $metric => $value) {
                if (isset(self::THRESHOLDS[$metric])) {
                    $threshold = self::THRESHOLDS[$metric];
                    
                    if ($this->isThresholdExceeded($metric, $value, $threshold)) {
                        $alert = $this->createPerformanceAlert($metric, $value, $threshold);
                        $alerts[] = $alert;
                        
                        $this->sendPerformanceAlert($alert);
                    }
                }
            }
            
            if (!empty($alerts)) {
                Log::warning('Performance thresholds exceeded', [
                    'alerts_count' => count($alerts),
                    'alerts' => $alerts
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Performance threshold check error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send performance alert
     */
    public function sendPerformanceAlert(array $alert): void
    {
        try {
            // Check alert cooldown
            if ($this->isAlertInCooldown($alert['metric'])) {
                return;
            }
            
            // Send email alert
            $this->sendEmailAlert($alert);
            
            // Send notification
            $this->sendNotificationAlert($alert);
            
            // Log alert
            Log::critical('Performance alert sent', $alert);
            
            // Set cooldown
            $this->setAlertCooldown($alert['metric']);
            
        } catch (\Exception $e) {
            Log::error('Performance alert sending error', [
                'alert' => $alert,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        try {
            return Cache::get('performance_alerts', []);
        } catch (\Exception $e) {
            Log::error('Get performance alerts error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations(): array
    {
        try {
            $metrics = $this->getCurrentMetrics();
            $recommendations = [];
            
            // API Response Time recommendations
            if ($metrics['api_response_time'] > 200) {
                $recommendations[] = [
                    'type' => 'api_optimization',
                    'priority' => 'high',
                    'title' => 'Optimize API Response Time',
                    'description' => 'API response time is above 200ms. Consider implementing caching, database query optimization, or API response compression.',
                    'actions' => [
                        'Implement Redis caching for frequently accessed data',
                        'Optimize database queries and add indexes',
                        'Enable API response compression',
                        'Consider implementing API pagination'
                    ]
                ];
            }
            
            // Database Query Time recommendations
            if ($metrics['database_query_time'] > 50) {
                $recommendations[] = [
                    'type' => 'database_optimization',
                    'priority' => 'high',
                    'title' => 'Optimize Database Queries',
                    'description' => 'Database query time is above 50ms. Consider adding indexes, optimizing queries, or implementing query caching.',
                    'actions' => [
                        'Add database indexes for frequently queried columns',
                        'Optimize complex queries and reduce N+1 problems',
                        'Implement query result caching',
                        'Consider database connection pooling'
                    ]
                ];
            }
            
            // Memory Usage recommendations
            if ($metrics['memory_usage'] > 70) {
                $recommendations[] = [
                    'type' => 'memory_optimization',
                    'priority' => 'medium',
                    'title' => 'Optimize Memory Usage',
                    'description' => 'Memory usage is above 70%. Consider optimizing data structures, implementing memory-efficient algorithms, or increasing server memory.',
                    'actions' => [
                        'Review and optimize data structures',
                        'Implement memory-efficient algorithms',
                        'Consider increasing server memory',
                        'Monitor for memory leaks'
                    ]
                ];
            }
            
            // CPU Usage recommendations
            if ($metrics['cpu_usage'] > 75) {
                $recommendations[] = [
                    'type' => 'cpu_optimization',
                    'priority' => 'high',
                    'title' => 'Optimize CPU Usage',
                    'description' => 'CPU usage is above 75%. Consider optimizing algorithms, implementing caching, or scaling horizontally.',
                    'actions' => [
                        'Optimize CPU-intensive algorithms',
                        'Implement caching to reduce computation',
                        'Consider horizontal scaling',
                        'Monitor for infinite loops or inefficient code'
                    ]
                ];
            }
            
            // Error Rate recommendations
            if ($metrics['error_rate'] > 2) {
                $recommendations[] = [
                    'type' => 'error_reduction',
                    'priority' => 'critical',
                    'title' => 'Reduce Error Rate',
                    'description' => 'Error rate is above 2%. Investigate and fix the root causes of errors.',
                    'actions' => [
                        'Review error logs and identify patterns',
                        'Implement better error handling',
                        'Add input validation and sanitization',
                        'Consider implementing circuit breakers'
                    ]
                ];
            }
            
            return $recommendations;
            
        } catch (\Exception $e) {
            Log::error('Get performance recommendations error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Detect performance regressions
     */
    public function detectPerformanceRegressions(): array
    {
        try {
            $currentMetrics = $this->getCurrentMetrics();
            $historicalMetrics = $this->getHistoricalMetrics(7); // Last 7 days
            
            $regressions = [];
            
            foreach ($currentMetrics as $metric => $currentValue) {
                if (isset($historicalMetrics[$metric])) {
                    $historicalAverage = array_sum($historicalMetrics[$metric]) / count($historicalMetrics[$metric]);
                    $regressionThreshold = $historicalAverage * 1.2; // 20% increase
                    
                    if ($currentValue > $regressionThreshold) {
                        $regressions[] = [
                            'metric' => $metric,
                            'current_value' => $currentValue,
                            'historical_average' => round($historicalAverage, 2),
                            'regression_percentage' => round((($currentValue - $historicalAverage) / $historicalAverage) * 100, 2),
                            'severity' => $this->calculateRegressionSeverity($currentValue, $historicalAverage)
                        ];
                    }
                }
            }
            
            return $regressions;
            
        } catch (\Exception $e) {
            Log::error('Performance regression detection error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get current performance metrics
     */
    private function getCurrentMetrics(): array
    {
        try {
            $cacheKey = 'current_performance_metrics';
            $cachedMetrics = Cache::get($cacheKey);
            
            if ($cachedMetrics !== null) {
                return $cachedMetrics;
            }
            
            $metrics = [
                'api_response_time' => $this->getAverageApiResponseTime(),
                'database_query_time' => $this->getAverageDatabaseQueryTime(),
                'memory_usage' => $this->getMemoryUsage(),
                'cpu_usage' => $this->getCpuUsage(),
                'error_rate' => $this->getErrorRate(),
                'request_count' => $this->getRequestCount(),
            ];
            
            Cache::put($cacheKey, $metrics, self::METRICS_CACHE_TTL);
            
            return $metrics;
            
        } catch (\Exception $e) {
            Log::error('Get current metrics error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get historical metrics
     */
    private function getHistoricalMetrics(int $days): array
    {
        try {
            $cacheKey = "historical_metrics_{$days}_days";
            $cachedMetrics = Cache::get($cacheKey);
            
            if ($cachedMetrics !== null) {
                return $cachedMetrics;
            }
            
            // This would typically query a metrics database
            // For now, return mock data
            $metrics = [];
            for ($i = 0; $i < $days; $i++) {
                $date = now()->subDays($i)->toDateString();
                $metrics['api_response_time'][] = rand(100, 400);
                $metrics['database_query_time'][] = rand(20, 120);
                $metrics['memory_usage'][] = rand(40, 90);
                $metrics['cpu_usage'][] = rand(30, 95);
                $metrics['error_rate'][] = rand(0, 10);
                $metrics['request_count'][] = rand(500, 2000);
            }
            
            Cache::put($cacheKey, $metrics, 3600); // 1 hour
            
            return $metrics;
            
        } catch (\Exception $e) {
            Log::error('Get historical metrics error', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Check if threshold is exceeded
     */
    private function isThresholdExceeded(string $metric, float $value, float $threshold): bool
    {
        return $value > $threshold;
    }
    
    /**
     * Create performance alert
     */
    private function createPerformanceAlert(string $metric, float $value, float $threshold): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'metric' => $metric,
            'value' => $value,
            'threshold' => $threshold,
            'excess_percentage' => round((($value - $threshold) / $threshold) * 100, 2),
            'severity' => $this->calculateAlertSeverity($value, $threshold),
            'message' => $this->generateAlertMessage($metric, $value, $threshold)
        ];
    }
    
    /**
     * Calculate alert severity
     */
    private function calculateAlertSeverity(float $value, float $threshold): string
    {
        $excessPercentage = (($value - $threshold) / $threshold) * 100;
        
        if ($excessPercentage > 100) {
            return 'critical';
        } elseif ($excessPercentage > 50) {
            return 'high';
        } elseif ($excessPercentage > 20) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate regression severity
     */
    private function calculateRegressionSeverity(float $current, float $historical): string
    {
        $regressionPercentage = (($current - $historical) / $historical) * 100;
        
        if ($regressionPercentage > 100) {
            return 'critical';
        } elseif ($regressionPercentage > 50) {
            return 'high';
        } elseif ($regressionPercentage > 20) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Generate alert message
     */
    private function generateAlertMessage(string $metric, float $value, float $threshold): string
    {
        $metricNames = [
            'api_response_time' => 'API Response Time',
            'database_query_time' => 'Database Query Time',
            'memory_usage' => 'Memory Usage',
            'cpu_usage' => 'CPU Usage',
            'error_rate' => 'Error Rate',
            'request_count' => 'Request Count'
        ];
        
        $metricName = $metricNames[$metric] ?? $metric;
        $excessPercentage = round((($value - $threshold) / $threshold) * 100, 2);
        
        return "{$metricName} exceeded threshold: {$value} (threshold: {$threshold}, excess: {$excessPercentage}%)";
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert): void
    {
        try {
            // This would typically send an email to administrators
            Log::info('Email alert would be sent', $alert);
        } catch (\Exception $e) {
            Log::error('Email alert sending error', [
                'alert' => $alert,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send notification alert
     */
    private function sendNotificationAlert(array $alert): void
    {
        try {
            // This would typically send a notification to administrators
            Log::info('Notification alert would be sent', $alert);
        } catch (\Exception $e) {
            Log::error('Notification alert sending error', [
                'alert' => $alert,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if alert is in cooldown
     */
    private function isAlertInCooldown(string $metric): bool
    {
        $cooldownKey = "alert_cooldown_{$metric}";
        return Cache::has($cooldownKey);
    }
    
    /**
     * Set alert cooldown
     */
    private function setAlertCooldown(string $metric): void
    {
        $cooldownKey = "alert_cooldown_{$metric}";
        Cache::put($cooldownKey, true, self::ALERT_COOLDOWN);
    }
    
    /**
     * Get average API response time
     */
    private function getAverageApiResponseTime(): float
    {
        // This would typically query performance monitoring data
        return rand(50, 500); // Mock data
    }
    
    /**
     * Get average database query time
     */
    private function getAverageDatabaseQueryTime(): float
    {
        // This would typically query performance monitoring data
        return rand(10, 200); // Mock data
    }
    
    /**
     * Get memory usage percentage
     */
    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 0; // No limit
        }
        
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        return ($memoryUsage / $memoryLimitBytes) * 100;
    }
    
    /**
     * Get CPU usage percentage
     */
    private function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            return $load[0] * 100; // Convert to percentage
        }
        
        return 0; // Not available on this OS
    }
    
    /**
     * Get error rate percentage
     */
    private function getErrorRate(): float
    {
        // This would typically query error logs
        return rand(0, 10); // Mock data
    }
    
    /**
     * Get request count per minute
     */
    private function getRequestCount(): int
    {
        // This would typically query request logs
        return rand(100, 2000); // Mock data
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}
