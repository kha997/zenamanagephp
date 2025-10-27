<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;

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
     * Get queue metrics for monitoring
     */
    public function getQueueMetrics(): array
    {
        try {
            $stats = $this->getQueueStats();
            
            $metrics = [
                'queue_jobs_total' => $stats['total_jobs'],
                'queue_jobs_failed_total' => $stats['total_failed'],
                'queue_jobs_processing' => array_sum(array_column($stats['queues'], 'processing')),
                'queue_workers_active' => count($stats['workers']),
                'queue_health_status' => $this->getHealthStatus()['status'],
                'timestamp' => now()->timestamp,
            ];

            // Add per-queue metrics
            foreach ($stats['queues'] as $queueName => $queueStats) {
                $metrics["queue_{$queueName}_pending"] = $queueStats['pending'];
                $metrics["queue_{$queueName}_failed"] = $queueStats['failed'];
                $metrics["queue_{$queueName}_processing"] = $queueStats['processing'];
            }

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get queue metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => $e->getMessage(),
                'timestamp' => now()->timestamp,
            ];
        }
    }

    /**
     * Retry a specific job
     */
    public function retryJob(string $jobId): array
    {
        try {
            if ($this->connection === 'database') {
                $job = \DB::table('failed_jobs')->where('id', $jobId)->first();
                if (!$job) {
                    return ['success' => false, 'message' => 'Job not found'];
                }

                // Move job back to jobs table
                \DB::table('jobs')->insert([
                    'queue' => $job->queue,
                    'payload' => $job->payload,
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => now()->timestamp,
                    'created_at' => now()->timestamp,
                ]);

                // Remove from failed jobs
                \DB::table('failed_jobs')->where('id', $jobId)->delete();

                return ['success' => true, 'message' => 'Job retried successfully', 'count' => 1];
            }

            return ['success' => false, 'message' => 'Retry not supported for this connection'];
        } catch (\Exception $e) {
            Log::error('Failed to retry job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'message' => 'Failed to retry job: ' . $e->getMessage()];
        }
    }

    /**
     * Retry all failed jobs for a queue
     */
    public function retryAllFailedJobs(string $queue = null): array
    {
        try {
            $count = 0;
            
            if ($this->connection === 'database') {
                $query = \DB::table('failed_jobs');
                if ($queue) {
                    $query->where('queue', $queue);
                }
                
                $failedJobs = $query->get();
                
                foreach ($failedJobs as $job) {
                    // Move job back to jobs table
                    \DB::table('jobs')->insert([
                        'queue' => $job->queue,
                        'payload' => $job->payload,
                        'attempts' => 0,
                        'reserved_at' => null,
                        'available_at' => now()->timestamp,
                        'created_at' => now()->timestamp,
                    ]);
                    
                    $count++;
                }
                
                // Remove from failed jobs
                $query->delete();
            }

            return ['success' => true, 'message' => "Retried {$count} failed jobs", 'count' => $count];
        } catch (\Exception $e) {
            Log::error('Failed to retry all failed jobs', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'message' => 'Failed to retry jobs: ' . $e->getMessage()];
        }
    }

    /**
     * Clear failed jobs with count
     */
    public function clearFailedJobs(string $queue = null): array
    {
        try {
            $count = 0;
            
            if ($this->connection === 'database') {
                $query = \DB::table('failed_jobs');
                if ($queue) {
                    $query->where('queue', $queue);
                }
                
                $count = $query->count();
                $query->delete();
            } elseif ($this->connection === 'redis') {
                $redis = Redis::connection();
                
                if ($queue) {
                    $failedKey = "queues:{$queue}:failed";
                    $count = $redis->llen($failedKey);
                    $redis->del($failedKey);
                } else {
                    foreach ($this->queues as $q) {
                        $failedKey = "queues:{$q}:failed";
                        $count += $redis->llen($failedKey);
                        $redis->del($failedKey);
                    }
                }
            }

            return ['success' => true, 'message' => "Cleared {$count} failed jobs", 'count' => $count];
        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'message' => 'Failed to clear failed jobs: ' . $e->getMessage()];
        }
    }

    /**
     * Get active workers with enhanced info
     */
    public function getActiveWorkers(): array
    {
        try {
            if ($this->connection === 'redis') {
                return $this->getRedisWorkers();
            } elseif ($this->connection === 'database') {
                return $this->getDatabaseWorkers();
            }
            
            return [];
        } catch (\Exception $e) {
            Log::warning('Failed to get active workers', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get database workers (simulated)
     */
    private function getDatabaseWorkers(): array
    {
        // For database connection, we can't easily track workers
        // Return empty array or simulate based on running processes
        return [];
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