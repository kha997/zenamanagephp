<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MemoryMonitoringService
{
    protected array $memoryThresholds = [
        'warning' => 70, // percentage
        'critical' => 85, // percentage
        'max_memory' => 128 * 1024 * 1024, // 128MB
    ];

    protected array $memoryHistory = [];

    public function __construct()
    {
        $this->initializeMemoryHistory();
    }

    /**
     * Initialize memory history
     */
    protected function initializeMemoryHistory(): void
    {
        $this->memoryHistory = [
            'current_usage' => [],
            'peak_usage' => [],
            'memory_limit' => [],
            'allocated_memory' => [],
            'free_memory' => [],
        ];
    }

    /**
     * Get current memory usage
     */
    public function getCurrentMemoryUsage(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $allocatedMemory = memory_get_usage(false);
        $freeMemory = $this->getFreeMemory();

        return [
            'current_usage' => $currentUsage,
            'peak_usage' => $peakUsage,
            'memory_limit' => $this->parseMemoryLimit($memoryLimit),
            'allocated_memory' => $allocatedMemory,
            'free_memory' => $freeMemory,
            'usage_percentage' => $this->calculateUsagePercentage($currentUsage, $this->parseMemoryLimit($memoryLimit)),
            'peak_percentage' => $this->calculateUsagePercentage($peakUsage, $this->parseMemoryLimit($memoryLimit)),
            'timestamp' => now(),
        ];
    }

    /**
     * Record memory usage
     */
    public function recordMemoryUsage(): void
    {
        $memoryData = $this->getCurrentMemoryUsage();
        
        // Store in history
        $this->memoryHistory['current_usage'][] = $memoryData['current_usage'];
        $this->memoryHistory['peak_usage'][] = $memoryData['peak_usage'];
        $this->memoryHistory['memory_limit'][] = $memoryData['memory_limit'];
        $this->memoryHistory['allocated_memory'][] = $memoryData['allocated_memory'];
        $this->memoryHistory['free_memory'][] = $memoryData['free_memory'];

        // Check thresholds
        $this->checkMemoryThresholds($memoryData);

        // Store in cache for real-time monitoring
        $this->storeMemoryMetric($memoryData);
    }

    /**
     * Check memory thresholds
     */
    protected function checkMemoryThresholds(array $memoryData): void
    {
        $usagePercentage = $memoryData['usage_percentage'];
        $peakPercentage = $memoryData['peak_percentage'];

        if ($usagePercentage >= $this->memoryThresholds['critical']) {
            Log::critical('Memory usage is critical', [
                'current_usage' => $memoryData['current_usage'],
                'usage_percentage' => $usagePercentage,
                'peak_usage' => $memoryData['peak_usage'],
                'peak_percentage' => $peakPercentage,
                'memory_limit' => $memoryData['memory_limit'],
            ]);
        } elseif ($usagePercentage >= $this->memoryThresholds['warning']) {
            Log::warning('Memory usage is high', [
                'current_usage' => $memoryData['current_usage'],
                'usage_percentage' => $usagePercentage,
                'peak_usage' => $memoryData['peak_usage'],
                'peak_percentage' => $peakPercentage,
                'memory_limit' => $memoryData['memory_limit'],
            ]);
        }
    }

    /**
     * Store memory metric in cache
     */
    protected function storeMemoryMetric(array $memoryData): void
    {
        $cacheKey = 'memory_metric_' . now()->format('Y-m-d-H-i-s');
        Cache::put($cacheKey, $memoryData, 300); // 5 minutes
        
        // Store cache key for retrieval
        $cacheKeys = Cache::get('memory_metrics_keys', []);
        $cacheKeys[] = $cacheKey;
        Cache::put('memory_metrics_keys', $cacheKeys, 300);
    }

    /**
     * Get memory statistics
     */
    public function getMemoryStats(): array
    {
        $stats = [];

        // Current usage statistics
        if (!empty($this->memoryHistory['current_usage'])) {
            $currentUsage = $this->memoryHistory['current_usage'];
            $stats['current_usage'] = [
                'avg' => round(array_sum($currentUsage) / count($currentUsage), 2),
                'min' => min($currentUsage),
                'max' => max($currentUsage),
                'current' => end($currentUsage),
                'count' => count($currentUsage),
            ];
        }

        // Peak usage statistics
        if (!empty($this->memoryHistory['peak_usage'])) {
            $peakUsage = $this->memoryHistory['peak_usage'];
            $stats['peak_usage'] = [
                'avg' => round(array_sum($peakUsage) / count($peakUsage), 2),
                'min' => min($peakUsage),
                'max' => max($peakUsage),
                'current' => end($peakUsage),
                'count' => count($peakUsage),
            ];
        }

        // Memory limit statistics
        if (!empty($this->memoryHistory['memory_limit'])) {
            $memoryLimit = $this->memoryHistory['memory_limit'];
            $stats['memory_limit'] = [
                'avg' => round(array_sum($memoryLimit) / count($memoryLimit), 2),
                'min' => min($memoryLimit),
                'max' => max($memoryLimit),
                'current' => end($memoryLimit),
                'count' => count($memoryLimit),
            ];
        }

        // Allocated memory statistics
        if (!empty($this->memoryHistory['allocated_memory'])) {
            $allocatedMemory = $this->memoryHistory['allocated_memory'];
            $stats['allocated_memory'] = [
                'avg' => round(array_sum($allocatedMemory) / count($allocatedMemory), 2),
                'min' => min($allocatedMemory),
                'max' => max($allocatedMemory),
                'current' => end($allocatedMemory),
                'count' => count($allocatedMemory),
            ];
        }

        // Free memory statistics
        if (!empty($this->memoryHistory['free_memory'])) {
            $freeMemory = $this->memoryHistory['free_memory'];
            $stats['free_memory'] = [
                'avg' => round(array_sum($freeMemory) / count($freeMemory), 2),
                'min' => min($freeMemory),
                'max' => max($freeMemory),
                'current' => end($freeMemory),
                'count' => count($freeMemory),
            ];
        }

        return $stats;
    }

    /**
     * Get memory recommendations
     */
    public function getMemoryRecommendations(): array
    {
        $recommendations = [];
        $currentUsage = $this->getCurrentMemoryUsage();

        // High memory usage recommendations
        if ($currentUsage['usage_percentage'] >= $this->memoryThresholds['critical']) {
            $recommendations[] = [
                'type' => 'critical_memory_usage',
                'priority' => 'critical',
                'message' => 'Memory usage is critical. Consider optimizing memory usage, reducing object creation, or increasing memory limit.',
                'current_value' => $currentUsage['usage_percentage'],
                'threshold' => $this->memoryThresholds['critical'],
            ];
        } elseif ($currentUsage['usage_percentage'] >= $this->memoryThresholds['warning']) {
            $recommendations[] = [
                'type' => 'high_memory_usage',
                'priority' => 'high',
                'message' => 'Memory usage is high. Consider optimizing memory usage or implementing garbage collection.',
                'current_value' => $currentUsage['usage_percentage'],
                'threshold' => $this->memoryThresholds['warning'],
            ];
        }

        // Peak memory usage recommendations
        if ($currentUsage['peak_percentage'] >= $this->memoryThresholds['critical']) {
            $recommendations[] = [
                'type' => 'critical_peak_memory',
                'priority' => 'critical',
                'message' => 'Peak memory usage is critical. Consider optimizing memory usage patterns or increasing memory limit.',
                'current_value' => $currentUsage['peak_percentage'],
                'threshold' => $this->memoryThresholds['critical'],
            ];
        }

        // Memory limit recommendations
        if ($currentUsage['memory_limit'] < $this->memoryThresholds['max_memory']) {
            $recommendations[] = [
                'type' => 'low_memory_limit',
                'priority' => 'medium',
                'message' => 'Memory limit is low. Consider increasing memory limit for better performance.',
                'current_value' => $currentUsage['memory_limit'],
                'recommended_value' => $this->memoryThresholds['max_memory'],
            ];
        }

        return $recommendations;
    }

    /**
     * Get memory thresholds
     */
    public function getMemoryThresholds(): array
    {
        return $this->memoryThresholds;
    }

    /**
     * Set memory thresholds
     */
    public function setMemoryThresholds(array $thresholds): void
    {
        $this->memoryThresholds = array_merge($this->memoryThresholds, $thresholds);
    }

    /**
     * Get free memory
     */
    protected function getFreeMemory(): int
    {
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $currentUsage = memory_get_usage(true);
        return max(0, $memoryLimit - $currentUsage);
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Calculate usage percentage
     */
    protected function calculateUsagePercentage(int $usage, int $limit): float
    {
        if ($limit <= 0) {
            return 0;
        }
        return round(($usage / $limit) * 100, 2);
    }

    /**
     * Get real-time memory metrics
     */
    public function getRealTimeMetrics(): array
    {
        $metrics = [];
        
        // Get cached metrics from last 5 minutes
        $cacheKeys = Cache::get('memory_metrics_keys', []);
        
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
     * Clear memory history
     */
    public function clearHistory(): void
    {
        $this->initializeMemoryHistory();
        Cache::forget('memory_metrics_keys');
    }

    /**
     * Export memory data
     */
    public function exportMemoryData(): array
    {
        return [
            'timestamp' => now(),
            'current_usage' => $this->getCurrentMemoryUsage(),
            'history' => $this->memoryHistory,
            'stats' => $this->getMemoryStats(),
            'recommendations' => $this->getMemoryRecommendations(),
            'thresholds' => $this->getMemoryThresholds(),
        ];
    }

    /**
     * Force garbage collection
     */
    public function forceGarbageCollection(): array
    {
        $beforeUsage = memory_get_usage(true);
        $beforePeak = memory_get_peak_usage(true);
        
        gc_collect_cycles();
        
        $afterUsage = memory_get_usage(true);
        $afterPeak = memory_get_peak_usage(true);
        
        return [
            'before_usage' => $beforeUsage,
            'after_usage' => $afterUsage,
            'freed_memory' => $beforeUsage - $afterUsage,
            'before_peak' => $beforePeak,
            'after_peak' => $afterPeak,
            'timestamp' => now(),
        ];
    }

    /**
     * Get memory usage by class
     */
    public function getMemoryUsageByClass(): array
    {
        $classes = get_declared_classes();
        $memoryUsage = [];
        
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isUserDefined()) {
                $memoryUsage[$class] = [
                    'class' => $class,
                    'methods' => count($reflection->getMethods()),
                    'properties' => count($reflection->getProperties()),
                    'constants' => count($reflection->getConstants()),
                ];
            }
        }
        
        return $memoryUsage;
    }
}
