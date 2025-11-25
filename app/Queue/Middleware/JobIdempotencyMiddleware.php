<?php declare(strict_types=1);

namespace App\Queue\Middleware;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job Idempotency Middleware
 * 
 * PR: Job idempotency
 * 
 * Ensures jobs are idempotent by checking idempotency key before execution.
 * Format: {tenant}_{user}_{action}_{payloadHash}
 */
class JobIdempotencyMiddleware
{
    /**
     * TTL for idempotency keys (24 hours)
     */
    private const IDEMPOTENCY_TTL = 86400;

    /**
     * Process the queued job.
     */
    public function handle(Job $job, callable $next): mixed
    {
        // Get idempotency key from job payload
        $idempotencyKey = $this->extractIdempotencyKey($job);

        // If no idempotency key, allow job to proceed
        if (!$idempotencyKey) {
            return $next($job);
        }

        // Check if job was already processed
        if ($this->isAlreadyProcessed($idempotencyKey)) {
            Log::info('Job idempotency check: job already processed', [
                'idempotency_key' => $idempotencyKey,
                'job_class' => $job->getName(),
                'job_id' => $job->getJobId(),
            ]);

            // Delete job from queue (already processed)
            $job->delete();

            return null; // Job already processed, skip execution
        }

        // Mark as processing
        $this->markAsProcessing($idempotencyKey);

        try {
            // Execute job
            $result = $next($job);

            // Mark as completed
            $this->markAsCompleted($idempotencyKey);

            return $result;
        } catch (\Throwable $e) {
            // Mark as failed (but allow retry)
            $this->markAsFailed($idempotencyKey, $e);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Extract idempotency key from job payload
     */
    protected function extractIdempotencyKey(Job $job): ?string
    {
        $payload = $job->payload();

        // Try to get from job data
        $jobData = $payload['data']['command'] ?? $payload['data'] ?? null;

        if (is_string($jobData)) {
            $jobData = unserialize($jobData);
        }

        // Check for idempotency_key property
        if (is_object($jobData) && isset($jobData->idempotencyKey)) {
            return $jobData->idempotencyKey;
        }

        // Check for idempotency_key in array
        if (is_array($jobData) && isset($jobData['idempotency_key'])) {
            return $jobData['idempotency_key'];
        }

        // Try to generate from job properties
        return $this->generateIdempotencyKey($job, $jobData);
    }

    /**
     * Generate idempotency key from job
     * Format: {tenant}_{user}_{action}_{payloadHash}
     */
    protected function generateIdempotencyKey(Job $job, $jobData): ?string
    {
        $tenantId = null;
        $userId = null;
        $action = $this->getJobAction($job);
        $payloadHash = $this->getPayloadHash($jobData);

        // Extract tenant_id and user_id from job data
        if (is_object($jobData)) {
            $tenantId = $jobData->tenantId ?? $jobData->tenant_id ?? null;
            $userId = $jobData->userId ?? $jobData->user_id ?? null;
        } elseif (is_array($jobData)) {
            $tenantId = $jobData['tenantId'] ?? $jobData['tenant_id'] ?? null;
            $userId = $jobData['userId'] ?? $jobData['user_id'] ?? null;
        }

        if (!$action || !$payloadHash) {
            return null;
        }

        // Format: {tenant}_{user}_{action}_{payloadHash}
        $parts = array_filter([$tenantId, $userId, $action, $payloadHash]);
        return implode('_', $parts);
    }

    /**
     * Get job action name
     */
    protected function getJobAction(Job $job): ?string
    {
        $jobName = $job->getName();
        
        // Extract class name from full namespace
        $parts = explode('\\', $jobName);
        $className = end($parts);

        // Convert to snake_case action
        $action = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        
        return $action;
    }

    /**
     * Get payload hash
     */
    protected function getPayloadHash($jobData): ?string
    {
        if (!$jobData) {
            return null;
        }

        // Serialize and hash
        $serialized = serialize($jobData);
        return substr(hash('sha256', $serialized), 0, 16); // 16 chars for shorter key
    }

    /**
     * Check if job was already processed
     */
    protected function isAlreadyProcessed(string $idempotencyKey): bool
    {
        // Check cache first
        $cacheKey = "job_idempotency:{$idempotencyKey}";
        $status = Cache::get($cacheKey);

        if ($status === 'completed') {
            return true;
        }

        // Check database
        try {
            $record = DB::table('job_idempotency_keys')
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', 'completed')
                ->first();

            if ($record) {
                // Cache it
                Cache::put($cacheKey, 'completed', self::IDEMPOTENCY_TTL);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check job idempotency in database', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        return false;
    }

    /**
     * Mark job as processing
     */
    protected function markAsProcessing(string $idempotencyKey): void
    {
        $cacheKey = "job_idempotency:{$idempotencyKey}";
        Cache::put($cacheKey, 'processing', 3600); // 1 hour TTL for processing

        try {
            DB::table('job_idempotency_keys')->updateOrInsert(
                ['idempotency_key' => $idempotencyKey],
                [
                    'status' => 'processing',
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to mark job as processing in database', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }
    }

    /**
     * Mark job as completed
     */
    protected function markAsCompleted(string $idempotencyKey): void
    {
        $cacheKey = "job_idempotency:{$idempotencyKey}";
        Cache::put($cacheKey, 'completed', self::IDEMPOTENCY_TTL);

        try {
            DB::table('job_idempotency_keys')->updateOrInsert(
                ['idempotency_key' => $idempotencyKey],
                [
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to mark job as completed in database', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }
    }

    /**
     * Mark job as failed
     */
    protected function markAsFailed(string $idempotencyKey, \Throwable $exception): void
    {
        $cacheKey = "job_idempotency:{$idempotencyKey}";
        Cache::put($cacheKey, 'failed', 3600); // 1 hour TTL for failed (allows retry)

        try {
            DB::table('job_idempotency_keys')->updateOrInsert(
                ['idempotency_key' => $idempotencyKey],
                [
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to mark job as failed in database', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }
    }
}

