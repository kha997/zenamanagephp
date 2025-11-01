<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\QueueManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QueueController extends Controller
{
    public function __construct(
        private QueueManagementService $queueService
    ) {}

    /**
     * Get queue statistics
     */
    public function getStats(Request $request)
    {
        try {
            $stats = $this->queueService->getQueueStats();
            
            return ApiResponse::success([
                'stats' => $stats,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::error('Failed to get queue statistics', 500);
        }
    }

    /**
     * Get queue metrics for monitoring
     */
    public function getMetrics(Request $request)
    {
        try {
            $metrics = $this->queueService->getQueueMetrics();
            
            return ApiResponse::success([
                'metrics' => $metrics,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue metrics', [
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error('Failed to get queue metrics', 500);
        }
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(Request $request)
    {
        try {
            $queue = $request->input('queue', 'default');
            $jobId = $request->input('job_id');
            
            if ($jobId) {
                $result = $this->queueService->retryJob($jobId);
            } else {
                $result = $this->queueService->retryAllFailedJobs($queue);
            }

            return ApiResponse::success([
                'message' => $result['message'],
                'retried_count' => $result['count'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retry jobs', [
                'error' => $e->getMessage(),
                'queue' => $request->input('queue'),
                'job_id' => $request->input('job_id')
            ]);

            return ApiResponse::error('Failed to retry jobs', 500);
        }
    }

    /**
     * Clear failed jobs
     */
    public function clearFailedJobs(Request $request)
    {
        try {
            $queue = $request->input('queue', 'default');
            $result = $this->queueService->clearFailedJobs($queue);

            return ApiResponse::success([
                'message' => $result['message'],
                'cleared_count' => $result['count'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', [
                'error' => $e->getMessage(),
                'queue' => $request->input('queue')
            ]);

            return ApiResponse::error('Failed to clear failed jobs', 500);
        }
    }

    /**
     * Get queue workers status
     */
    public function getWorkers(Request $request)
    {
        try {
            $workers = $this->queueService->getActiveWorkers();
            
            return ApiResponse::success([
                'workers' => $workers,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue workers', [
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error('Failed to get queue workers', 500);
        }
    }
}
