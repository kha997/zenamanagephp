<?php declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\JobRetryPolicyService;
use App\Services\JobThrottlingService;
use App\Services\TenantContext;

/**
 * Base Idempotent Job
 * 
 * PR: Job idempotency
 * 
 * Base class for jobs that require idempotency, retry policy, and throttling.
 * Provides standardized idempotency key generation and retry handling.
 */
abstract class BaseIdempotentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Idempotency key (auto-generated if not provided)
     */
    public ?string $idempotencyKey = null;

    /**
     * Tenant ID
     */
    protected ?string $tenantId = null;

    /**
     * User ID
     */
    protected ?string $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $tenantId = null, ?string $userId = null, ?string $idempotencyKey = null)
    {
        $this->tenantId = $tenantId ?? TenantContext::getTenantId();
        $this->userId = $userId ?? TenantContext::getUserId();
        $this->idempotencyKey = $idempotencyKey ?? $this->generateIdempotencyKey();

        // Set retry policy
        $retryPolicy = app(JobRetryPolicyService::class);
        $this->tries = config('queue.retry.max_tries', 3);
        $backoffs = $retryPolicy->getBackoffArray($this->tries);
        $this->backoff = $backoffs[0] ?? 60;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set tenant context
        if ($this->tenantId) {
            TenantContext::set($this->tenantId, $this->userId);
        }

        // Check throttling before execution
        $throttlingService = app(JobThrottlingService::class);
        if (!$throttlingService->canDispatch($this->tenantId, $this->queue)) {
            Log::warning('Job throttled', [
                'job_class' => static::class,
                'tenant_id' => $this->tenantId,
                'queue' => $this->queue,
            ]);

            // Release job back to queue with delay
            $this->release(60); // Retry after 1 minute
            return;
        }

        // Record dispatch for throttling
        $throttlingService->recordDispatch($this->tenantId, $this->queue);

        try {
            // Execute job logic
            $this->execute();
        } finally {
            // Clear tenant context
            TenantContext::clear();
        }
    }

    /**
     * Execute the job logic - to be implemented by child classes
     */
    abstract protected function execute(): void;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Uses exponential backoff.
     */
    public function backoff(): array
    {
        $retryPolicy = app(JobRetryPolicyService::class);
        return $retryPolicy->getBackoffArray($this->tries);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed after max retries', [
            'job_class' => static::class,
            'job_id' => $this->job->getJobId() ?? null,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'idempotency_key' => $this->idempotencyKey,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage(),
        ]);

        // Move to dead letter queue
        $retryPolicy = app(JobRetryPolicyService::class);
        $retryPolicy->moveToDeadLetterQueue(
            $this->job->getJobId() ?? uniqid('job_', true),
            static::class,
            $this->getJobPayload(),
            $exception
        );
    }

    /**
     * Generate idempotency key
     * Format: {tenant}_{user}_{action}_{payloadHash}
     */
    protected function generateIdempotencyKey(): string
    {
        $action = $this->getActionName();
        $payloadHash = $this->getPayloadHash();

        $parts = array_filter([
            $this->tenantId,
            $this->userId,
            $action,
            $payloadHash,
        ]);

        return implode('_', $parts);
    }

    /**
     * Get action name from job class
     */
    protected function getActionName(): string
    {
        $className = class_basename(static::class);
        // Convert PascalCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Get payload hash
     */
    protected function getPayloadHash(): string
    {
        $payload = $this->getJobPayload();
        $serialized = serialize($payload);
        return substr(hash('sha256', $serialized), 0, 16);
    }

    /**
     * Get job payload for hashing
     */
    protected function getJobPayload(): array
    {
        // Get all public properties
        $properties = get_object_vars($this);
        
        // Remove Laravel job properties
        unset($properties['job'], $properties['connection'], $properties['queue']);

        return $properties;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}

