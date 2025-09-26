<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use App\Models\MaintenanceTask;
use App\Models\PerformanceMetric;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Display maintenance dashboard
     */
    public function index()
    {
        $systemHealth = $this->getSystemHealth();
        $recentLogs = $this->getRecentLogs();
        $maintenanceTasks = $this->getMaintenanceTasks();
        $performanceMetrics = $this->getPerformanceMetrics();
        $diskUsage = $this->getDiskUsage();
        $databaseStats = $this->getDatabaseStats();

        return view('admin.maintenance.dashboard', compact(
            'systemHealth',
            'recentLogs',
            'maintenanceTasks',
            'performanceMetrics',
            'diskUsage',
            'databaseStats'
        ));
    }

    /**
     * Get system health status
     */
    private function getSystemHealth()
    {
        $health = [
            'status' => 'healthy',
            'services' => [],
            'issues' => []
        ];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $health['services']['database'] = [
                'status' => 'healthy',
                'response_time' => $this->getDatabaseResponseTime()
            ];
        } catch (\Exception $e) {
            $health['services']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['issues'][] = 'Database connection failed';
        }

        // Check Redis connection
        try {
            Cache::store('redis')->put('health_check', 'ok', 10);
            $health['services']['redis'] = [
                'status' => 'healthy',
                'response_time' => $this->getRedisResponseTime()
            ];
        } catch (\Exception $e) {
            $health['services']['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['issues'][] = 'Redis connection failed';
        }

        // Check disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage['usage_percentage'] > 90) {
            $health['issues'][] = 'Disk space critical: ' . $diskUsage['usage_percentage'] . '%';
        }

        // Check memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 90) {
            $health['issues'][] = 'Memory usage critical: ' . $memoryUsage . '%';
        }

        // Determine overall status
        if (!empty($health['issues'])) {
            $health['status'] = 'unhealthy';
        }

        return $health;
    }

    /**
     * Get recent system logs
     */
    private function getRecentLogs()
    {
        return SystemLog::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get maintenance tasks
     */
    private function getMaintenanceTasks()
    {
        return MaintenanceTask::orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics()
    {
        return PerformanceMetric::where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage()
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;

        return [
            'total' => $this->formatBytes($totalSpace),
            'used' => $this->formatBytes($usedSpace),
            'free' => $this->formatBytes($freeSpace),
            'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 2)
        ];
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats()
    {
        try {
            $stats = DB::select("
                SELECT 
                    table_schema as 'database',
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                GROUP BY table_schema
            ");

            $tableCount = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()");
            $connectionCount = DB::select("SHOW STATUS LIKE 'Threads_connected'");

            return [
                'size_mb' => $stats[0]->size_mb ?? 0,
                'table_count' => $tableCount[0]->count ?? 0,
                'connections' => $connectionCount[0]->Value ?? 0
            ];
        } catch (\Exception $e) {
            return [
                'size_mb' => 0,
                'table_count' => 0,
                'connections' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get database response time
     */
    private function getDatabaseResponseTime()
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get Redis response time
     */
    private function getRedisResponseTime()
    {
        $start = microtime(true);
        Cache::store('redis')->get('health_check');
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get memory usage percentage
     */
    private function getMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 0; // No limit
        }
        
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        return round(($memoryUsage / $memoryLimitBytes) * 100, 2);
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

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Clear Redis cache
            Cache::flush();

            $this->logMaintenanceTask('Cache cleared successfully', 'success');

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            $this->logMaintenanceTask('Cache clear failed: ' . $e->getMessage(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize application
     */
    public function optimize()
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            $this->logMaintenanceTask('Application optimized successfully', 'success');

            return response()->json([
                'success' => true,
                'message' => 'Application optimized successfully'
            ]);
        } catch (\Exception $e) {
            $this->logMaintenanceTask('Application optimization failed: ' . $e->getMessage(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize application: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run database maintenance
     */
    public function databaseMaintenance()
    {
        try {
            // Optimize tables
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                DB::statement("OPTIMIZE TABLE `{$tableName}`");
            }

            // Analyze tables
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                DB::statement("ANALYZE TABLE `{$tableName}`");
            }

            $this->logMaintenanceTask('Database maintenance completed successfully', 'success');

            return response()->json([
                'success' => true,
                'message' => 'Database maintenance completed successfully'
            ]);
        } catch (\Exception $e) {
            $this->logMaintenanceTask('Database maintenance failed: ' . $e->getMessage(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'Failed to run database maintenance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old logs
     */
    public function cleanupLogs()
    {
        try {
            $deletedCount = SystemLog::where('created_at', '<', Carbon::now()->subDays(30))->delete();

            $this->logMaintenanceTask("Cleaned up {$deletedCount} old log entries", 'success');

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} old log entries"
            ]);
        } catch (\Exception $e) {
            $this->logMaintenanceTask('Log cleanup failed: ' . $e->getMessage(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Backup database
     */
    public function backupDatabase()
    {
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $path = storage_path('backups/' . $filename);

            // Create backup directory if it doesn't exist
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            // Run mysqldump
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.database'),
                $path
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $this->logMaintenanceTask("Database backup created: {$filename}", 'success');

                return response()->json([
                    'success' => true,
                    'message' => 'Database backup created successfully',
                    'filename' => $filename
                ]);
            } else {
                throw new \Exception('mysqldump command failed');
            }
        } catch (\Exception $e) {
            $this->logMaintenanceTask('Database backup failed: ' . $e->getMessage(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'Failed to create database backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log maintenance task
     */
    private function logMaintenanceTask($message, $level = 'info')
    {
        MaintenanceTask::create([
            'task' => $message,
            'level' => $level,
            'user_id' => Auth::id(),
            'completed_at' => now()
        ]);

        SystemLog::create([
            'level' => $level,
            'message' => $message,
            'context' => ['maintenance' => true],
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Get system performance data
     */
    public function getPerformanceData()
    {
        $metrics = PerformanceMetric::where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get system health data
     */
    public function getHealthData()
    {
        $health = $this->getSystemHealth();
        
        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }
}