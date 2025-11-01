<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:monitor 
                            {--interval=60 : Monitoring interval in seconds}
                            {--duration=300 : Total monitoring duration in seconds}
                            {--output=table : Output format (table, json, csv)}
                            {--log : Log results to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor system performance in real-time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = $this->option('interval');
        $duration = $this->option('duration');
        $output = $this->option('output');
        $log = $this->option('log');

        $this->info('📊 ZenaManage System Monitor');
        $this->info("Monitoring interval: {$interval}s");
        $this->info("Total duration: {$duration}s");
        $this->newLine();

        $startTime = time();
        $endTime = $startTime + $duration;
        $iteration = 0;

        $results = [];

        while (time() < $endTime) {
            $iteration++;
            $timestamp = now()->toISOString();
            
            $this->line("Iteration {$iteration} - {$timestamp}");
            
            $metrics = $this->collectMetrics();
            $results[] = $metrics;
            
            $this->displayMetrics($metrics, $output);
            
            if ($log) {
                $this->logMetrics($metrics);
            }
            
            if (time() < $endTime) {
                sleep($interval);
            }
        }

        $this->newLine();
        $this->info('📈 Monitoring Summary:');
        $this->displaySummary($results);

        return 0;
    }

    /**
     * Collect system metrics
     */
    private function collectMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'system' => $this->getSystemMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'redis' => $this->getRedisMetrics(),
            'queue' => $this->getQueueMetrics(),
            'email' => $this->getEmailMetrics(),
            'cache' => $this->getCacheMetrics(),
        ];
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'disk_free' => disk_free_space('/'),
            'disk_total' => disk_total_space('/'),
            'load_average' => sys_getloadavg(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $users = DB::table('users')->count();
            $invitations = DB::table('invitations')->count();
            $emailTracking = DB::table('email_tracking')->count();
            
            return [
                'users_count' => $users,
                'invitations_count' => $invitations,
                'email_tracking_count' => $emailTracking,
                'connection_status' => 'connected',
            ];
        } catch (\Exception $e) {
            return [
                'connection_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Redis metrics
     */
    private function getRedisMetrics(): array
    {
        try {
            $info = Redis::info();
            $memory = Redis::info('memory');
            
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $memory['used_memory'] ?? 0,
                'used_memory_human' => $memory['used_memory_human'] ?? '0B',
                'redis_version' => $info['redis_version'] ?? 'Unknown',
                'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'connection_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue metrics
     */
    private function getQueueMetrics(): array
    {
        try {
            $queues = ['emails-high', 'emails-medium', 'emails-low', 'emails-welcome'];
            $totalPending = 0;
            $totalFailed = 0;
            $totalProcessing = 0;
            
            foreach ($queues as $queue) {
                $pending = Redis::llen("queues:{$queue}");
                $failed = Redis::llen("queues:{$queue}:failed");
                $processing = Redis::llen("queues:{$queue}:processing");
                
                $totalPending += $pending;
                $totalFailed += $failed;
                $totalProcessing += $processing;
            }
            
            return [
                'total_pending' => $totalPending,
                'total_failed' => $totalFailed,
                'total_processing' => $totalProcessing,
                'queues' => $queues,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get email metrics
     */
    private function getEmailMetrics(): array
    {
        try {
            $last24Hours = now()->subHours(24);
            
            $totalSent = DB::table('email_tracking')
                ->where('created_at', '>=', $last24Hours)
                ->count();
                
            $totalDelivered = DB::table('email_tracking')
                ->where('created_at', '>=', $last24Hours)
                ->where('status', 'delivered')
                ->count();
                
            $totalFailed = DB::table('email_tracking')
                ->where('created_at', '>=', $last24Hours)
                ->where('status', 'failed')
                ->count();
            
            $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0;
            $failureRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0;
            
            return [
                'total_sent_24h' => $totalSent,
                'total_delivered_24h' => $totalDelivered,
                'total_failed_24h' => $totalFailed,
                'delivery_rate' => $deliveryRate,
                'failure_rate' => $failureRate,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $driver = config('cache.default');
            $store = Cache::store();
            
            return [
                'driver' => $driver,
                'store' => get_class($store),
                'status' => 'active',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Display metrics
     */
    private function displayMetrics(array $metrics, string $output): void
    {
        switch ($output) {
            case 'json':
                $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->displayCsv($metrics);
                break;
            default:
                $this->displayTable($metrics);
                break;
        }
    }

    /**
     * Display metrics as table
     */
    private function displayTable(array $metrics): void
    {
        $system = $metrics['system'];
        $database = $metrics['database'];
        $redis = $metrics['redis'];
        $queue = $metrics['queue'];
        $email = $metrics['email'];
        
        $this->table(['Metric', 'Value'], [
            ['Memory Usage', $this->formatBytes($system['memory_usage'])],
            ['Memory Peak', $this->formatBytes($system['memory_peak'])],
            ['Disk Free', $this->formatBytes($system['disk_free'])],
            ['Load Average', implode(', ', $system['load_average'])],
            ['DB Users', $database['users_count'] ?? 'N/A'],
            ['DB Invitations', $database['invitations_count'] ?? 'N/A'],
            ['Redis Memory', $redis['used_memory_human'] ?? 'N/A'],
            ['Redis Clients', $redis['connected_clients'] ?? 'N/A'],
            ['Queue Pending', $queue['total_pending'] ?? 'N/A'],
            ['Queue Failed', $queue['total_failed'] ?? 'N/A'],
            ['Email Sent (24h)', $email['total_sent_24h'] ?? 'N/A'],
            ['Delivery Rate', ($email['delivery_rate'] ?? 0) . '%'],
        ]);
    }

    /**
     * Display metrics as CSV
     */
    private function displayCsv(array $metrics): void
    {
        $system = $metrics['system'];
        $database = $metrics['database'];
        $redis = $metrics['redis'];
        $queue = $metrics['queue'];
        $email = $metrics['email'];
        
        $csv = [
            $metrics['timestamp'],
            $this->formatBytes($system['memory_usage']),
            $this->formatBytes($system['memory_peak']),
            $this->formatBytes($system['disk_free']),
            implode(',', $system['load_average']),
            $database['users_count'] ?? 'N/A',
            $database['invitations_count'] ?? 'N/A',
            $redis['used_memory_human'] ?? 'N/A',
            $redis['connected_clients'] ?? 'N/A',
            $queue['total_pending'] ?? 'N/A',
            $queue['total_failed'] ?? 'N/A',
            $email['total_sent_24h'] ?? 'N/A',
            $email['delivery_rate'] ?? 0,
        ];
        
        $this->line(implode(',', $csv));
    }

    /**
     * Log metrics
     */
    private function logMetrics(array $metrics): void
    {
        $logFile = storage_path('logs/system-monitor.log');
        $logEntry = json_encode($metrics) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Display monitoring summary
     */
    private function displaySummary(array $results): void
    {
        if (empty($results)) {
            $this->warn('No monitoring data collected');
            return;
        }

        $count = count($results);
        $first = $results[0];
        $last = $results[$count - 1];
        
        $this->table(['Metric', 'First', 'Last', 'Change'], [
            ['Memory Usage', $this->formatBytes($first['system']['memory_usage']), $this->formatBytes($last['system']['memory_usage']), $this->getChange($first['system']['memory_usage'], $last['system']['memory_usage'])],
            ['Queue Pending', $first['queue']['total_pending'] ?? 'N/A', $last['queue']['total_pending'] ?? 'N/A', $this->getChange($first['queue']['total_pending'] ?? 0, $last['queue']['total_pending'] ?? 0)],
            ['Queue Failed', $first['queue']['total_failed'] ?? 'N/A', $last['queue']['total_failed'] ?? 'N/A', $this->getChange($first['queue']['total_failed'] ?? 0, $last['queue']['total_failed'] ?? 0)],
            ['Email Delivery Rate', ($first['email']['delivery_rate'] ?? 0) . '%', ($last['email']['delivery_rate'] ?? 0) . '%', $this->getChange($first['email']['delivery_rate'] ?? 0, $last['email']['delivery_rate'] ?? 0)],
        ]);
    }

    /**
     * Format bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get change indicator
     */
    private function getChange($first, $last): string
    {
        if ($first === 'N/A' || $last === 'N/A') {
            return 'N/A';
        }
        
        $change = $last - $first;
        
        if ($change > 0) {
            return "↗️ +{$change}";
        } elseif ($change < 0) {
            return "↘️ {$change}";
        } else {
            return "➡️ 0";
        }
    }
}