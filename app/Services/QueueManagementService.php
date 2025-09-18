<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;

class QueueManagementService
{
    protected $connection;
    protected $queues;

    public function __construct()
    {
        $this->connection = config('queue.default', 'redis');
        $this->queues = [
            'emails-high',
            'emails-medium', 
            'emails-low',
            'emails-welcome'
        ];
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        try {
            $stats = [
                'connection' => $this->connection,
                'queues' => [],
                'total_jobs' => 0,
                'total_failed' => 0,
                'workers' => $this->getActiveWorkers(),
            ];

            foreach ($this->queues as $queue) {
                $queueStats = $this->getQueueStatsForQueue($queue);
                $stats['queues'][$queue] = $queueStats;
                $stats['total_jobs'] += $queueStats['pending'];
                $stats['total_failed'] += $queueStats['failed'];
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get queue statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'connection' => $this->connection,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get statistics for specific queue
     */
    private function getQueueStatsForQueue(string $queue): array
    {
        try {
            if ($this->connection === 'redis') {
                return $this->getRedisQueueStats($queue);
            } elseif ($this->connection === 'database') {
                return $this->getDatabaseQueueStats($queue);
            } else {
                return [
                    'pending' => 0,
                    'failed' => 0,
                    'processing' => 0,
                    'size' => 0,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get queue stats for queue', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'pending' => 0,
                'failed' => 0,
                'processing' => 0,
                'size' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Redis queue statistics
     */
    private function getRedisQueueStats(string $queue): array
    {
        $redis = Redis::connection();
        
        $pendingKey = "queues:{$queue}";
        $failedKey = "queues:{$queue}:failed";
        $processingKey = "queues:{$queue}:processing";

        return [
            'pending' => $redis->llen($pendingKey),
            'failed' => $redis->llen($failedKey),
            'processing' => $redis->llen($processingKey),
            'size' => $redis->llen($pendingKey),
        ];
    }

    /**
     * Get database queue statistics
     */
    private function getDatabaseQueueStats(string $queue): array
    {
        $pending = \DB::table('jobs')
            ->where('queue', $queue)
            ->count();

        $failed = \DB::table('failed_jobs')
            ->where('queue', $queue)
            ->count();

        return [
            'pending' => $pending,
            'failed' => $failed,
            'processing' => 0, // Database doesn't track processing
            'size' => $pending,
        ];
    }

    /**
     * Get active workers
     */
    private function getActiveWorkers(): array
    {
        try {
            if ($this->connection === 'redis') {
                return $this->getRedisWorkers();
            } else {
                return [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get active workers', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get Redis workers
     */
    private function getRedisWorkers(): array
    {
        $redis = Redis::connection();
        $workers = [];

        // Get worker keys
        $workerKeys = $redis->keys('queues:*:workers');
        
        foreach ($workerKeys as $key) {
            $workerData = $redis->hgetall($key);
            if (!empty($workerData)) {
                $workers[] = [
                    'queue' => str_replace(['queues:', ':workers'], '', $key),
                    'pid' => $workerData['pid'] ?? null,
                    'started_at' => $workerData['started_at'] ?? null,
                    'processed' => $workerData['processed'] ?? 0,
                    'memory' => $workerData['memory'] ?? 0,
                ];
            }
        }

        return $workers;
    }

    /**
     * Clear failed jobs
     */
    public function clearFailedJobs(string $queue = null): bool
    {
        try {
            if ($this->connection === 'redis') {
                return $this->clearRedisFailedJobs($queue);
            } elseif ($this->connection === 'database') {
                return $this->clearDatabaseFailedJobs($queue);
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear Redis failed jobs
     */
    private function clearRedisFailedJobs(string $queue = null): bool
    {
        $redis = Redis::connection();
        
        if ($queue) {
            $failedKey = "queues:{$queue}:failed";
            $redis->del($failedKey);
        } else {
            foreach ($this->queues as $q) {
                $failedKey = "queues:{$q}:failed";
                $redis->del($failedKey);
            }
        }
        
        return true;
    }

    /**
     * Clear database failed jobs
     */
    private function clearDatabaseFailedJobs(string $queue = null): bool
    {
        if ($queue) {
            \DB::table('failed_jobs')->where('queue', $queue)->delete();
        } else {
            \DB::table('failed_jobs')->delete();
        }
        
        return true;
    }

    /**
     * Restart queue workers
     */
    public function restartWorkers(): bool
    {
        try {
            // Clear cache
            Cache::flush();
            
            // Restart queue workers
            $this->runCommand('queue:restart');
            
            Log::info('Queue workers restarted successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to restart queue workers', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Start queue workers
     */
    public function startWorkers(): bool
    {
        try {
            foreach ($this->queues as $queue) {
                $this->startWorkerForQueue($queue);
            }
            
            Log::info('Queue workers started successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to start queue workers', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Start worker for specific queue
     */
    private function startWorkerForQueue(string $queue): void
    {
        $command = [
            'php', 'artisan', 'queue:work',
            "--connection={$this->connection}",
            "--queue={$queue}",
            '--timeout=60',
            '--tries=3',
            '--max-jobs=1000',
            '--max-time=3600',
            '--sleep=3',
            '--daemon',
        ];

        Process::start($command);
    }

    /**
     * Stop queue workers
     */
    public function stopWorkers(): bool
    {
        try {
            $this->runCommand('queue:restart');
            Log::info('Queue workers stopped successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to stop queue workers', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Run artisan command
     */
    private function runCommand(string $command): void
    {
        Process::run(['php', 'artisan', $command]);
    }

    /**
     * Get queue health status
     */
    public function getHealthStatus(): array
    {
        $stats = $this->getQueueStats();
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => [],
        ];

        // Check for high pending jobs
        if ($stats['total_jobs'] > 1000) {
            $health['status'] = 'warning';
            $health['issues'][] = 'High number of pending jobs: ' . $stats['total_jobs'];
            $health['recommendations'][] = 'Consider adding more workers or optimizing job processing';
        }

        // Check for failed jobs
        if ($stats['total_failed'] > 100) {
            $health['status'] = 'critical';
            $health['issues'][] = 'High number of failed jobs: ' . $stats['total_failed'];
            $health['recommendations'][] = 'Investigate failed jobs and fix underlying issues';
        }

        // Check for active workers
        if (empty($stats['workers'])) {
            $health['status'] = 'critical';
            $health['issues'][] = 'No active queue workers';
            $health['recommendations'][] = 'Start queue workers to process jobs';
        }

        return $health;
    }
}
