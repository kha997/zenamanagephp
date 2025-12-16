<?php declare(strict_types=1);

namespace App\Jobs;

use App\Services\OutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Outbox Job
 * 
 * Processes pending events from the outbox table.
 * This job should be run periodically (e.g., every minute) to ensure
 * events are published even if the queue was temporarily down.
 * 
 * Features:
 * - Idempotent: Can be run multiple times safely
 * - Retry policy: Exponential backoff with max retries
 * - Dead-letter queue: Failed events after max retries are marked as failed
 */
class ProcessOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of events to process per run
     */
    public int $limit;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60; // Start with 60 seconds

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 100)
    {
        $this->limit = $limit;
        $this->onQueue('outbox'); // Dedicated queue for outbox processing
    }

    /**
     * Execute the job.
     * 
     * Idempotent: Can be run multiple times safely.
     * Each event is processed with lockForUpdate to prevent concurrent processing.
     */
    public function handle(OutboxService $outboxService): void
    {
        try {
            $processed = $outboxService->processPendingEvents($this->limit);
            
            // Also retry failed events that are retryable
            $retried = $outboxService->retryFailedEvents((int) ($this->limit * 0.5));
            
            Log::info('Outbox events processed', [
                'processed_count' => $processed,
                'retried_count' => $retried,
                'limit' => $this->limit,
                'traceId' => request()->header('X-Request-Id'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process outbox events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'traceId' => request()->header('X-Request-Id'),
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Exponential backoff: 60s, 120s, 240s
     */
    public function backoff(): array
    {
        return [
            60,   // First retry after 1 minute
            120,  // Second retry after 2 minutes
            240,  // Third retry after 4 minutes
        ];
    }

    /**
     * Handle a job failure.
     * 
     * After max retries, events remain in outbox for manual review.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessOutboxJob failed after max retries', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'limit' => $this->limit,
            'traceId' => request()->header('X-Request-Id'),
        ]);
        
        // Don't delete the job - let it remain in failed_jobs table for monitoring
        // The outbox events themselves remain in outbox table for manual retry
    }
}
