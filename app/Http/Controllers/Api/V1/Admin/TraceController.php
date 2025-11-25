<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class TraceController extends Controller
{
    /**
     * Get trace information by trace ID
     * 
     * Queries logs, database, and queue to find all events related to a trace ID.
     * Returns timeline of request with logs, DB queries, queue jobs.
     * 
     * @param Request $request
     * @param string $traceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $traceId)
    {
        // Only super admin can access trace lookup
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'ok' => false,
                'code' => 'FORBIDDEN',
                'message' => 'Only super admin can access trace lookup',
                'traceId' => $traceId,
            ], 403);
        }

        $timeline = [];
        
        // 1. Search logs
        $logs = $this->searchLogs($traceId);
        foreach ($logs as $log) {
            $timeline[] = [
                'timestamp' => $log['timestamp'] ?? now()->toISOString(),
                'type' => 'log',
                'level' => $log['level'] ?? 'info',
                'message' => $log['message'] ?? '',
                'context' => $log['context'] ?? [],
            ];
        }

        // 2. Search database query logs (if enabled)
        $queries = $this->searchQueryLogs($traceId);
        foreach ($queries as $query) {
            $timeline[] = [
                'timestamp' => $query['created_at'] ?? now()->toISOString(),
                'type' => 'query',
                'sql' => $query['sql'] ?? '',
                'bindings' => $query['bindings'] ?? [],
                'time' => $query['time'] ?? 0,
            ];
        }

        // 3. Search queue jobs (if trace ID is stored in job payload)
        $jobs = $this->searchQueueJobs($traceId);
        foreach ($jobs as $job) {
            $timeline[] = [
                'timestamp' => $job['created_at'] ?? now()->toISOString(),
                'type' => 'queue_job',
                'job' => $job['queue'] ?? 'default',
                'payload' => $job['payload'] ?? [],
                'status' => $job['status'] ?? 'pending',
            ];
        }

        // Sort timeline by timestamp
        usort($timeline, function ($a, $b) {
            return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
        });

        return response()->json([
            'ok' => true,
            'trace_id' => $traceId,
            'timeline' => $timeline,
            'summary' => [
                'total_events' => count($timeline),
                'logs' => count($logs),
                'queries' => count($queries),
                'jobs' => count($jobs),
            ],
        ]);
    }

    /**
     * Search logs for trace ID
     */
    private function searchLogs(string $traceId): array
    {
        $logs = [];
        $logPath = storage_path('logs');
        
        if (!File::isDirectory($logPath)) {
            return $logs;
        }

        // Search in recent log files (last 7 days)
        $logFiles = File::glob($logPath . '/laravel-*.log');
        $logFiles = array_slice($logFiles, -7); // Last 7 log files

        foreach ($logFiles as $logFile) {
            $content = File::get($logFile);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (str_contains($line, $traceId)) {
                    // Try to parse JSON log entry
                    $jsonStart = strpos($line, '{');
                    if ($jsonStart !== false) {
                        $json = substr($line, $jsonStart);
                        $decoded = json_decode($json, true);
                        if ($decoded) {
                            $logs[] = $decoded;
                        }
                    }
                }
            }
        }

        return $logs;
    }

    /**
     * Search query logs for trace ID
     */
    private function searchQueryLogs(string $traceId): array
    {
        if (!Schema::hasTable('query_logs')) {
            return [];
        }

        return DB::table('query_logs')
            ->where('trace_id', $traceId)
            ->orWhere('context', 'like', "%{$traceId}%")
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Search queue jobs for trace ID
     */
    private function searchQueueJobs(string $traceId): array
    {
        $jobs = [];

        // Search in jobs table
        if (Schema::hasTable('jobs')) {
            $dbJobs = DB::table('jobs')
                ->where('payload', 'like', "%{$traceId}%")
                ->get();
            
            foreach ($dbJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobs[] = [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'payload' => $payload,
                    'created_at' => $job->created_at,
                    'status' => 'pending',
                ];
            }
        }

        // Search in failed_jobs table
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')
                ->where('payload', 'like', "%{$traceId}%")
                ->get();
            
            foreach ($failedJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobs[] = [
                    'id' => $job->id,
                    'queue' => $job->queue ?? 'default',
                    'payload' => $payload,
                    'created_at' => $job->failed_at,
                    'status' => 'failed',
                    'exception' => $job->exception,
                ];
            }
        }

        return $jobs;
    }
}
