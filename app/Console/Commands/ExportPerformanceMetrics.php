<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PerformanceMonitoringService;

/**
 * Export Performance Metrics Command
 * 
 * PR: Metrics collection + Performance budgets enforcement
 * 
 * Exports performance metrics in JSON format for CI budget validation.
 */
class ExportPerformanceMetrics extends Command
{
    protected $signature = 'metrics:export 
                            {--output=test-results/performance-metrics.json : Output file path}
                            {--format=json : Output format (json)}';

    protected $description = 'Export performance metrics for CI budget validation';

    protected PerformanceMonitoringService $performanceService;

    public function __construct(PerformanceMonitoringService $performanceService)
    {
        parent::__construct();
        $this->performanceService = $performanceService;
    }

    public function handle(): int
    {
        $outputFile = $this->option('output');
        $format = $this->option('format');

        $this->info('📊 Collecting performance metrics...');

        $metrics = [
            'timestamp' => now()->toISOString(),
            'api' => $this->collectApiMetrics(),
            'pages' => $this->collectPageMetrics(),
            'websocket' => $this->collectWebSocketMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'memory' => $this->collectMemoryMetrics(),
        ];

        // Ensure output directory exists
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Write metrics to file
        if ($format === 'json') {
            file_put_contents($outputFile, json_encode($metrics, JSON_PRETTY_PRINT));
            $this->info("✅ Metrics exported to: {$outputFile}");
        } else {
            $this->error("Unsupported format: {$format}");
            return Command::FAILURE;
        }

        // Print summary
        $this->info("\n📊 Metrics Summary:");
        $this->info("   API endpoints: " . count($metrics['api']));
        $this->info("   Pages: " . count($metrics['pages']));
        $this->info("   WebSocket: " . count($metrics['websocket']));
        $this->info("   Cache: " . count($metrics['cache']));
        $this->info("   Database: " . count($metrics['database']));
        $this->info("   Memory: " . count($metrics['memory']));

        return Command::SUCCESS;
    }

    protected function collectApiMetrics(): array
    {
        $apiMetrics = [];

        // Try to get metrics from cache (stored by PerformanceMiddleware)
        $cachedMetrics = Cache::get('performance:api:metrics', []);

        if (!empty($cachedMetrics)) {
            foreach ($cachedMetrics as $endpoint => $data) {
                if (isset($data['response_times']) && count($data['response_times']) > 0) {
                    $times = $data['response_times'];
                    sort($times);
                    
                    $apiMetrics[$endpoint] = [
                        'p50' => $this->percentile($times, 50),
                        'p95' => $this->percentile($times, 95),
                        'p99' => $this->percentile($times, 99),
                        'max' => max($times),
                        'count' => count($times),
                    ];
                }
            }
        }

        return $apiMetrics;
    }

    protected function collectPageMetrics(): array
    {
        $pageMetrics = [];

        // Try to get metrics from cache
        $cachedMetrics = Cache::get('performance:pages:metrics', []);

        if (!empty($cachedMetrics)) {
            foreach ($cachedMetrics as $route => $data) {
                if (isset($data['load_times']) && count($data['load_times']) > 0) {
                    $times = $data['load_times'];
                    sort($times);
                    
                    $pageMetrics[$route] = [
                        'p50' => $this->percentile($times, 50),
                        'p95' => $this->percentile($times, 95),
                        'p99' => $this->percentile($times, 99),
                        'max' => max($times),
                        'count' => count($times),
                        'fcp' => $data['fcp'] ?? null,
                        'lcp' => $data['lcp'] ?? null,
                        'ttfb' => $data['ttfb'] ?? null,
                    ];
                }
            }
        }

        return $pageMetrics;
    }

    protected function collectWebSocketMetrics(): array
    {
        $wsMetrics = [];

        // Try to get metrics from cache
        $cachedMetrics = Cache::get('performance:websocket:metrics', []);

        if (!empty($cachedMetrics)) {
            if (isset($cachedMetrics['subscribe'])) {
                $times = $cachedMetrics['subscribe'];
                sort($times);
                $wsMetrics['subscribe'] = [
                    'p50' => $this->percentile($times, 50),
                    'p95' => $this->percentile($times, 95),
                    'p99' => $this->percentile($times, 99),
                    'max' => max($times),
                ];
            }

            if (isset($cachedMetrics['message_delivery'])) {
                $times = $cachedMetrics['message_delivery'];
                sort($times);
                $wsMetrics['message_delivery'] = [
                    'p50' => $this->percentile($times, 50),
                    'p95' => $this->percentile($times, 95),
                    'p99' => $this->percentile($times, 99),
                    'max' => max($times),
                ];
            }
        }

        return $wsMetrics;
    }

    protected function collectCacheMetrics(): array
    {
        $cacheMetrics = [];

        // Get cache hit rate from cache stats (if available)
        $hitRate = Cache::get('performance:cache:hit_rate');
        if ($hitRate !== null) {
            $cacheMetrics['hit_rate'] = $hitRate;
        }

        return $cacheMetrics;
    }

    protected function collectDatabaseMetrics(): array
    {
        $dbMetrics = [];

        // Get query performance from query log
        if (config('database.default') === 'sqlite' || DB::getQueryLog()) {
            $queries = DB::getQueryLog();
            
            if (!empty($queries)) {
                $queryTimes = array_column($queries, 'time');
                sort($queryTimes);
                
                $dbMetrics['query_time'] = [
                    'p50' => $this->percentile($queryTimes, 50),
                    'p95' => $this->percentile($queryTimes, 95),
                    'p99' => $this->percentile($queryTimes, 99),
                    'max' => max($queryTimes),
                    'count' => count($queryTimes),
                ];

                $slowQueries = array_filter($queryTimes, fn($t) => $t > 100);
                $dbMetrics['slow_queries'] = [
                    'count' => count($slowQueries),
                    'threshold' => 100,
                ];
            }
        }

        return $dbMetrics;
    }

    protected function collectMemoryMetrics(): array
    {
        $memoryMetrics = [];

        $peakUsage = memory_get_peak_usage(true);
        $currentUsage = memory_get_usage(true);
        
        // Calculate percentage (assuming 512MB limit for PHP)
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        
        $peakUsagePercent = ($memoryLimitBytes > 0) 
            ? ($peakUsage / $memoryLimitBytes) * 100 
            : 0;

        $memoryMetrics = [
            'peak_usage' => round($peakUsagePercent, 2),
            'peak_bytes' => $peakUsage,
            'current_bytes' => $currentUsage,
            'limit_bytes' => $memoryLimitBytes,
        ];

        return $memoryMetrics;
    }

    protected function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = (count($values) - 1) * ($percentile / 100);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $weight = $index - $lower;
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }
}

