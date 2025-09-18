<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Process;

class WorkerStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workers:status 
                            {--detailed : Show detailed information}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of queue workers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $status = $this->getWorkerStatus();
            
            if ($this->option('json')) {
                $this->line(json_encode($status, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->displayStatus($status);
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to get worker status: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get worker status
     */
    private function getWorkerStatus(): array
    {
        $status = [
            'timestamp' => now()->toISOString(),
            'queues' => [],
            'total_workers' => 0,
            'active_workers' => 0,
            'queue_stats' => [],
        ];

        $queues = ['emails-high', 'emails-medium', 'emails-low', 'emails-welcome'];

        foreach ($queues as $queue) {
            $queueStatus = $this->getQueueStatus($queue);
            $status['queues'][$queue] = $queueStatus;
            $status['total_workers'] += $queueStatus['workers'];
            $status['active_workers'] += $queueStatus['active_workers'];
            $status['queue_stats'][$queue] = $this->getQueueStats($queue);
        }

        return $status;
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(string $queue): array
    {
        $processFile = storage_path('app/workers.json');
        
        if (!file_exists($processFile)) {
            return [
                'workers' => 0,
                'active_workers' => 0,
                'processes' => [],
            ];
        }

        $data = json_decode(file_get_contents($processFile), true);
        $processes = $data['processes'] ?? [];
        
        $queueProcesses = array_filter($processes, function($process) use ($queue) {
            return $process['queue'] === $queue;
        });

        $activeWorkers = 0;
        foreach ($queueProcesses as $process) {
            if ($this->isProcessRunning($process['pid'])) {
                $activeWorkers++;
            }
        }

        return [
            'workers' => count($queueProcesses),
            'active_workers' => $activeWorkers,
            'processes' => $queueProcesses,
        ];
    }

    /**
     * Get queue statistics
     */
    private function getQueueStats(string $queue): array
    {
        try {
            $redis = Redis::connection();
            
            $pendingKey = "queues:{$queue}";
            $failedKey = "queues:{$queue}:failed";
            $processingKey = "queues:{$queue}:processing";

            return [
                'pending' => $redis->llen($pendingKey),
                'failed' => $redis->llen($failedKey),
                'processing' => $redis->llen($processingKey),
            ];
        } catch (\Exception $e) {
            return [
                'pending' => 0,
                'failed' => 0,
                'processing' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if process is running
     */
    private function isProcessRunning(int $pid): bool
    {
        try {
            $result = Process::run(['ps', '-p', $pid]);
            return $result->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Display status
     */
    private function displayStatus(array $status): void
    {
        $this->info('🚀 Queue Workers Status');
        $this->newLine();

        // Summary
        $this->info('📊 Summary:');
        $this->table(['Metric', 'Value'], [
            ['Total Workers', $status['total_workers']],
            ['Active Workers', $status['active_workers']],
            ['Inactive Workers', $status['total_workers'] - $status['active_workers']],
            ['Last Updated', $status['timestamp']],
        ]);

        $this->newLine();

        // Queue details
        $this->info('📋 Queue Details:');
        $queueData = [];
        foreach ($status['queues'] as $queue => $queueStatus) {
            $stats = $status['queue_stats'][$queue];
            $queueData[] = [
                'Queue' => $queue,
                'Workers' => $queueStatus['active_workers'] . '/' . $queueStatus['workers'],
                'Pending' => $stats['pending'],
                'Failed' => $stats['failed'],
                'Processing' => $stats['processing'],
            ];
        }

        $this->table(['Queue', 'Workers', 'Pending', 'Failed', 'Processing'], $queueData);

        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('🔍 Detailed Process Information:');
            
            foreach ($status['queues'] as $queue => $queueStatus) {
                if (!empty($queueStatus['processes'])) {
                    $this->line("Queue: {$queue}");
                    foreach ($queueStatus['processes'] as $process) {
                        $status = $this->isProcessRunning($process['pid']) ? '🟢 Active' : '🔴 Inactive';
                        $this->line("  Worker {$process['worker']}: PID {$process['pid']} {$status}");
                    }
                    $this->newLine();
                }
            }
        }

        // Health status
        $this->newLine();
        $healthStatus = $this->getHealthStatus($status);
        $this->info("💚 Health Status: {$healthStatus}");
    }

    /**
     * Get health status
     */
    private function getHealthStatus(array $status): string
    {
        if ($status['active_workers'] === 0) {
            return '🔴 Critical - No active workers';
        }

        if ($status['active_workers'] < $status['total_workers']) {
            return '🟡 Warning - Some workers inactive';
        }

        return '🟢 Healthy - All workers active';
    }
}