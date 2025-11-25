<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QueueManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QueueController (Admin)
 * 
 * Admin dashboard for managing failed jobs and queue operations.
 */
class QueueController extends Controller
{
    public function __construct(
        private QueueManagementService $queueService
    ) {}
    
    /**
     * Display failed jobs dashboard
     */
    public function failedJobs(Request $request)
    {
        try {
            $queue = $request->input('queue');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $query = DB::table('failed_jobs');
            
            if ($queue) {
                $query->where('queue', $queue);
            }
            
            $failedJobs = $query->orderBy('failed_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Format failed jobs for display
            $formattedJobs = $failedJobs->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'job_data' => $payload['data'] ?? [],
                ];
            });
            
            return response()->json([
                'failed_jobs' => $formattedJobs,
                'pagination' => [
                    'current_page' => $failedJobs->currentPage(),
                    'last_page' => $failedJobs->lastPage(),
                    'per_page' => $failedJobs->perPage(),
                    'total' => $failedJobs->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get failed jobs', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to retrieve failed jobs',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
    
    /**
     * Retry a specific failed job
     */
    public function retryJob(Request $request, string $jobId)
    {
        try {
            $result = $this->queueService->retryJob($jobId);
            
            if ($result['success']) {
                return response()->json([
                    'ok' => true,
                    'message' => $result['message'],
                ]);
            }
            
            return response()->json([
                'ok' => false,
                'code' => 'JOB_RETRY_FAILED',
                'message' => $result['message'],
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to retry job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to retry job',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
    
    /**
     * Retry all failed jobs (optionally filtered by queue)
     */
    public function retryAll(Request $request)
    {
        try {
            $queue = $request->input('queue');
            $result = $this->queueService->retryAllFailedJobs($queue);
            
            return response()->json([
                'ok' => true,
                'message' => $result['message'],
                'retried_count' => $result['count'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retry all jobs', [
                'queue' => $request->input('queue'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to retry all jobs',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
    
    /**
     * Get error details for a failed job
     */
    public function getJobError(Request $request, string $jobId)
    {
        try {
            $job = DB::table('failed_jobs')->where('id', $jobId)->first();
            
            if (!$job) {
                return response()->json([
                    'ok' => false,
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Failed job not found',
                    'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                ], 404);
            }
            
            return response()->json([
                'ok' => true,
                'job' => [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get job error', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to retrieve job error',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
    
    /**
     * Delete a failed job
     */
    public function deleteJob(Request $request, string $jobId)
    {
        try {
            $deleted = DB::table('failed_jobs')->where('id', $jobId)->delete();
            
            if ($deleted) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Failed job deleted successfully',
                ]);
            }
            
            return response()->json([
                'ok' => false,
                'code' => 'JOB_NOT_FOUND',
                'message' => 'Failed job not found',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to delete job',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
    
    /**
     * Get queue statistics
     */
    public function stats(Request $request)
    {
        try {
            $stats = $this->queueService->getQueueStats();
            
            return response()->json([
                'ok' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get queue stats', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to retrieve queue statistics',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
}

