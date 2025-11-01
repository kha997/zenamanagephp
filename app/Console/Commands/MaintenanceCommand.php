<?php

namespace App\Console\Commands;

use App\Models\MaintenanceTask;
use App\Models\PerformanceMetric;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'maintenance:run {--task=all : Specific task to run}';

    /**
     * The console command description.
     */
    protected $description = 'Run automated maintenance tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $task = $this->option('task');

        $this->info('Starting automated maintenance...');

        switch ($task) {
            case 'all':
                $this->runAllTasks();
                break;
            case 'cache':
                $this->clearCache();
                break;
            case 'database':
                $this->optimizeDatabase();
                break;
            case 'logs':
                $this->cleanupLogs();
                break;
            case 'metrics':
                $this->collectMetrics();
                break;
            case 'backup':
                $this->createBackup();
                break;
            default:
                $this->error('Invalid task specified. Available tasks: all, cache, database, logs, metrics, backup');
                return 1;
        }

        $this->info('Maintenance completed successfully!');
        return 0;
    }

    /**
     * Run all maintenance tasks
     */
    private function runAllTasks()
    {
        $this->info('Running all maintenance tasks...');

        $this->clearCache();
        $this->optimizeDatabase();
        $this->cleanupLogs();
        $this->collectMetrics();
        $this->createBackup();

        $this->info('All maintenance tasks completed!');
    }

    /**
     * Clear application cache
     */
    private function clearCache()
    {
        $this->info('Clearing application cache...');

        try {
            $task = MaintenanceTask::create([
                'task' => 'Clear application cache',
                'level' => 'info',
                'priority' => 'medium',
                'status' => 'running',
                'started_at' => now()
            ]);

            // Clear Laravel caches
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->call('route:clear');
            $this->call('view:clear');

            // Clear Redis cache
            Cache::flush();

            $task->markAsCompleted(['caches_cleared' => ['laravel', 'redis']]);

            $this->info('✓ Cache cleared successfully');
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('✗ Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Optimize database
     */
    private function optimizeDatabase()
    {
        $this->info('Optimizing database...');

        try {
            $task = MaintenanceTask::create([
                'task' => 'Optimize database',
                'level' => 'info',
                'priority' => 'medium',
                'status' => 'running',
                'started_at' => now()
            ]);

            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $optimizedTables = [];

            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                
                // Optimize table
                DB::statement("OPTIMIZE TABLE `{$tableName}`");
                
                // Analyze table
                DB::statement("ANALYZE TABLE `{$tableName}`");
                
                $optimizedTables[] = $tableName;
            }

            $task->markAsCompleted(['optimized_tables' => $optimizedTables]);

            $this->info('✓ Database optimized successfully (' . count($optimizedTables) . ' tables)');
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('✗ Failed to optimize database: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup old logs
     */
    private function cleanupLogs()
    {
        $this->info('Cleaning up old logs...');

        try {
            $task = MaintenanceTask::create([
                'task' => 'Cleanup old logs',
                'level' => 'info',
                'priority' => 'low',
                'status' => 'running',
                'started_at' => now()
            ]);

            // Clean up system logs older than 30 days
            $deletedLogs = SystemLog::where('created_at', '<', Carbon::now()->subDays(30))->delete();

            // Clean up maintenance tasks older than 90 days
            $deletedTasks = MaintenanceTask::where('created_at', '<', Carbon::now()->subDays(90))->delete();

            // Clean up performance metrics older than 180 days
            $deletedMetrics = PerformanceMetric::where('created_at', '<', Carbon::now()->subDays(180))->delete();

            $task->markAsCompleted([
                'deleted_logs' => $deletedLogs,
                'deleted_tasks' => $deletedTasks,
                'deleted_metrics' => $deletedMetrics
            ]);

            $this->info("✓ Cleaned up {$deletedLogs} logs, {$deletedTasks} tasks, {$deletedMetrics} metrics");
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('✗ Failed to cleanup logs: ' . $e->getMessage());
        }
    }

    /**
     * Collect system metrics
     */
    private function collectMetrics()
    {
        $this->info('Collecting system metrics...');

        try {
            $task = MaintenanceTask::create([
                'task' => 'Collect system metrics',
                'level' => 'info',
                'priority' => 'low',
                'status' => 'running',
                'started_at' => now()
            ]);

            $metrics = [];

            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $memoryPercentage = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;

            PerformanceMetric::record('memory_usage', $memoryUsage, 'bytes', 'system');
            PerformanceMetric::record('memory_percentage', $memoryPercentage, 'percentage', 'system');

            // Disk usage
            $totalSpace = disk_total_space('/');
            $freeSpace = disk_free_space('/');
            $usedSpace = $totalSpace - $freeSpace;
            $diskPercentage = ($usedSpace / $totalSpace) * 100;

            PerformanceMetric::record('disk_usage', $usedSpace, 'bytes', 'system');
            PerformanceMetric::record('disk_percentage', $diskPercentage, 'percentage', 'system');

            // Database size
            try {
                $dbSize = DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()
                ")[0]->size_mb;

                PerformanceMetric::record('database_size', $dbSize, 'MB', 'database');
            } catch (\Exception $e) {
                $this->warn('Could not collect database size: ' . $e->getMessage());
            }

            // Database connections
            try {
                $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value;
                PerformanceMetric::record('database_connections', $connections, 'connections', 'database');
            } catch (\Exception $e) {
                $this->warn('Could not collect database connections: ' . $e->getMessage());
            }

            // Cache hit rate (if Redis is available)
            try {
                $redisInfo = Cache::store('redis')->getRedis()->info();
                $hits = $redisInfo['keyspace_hits'] ?? 0;
                $misses = $redisInfo['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

                PerformanceMetric::record('cache_hit_rate', $hitRate, 'percentage', 'cache');
            } catch (\Exception $e) {
                $this->warn('Could not collect cache metrics: ' . $e->getMessage());
            }

            $task->markAsCompleted(['metrics_collected' => count($metrics)]);

            $this->info('✓ System metrics collected successfully');
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('✗ Failed to collect metrics: ' . $e->getMessage());
        }
    }

    /**
     * Create system backup
     */
    private function createBackup()
    {
        $this->info('Creating system backup...');

        try {
            $task = MaintenanceTask::create([
                'task' => 'Create system backup',
                'level' => 'info',
                'priority' => 'high',
                'status' => 'running',
                'started_at' => now()
            ]);

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $path = storage_path('backups/' . $filename);

            // Create backup directory if it doesn't exist
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            // Run mysqldump with MariaDB compatibility
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --single-transaction --skip-routines --skip-triggers --skip-events %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.database'),
                $path
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                // Try fallback command
                $fallbackCommand = sprintf(
                    'mysqldump --user=%s --password=%s --host=%s --single-transaction --skip-routines --skip-triggers --skip-events --skip-lock-tables %s > %s',
                    config('database.connections.mysql.username'),
                    config('database.connections.mysql.password'),
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.database'),
                    $path
                );
                
                exec($fallbackCommand, $fallbackOutput, $fallbackReturnCode);
                
                if ($fallbackReturnCode !== 0) {
                    throw new \Exception('mysqldump command failed with return code: ' . $fallbackReturnCode . '. Output: ' . implode("\n", $fallbackOutput));
                }
            }

            if ($returnCode === 0 || (isset($fallbackReturnCode) && $fallbackReturnCode === 0)) {
                $fileSize = filesize($path);
                
                $task->markAsCompleted([
                    'filename' => $filename,
                    'file_size' => $fileSize,
                    'backup_path' => $path
                ]);

                $this->info("✓ Backup created successfully: {$filename} (" . $this->formatBytes($fileSize) . ")");
            } else {
                throw new \Exception('mysqldump command failed with return code: ' . $returnCode);
            }
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('✗ Failed to create backup: ' . $e->getMessage());
        }
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}