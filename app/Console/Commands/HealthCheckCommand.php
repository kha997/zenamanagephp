<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check';
    protected $description = 'Perform comprehensive health check of the application';

    public function handle()
    {
        $this->info('🔍 Starting health check...');
        
        $checks = [
            'Database Connection' => $this->checkDatabase(),
            'Cache System' => $this->checkCache(),
            'File Permissions' => $this->checkFilePermissions(),
            'Disk Space' => $this->checkDiskSpace(),
            'Memory Usage' => $this->checkMemoryUsage(),
            'Application Performance' => $this->checkApplicationPerformance(),
            'Security Status' => $this->checkSecurityStatus(),
            'Queue System' => $this->checkQueueSystem(),
            'Log System' => $this->checkLogSystem(),
            'Email System' => $this->checkEmailSystem()
        ];
        
        $this->displayResults($checks);
        
        $failedChecks = array_filter($checks, function($check) {
            return $check['status'] === 'FAIL';
        });
        
        if (count($failedChecks) > 0) {
            $this->error('❌ Health check failed with ' . count($failedChecks) . ' issues');
            return 1;
        }
        
        $this->info('✅ All health checks passed!');
        return 0;
    }
    
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            $userCount = User::count();
            $projectCount = Project::count();
            $taskCount = Task::count();
            $documentCount = Document::count();
            
            return [
                'status' => 'PASS',
                'message' => "Database connected successfully. Users: {$userCount}, Projects: {$projectCount}, Tasks: {$taskCount}, Documents: {$documentCount}",
                'details' => [
                    'connection' => 'OK',
                    'users' => $userCount,
                    'projects' => $projectCount,
                    'tasks' => $taskCount,
                    'documents' => $documentCount
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'FAIL',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    private function checkCache()
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test_value';
            
            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            Cache::forget($key);
            
            if ($retrieved === $value) {
                return [
                    'status' => 'PASS',
                    'message' => 'Cache system working correctly',
                    'details' => ['driver' => config('cache.default')]
                ];
            } else {
                return [
                    'status' => 'FAIL',
                    'message' => 'Cache system not working correctly',
                    'details' => ['driver' => config('cache.default')]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'FAIL',
                'message' => 'Cache system error: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    private function checkFilePermissions()
    {
        $directories = [
            'storage' => '755',
            'storage/app' => '755',
            'storage/framework' => '755',
            'storage/logs' => '755',
            'bootstrap/cache' => '755'
        ];
        
        $issues = [];
        
        foreach ($directories as $dir => $expectedPerms) {
            if (!is_dir($dir)) {
                $issues[] = "Directory {$dir} does not exist";
                continue;
            }
            
            $perms = substr(sprintf('%o', fileperms($dir)), -3);
            if ($perms !== $expectedPerms) {
                $issues[] = "Directory {$dir} has incorrect permissions: {$perms} (expected: {$expectedPerms})";
            }
        }
        
        if (empty($issues)) {
            return [
                'status' => 'PASS',
                'message' => 'All file permissions are correct',
                'details' => $directories
            ];
        } else {
            return [
                'status' => 'FAIL',
                'message' => 'File permission issues found',
                'details' => $issues
            ];
        }
    }
    
    private function checkDiskSpace()
    {
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        $usedBytes = $totalBytes - $freeBytes;
        $freePercent = ($freeBytes / $totalBytes) * 100;
        
        $status = $freePercent > 20 ? 'PASS' : 'WARN';
        
        return [
            'status' => $status,
            'message' => "Disk usage: " . round(100 - $freePercent, 2) . "% used, " . round($freePercent, 2) . "% free",
            'details' => [
                'total' => $this->formatBytes($totalBytes),
                'used' => $this->formatBytes($usedBytes),
                'free' => $this->formatBytes($freeBytes),
                'free_percent' => round($freePercent, 2)
            ]
        ];
    }
    
    private function checkMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
        
        $status = $usagePercent < 80 ? 'PASS' : 'WARN';
        
        return [
            'status' => $status,
            'message' => "Memory usage: " . round($usagePercent, 2) . "% of limit",
            'details' => [
                'used' => $this->formatBytes($memoryUsage),
                'limit' => $memoryLimit,
                'usage_percent' => round($usagePercent, 2)
            ]
        ];
    }
    
    private function checkApplicationPerformance()
    {
        $startTime = microtime(true);
        
        // Perform some basic operations
        User::count();
        Project::count();
        Task::count();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $status = $executionTime < 1000 ? 'PASS' : 'WARN';
        
        return [
            'status' => $status,
            'message' => "Application performance: " . round($executionTime, 2) . "ms",
            'details' => [
                'execution_time_ms' => round($executionTime, 2),
                'threshold_ms' => 1000
            ]
        ];
    }
    
    private function checkSecurityStatus()
    {
        $issues = [];
        
        // Check if APP_DEBUG is disabled in production
        if (config('app.debug') && config('app.env') === 'production') {
            $issues[] = 'APP_DEBUG is enabled in production';
        }
        
        // Check if APP_KEY is set
        if (empty(config('app.key'))) {
            $issues[] = 'APP_KEY is not set';
        }
        
        // Check if HTTPS is enforced in production
        if (config('app.env') === 'production' && !request()->secure()) {
            $issues[] = 'HTTPS is not enforced in production';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'PASS',
                'message' => 'Security configuration is correct',
                'details' => [
                    'debug_mode' => config('app.debug') ? 'enabled' : 'disabled',
                    'app_key_set' => !empty(config('app.key')),
                    'https_enforced' => request()->secure()
                ]
            ];
        } else {
            return [
                'status' => 'FAIL',
                'message' => 'Security issues found',
                'details' => $issues
            ];
        }
    }
    
    private function checkQueueSystem()
    {
        try {
            // Check if queue driver is configured
            $driver = config('queue.default');
            
            if ($driver === 'sync') {
                return [
                    'status' => 'WARN',
                    'message' => 'Queue system is using sync driver (not recommended for production)',
                    'details' => ['driver' => $driver]
                ];
            }
            
            return [
                'status' => 'PASS',
                'message' => 'Queue system is configured correctly',
                'details' => ['driver' => $driver]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'FAIL',
                'message' => 'Queue system error: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    private function checkLogSystem()
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            
            if (!file_exists($logPath)) {
                return [
                    'status' => 'WARN',
                    'message' => 'Log file does not exist',
                    'details' => ['path' => $logPath]
                ];
            }
            
            $logSize = filesize($logPath);
            $logSizeMB = $logSize / (1024 * 1024);
            
            $status = $logSizeMB < 100 ? 'PASS' : 'WARN';
            
            return [
                'status' => $status,
                'message' => "Log system working. Log size: " . round($logSizeMB, 2) . "MB",
                'details' => [
                    'path' => $logPath,
                    'size_mb' => round($logSizeMB, 2)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'FAIL',
                'message' => 'Log system error: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    private function checkEmailSystem()
    {
        try {
            $driver = config('mail.default');
            
            if ($driver === 'log') {
                return [
                    'status' => 'WARN',
                    'message' => 'Email system is using log driver (not recommended for production)',
                    'details' => ['driver' => $driver]
                ];
            }
            
            return [
                'status' => 'PASS',
                'message' => 'Email system is configured correctly',
                'details' => ['driver' => $driver]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'FAIL',
                'message' => 'Email system error: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    private function displayResults($checks)
    {
        $this->info("\n📊 Health Check Results:");
        $this->info("========================\n");
        
        foreach ($checks as $name => $check) {
            $status = $check['status'];
            $message = $check['message'];
            
            switch ($status) {
                case 'PASS':
                    $this->info("✅ {$name}: {$message}");
                    break;
                case 'WARN':
                    $this->warn("⚠️  {$name}: {$message}");
                    break;
                case 'FAIL':
                    $this->error("❌ {$name}: {$message}");
                    break;
            }
        }
        
        $this->info("\n");
    }
    
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    private function parseMemoryLimit($limit)
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
}
