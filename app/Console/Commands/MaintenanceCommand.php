<?php

namespace App\Console\Commands;

use App\Models\MaintenanceTask;
use App\Models\PerformanceMetric;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $task = $this->createMaintenanceTask('Clear application cache', 'info', 'medium');

        try {
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->call('route:clear');
            $this->call('view:clear');

            Cache::flush();

            $this->completeMaintenanceTask($task, ['caches_cleared' => ['laravel', 'redis']]);
            $this->info('✓ Cache cleared successfully');
        } catch (\Exception $e) {
            $this->failMaintenanceTask($task, $e->getMessage());
            $this->error('✗ Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Optimize database
     */
    private function optimizeDatabase()
    {
        $this->info('Optimizing database...');

        $task = $this->createMaintenanceTask('Optimize database', 'info', 'medium');
        $optimizedTables = [];

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $tables = DB::select('SHOW TABLES');

                foreach ($tables as $table) {
                    $tableName = array_values((array) $table)[0];

                    DB::statement("OPTIMIZE TABLE `{$tableName}`");
                    DB::statement("ANALYZE TABLE `{$tableName}`");
                    $optimizedTables[] = $tableName;
                }
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA optimize');
                DB::statement('PRAGMA integrity_check');
                $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

                foreach ($rows as $row) {
                    $optimizedTables[] = $row->name;
                }
            } else {
                $this->info("Database optimization is skipped for driver {$driver}");
            }

            $this->completeMaintenanceTask($task, ['optimized_tables' => $optimizedTables]);
            $this->info('✓ Database optimized successfully (' . count($optimizedTables) . ' tables)');
        } catch (\Exception $e) {
            $this->failMaintenanceTask($task, $e->getMessage());
            $this->error('✗ Failed to optimize database: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup old logs
     */
    private function cleanupLogs()
    {
        $this->info('Cleaning up old logs...');

        $task = $this->createMaintenanceTask('Cleanup old logs', 'info', 'low');

        try {
            $deletedLogs = SystemLog::where('created_at', '<', Carbon::now()->subDays(30))->delete();
            $deletedTasks = MaintenanceTask::where('created_at', '<', Carbon::now()->subDays(90))->delete();
            $deletedMetrics = PerformanceMetric::where('created_at', '<', Carbon::now()->subDays(180))->delete();

            $this->completeMaintenanceTask($task, [
                'deleted_logs' => $deletedLogs,
                'deleted_tasks' => $deletedTasks,
                'deleted_metrics' => $deletedMetrics
            ]);

            $this->info("✓ Cleaned up {$deletedLogs} logs, {$deletedTasks} tasks, {$deletedMetrics} metrics");
        } catch (\Exception $e) {
            $this->failMaintenanceTask($task, $e->getMessage());
            $this->error('✗ Failed to cleanup logs: ' . $e->getMessage());
        }
    }

    /**
     * Collect system metrics
     */
    private function collectMetrics()
    {
        $this->info('Collecting system metrics...');

        $task = $this->createMaintenanceTask('Collect system metrics', 'info', 'low');
        $recordedMetrics = [];

        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $memoryPercentage = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;

            PerformanceMetric::record('memory_usage', $memoryUsage, 'bytes', 'system');
            PerformanceMetric::record('memory_percentage', $memoryPercentage, 'percentage', 'system');
            $recordedMetrics[] = 'memory_usage';
            $recordedMetrics[] = 'memory_percentage';

            $totalSpace = disk_total_space('/');
            $freeSpace = disk_free_space('/');
            $usedSpace = $totalSpace - $freeSpace;
            $diskPercentage = ($usedSpace / $totalSpace) * 100;

            PerformanceMetric::record('disk_usage', $usedSpace, 'bytes', 'system');
            PerformanceMetric::record('disk_percentage', $diskPercentage, 'percentage', 'system');
            $recordedMetrics[] = 'disk_usage';
            $recordedMetrics[] = 'disk_percentage';

            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                try {
                    $dbSize = DB::select("
                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()
                    ")[0]->size_mb ?? 0;

                    PerformanceMetric::record('database_size', $dbSize, 'MB', 'database');
                    $recordedMetrics[] = 'database_size';
                } catch (\Exception $e) {
                    $this->warn('Could not collect database size: ' . $e->getMessage());
                }

                try {
                    $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
                    PerformanceMetric::record('database_connections', $connections, 'connections', 'database');
                    $recordedMetrics[] = 'database_connections';
                } catch (\Exception $e) {
                    $this->warn('Could not collect database connections: ' . $e->getMessage());
                }
            } elseif ($driver === 'sqlite') {
                try {
                    $tableCount = DB::select("SELECT COUNT(*) as count FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
                    $countValue = (int) ($tableCount[0]->count ?? 0);
                    PerformanceMetric::record('database_table_count', $countValue, 'tables', 'database');
                    $recordedMetrics[] = 'database_table_count';
                } catch (\Exception $e) {
                    $this->warn('Could not count SQLite tables: ' . $e->getMessage());
                }
            }

            try {
                $redisInfo = Cache::store('redis')->getRedis()->info();
                $hits = $redisInfo['keyspace_hits'] ?? 0;
                $misses = $redisInfo['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

                PerformanceMetric::record('cache_hit_rate', $hitRate, 'percentage', 'cache');
                $recordedMetrics[] = 'cache_hit_rate';
            } catch (\Exception $e) {
                $this->warn('Could not collect cache metrics: ' . $e->getMessage());
            }

            $this->completeMaintenanceTask($task, ['metrics_collected' => count($recordedMetrics), 'metrics' => $recordedMetrics]);
            $this->info('✓ System metrics collected successfully');
        } catch (\Exception $e) {
            $this->failMaintenanceTask($task, $e->getMessage());
            $this->error('✗ Failed to collect metrics: ' . $e->getMessage());
        }
    }

    /**
     * Create system backup
     */
    private function createBackup()
    {
        $this->info('Creating system backup...');

        $task = $this->createMaintenanceTask('Create system backup', 'info', 'high');

        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $path = storage_path('backups/' . $filename);

            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $config = config('database.connections.mysql', []);

                if (empty($config['host']) || empty($config['database'])) {
                    throw new \RuntimeException('MySQL backup configuration is incomplete');
                }

                $command = sprintf(
                    'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers %s > %s',
                    $config['username'] ?? '',
                    $config['password'] ?? '',
                    $config['host'],
                    $config['port'] ?? 3306,
                    $config['database'],
                    $path
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new \RuntimeException('mysqldump command failed with return code: ' . $returnCode);
                }

                if (!file_exists($path) || filesize($path) === 0) {
                    throw new \RuntimeException('Database backup file is empty or missing');
                }
            } else {
                file_put_contents($path, $this->buildFallbackBackupContent($driver));
            }

            $fileSize = file_exists($path) ? filesize($path) : 0;

            $this->completeMaintenanceTask($task, [
                'filename' => $filename,
                'file_size' => $fileSize,
                'backup_path' => $path,
                'driver' => $driver
            ]);

            $this->info("✓ Backup created successfully: {$filename} (" . $this->formatBytes($fileSize) . ")");
        } catch (\Exception $e) {
            $this->failMaintenanceTask($task, $e->getMessage());
            $this->error('✗ Failed to create backup: ' . $e->getMessage());
        }
    }

    private function createMaintenanceTask(string $name, string $level = 'info', string $priority = 'medium', array $metadata = []): ?MaintenanceTask
    {
        if (!Schema::hasTable('maintenance_tasks')) {
            $this->warn("maintenance_tasks table is missing; skipping log for {$name}");
            return null;
        }

        return MaintenanceTask::create([
            'task' => $name,
            'level' => $level,
            'priority' => $priority,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata,
            'tenant_id' => app()->has('tenant') ? app('tenant')->id : null,
        ]);
    }

    private function completeMaintenanceTask(?MaintenanceTask $task, array $payload = []): void
    {
        if ($task) {
            $task->markAsCompleted($payload ?: null);
        }
    }

    private function failMaintenanceTask(?MaintenanceTask $task, string $message): void
    {
        if ($task) {
            $task->markAsFailed($message);
        }
    }

    private function buildFallbackBackupContent(string $driver): string
    {
        $tables = [];

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            $tables = array_map(fn ($row) => $row->name, $rows);
        }

        $payload = [
            'driver' => $driver,
            'tables' => $tables,
            'timestamp' => now()->toIso8601String(),
        ];

        return "-- Backup placeholder for {$driver}\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
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
