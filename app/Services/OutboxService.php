<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Outbox Service
 * 
 * Handles transactional outbox pattern for reliable event publishing.
 * Events are written to outbox table within the same transaction as
 * the main operation, ensuring they are published even if queue is down.
 */
class OutboxService
{
    /**
     * Add event to outbox within a transaction
     * 
     * @param string $eventType Event class name (e.g., 'ProjectUpdated')
     * @param string $eventName Event name (e.g., 'Project.Project.Updated')
     * @param array $payload Event payload
     * @param string|null $correlationId Request correlation ID
     * @return Outbox
     */
    public function addEvent(
        string $eventType,
        string $eventName,
        array $payload,
        ?string $correlationId = null
    ): Outbox {
        $tenantId = Auth::user()?->tenant_id;
        $correlationId = $correlationId ?? request()->header('X-Request-Id');

        return DB::transaction(function () use ($eventType, $eventName, $payload, $tenantId, $correlationId) {
            return Outbox::create([
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'event_name' => $eventName,
                'payload' => $payload,
                'status' => Outbox::STATUS_PENDING,
                'correlation_id' => $correlationId,
            ]);
        });
    }

    /**
     * Process pending events from outbox (idempotent)
     * 
     * @param int $limit Number of events to process
     * @return int Number of events processed
     */
    public function processPendingEvents(int $limit = 100): int
    {
        $events = Outbox::pending()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->lockForUpdate() // Prevent concurrent processing
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            try {
                // Idempotency check: skip if already processed
                // This ensures the consumer is idempotent - can be run multiple times safely
                if ($event->processed_at !== null && $event->status === Outbox::STATUS_COMPLETED) {
                    Log::debug('Outbox event already processed, skipping (idempotency)', [
                        'event_id' => $event->id,
                        'event_type' => $event->event_type,
                        'processed_at' => $event->processed_at,
                        'traceId' => $event->correlation_id,
                    ]);
                    continue;
                }
                
                // Also skip if currently being processed by another worker (within last 5 minutes)
                if ($event->status === Outbox::STATUS_PROCESSING) {
                    $processingAge = now()->diffInMinutes($event->updated_at);
                    if ($processingAge < 5) {
                        Log::debug('Outbox event being processed by another worker, skipping', [
                            'event_id' => $event->id,
                            'processing_age_minutes' => $processingAge,
                        ]);
                        continue;
                    }
                    // If processing for > 5 minutes, assume worker crashed and retry
                    Log::warning('Outbox event stuck in processing state, resetting to pending', [
                        'event_id' => $event->id,
                        'processing_age_minutes' => $processingAge,
                    ]);
                    $event->update(['status' => Outbox::STATUS_PENDING]);
                }
                
                $event->markAsProcessing();
                
                // Dispatch the event based on event_type
                $this->dispatchEvent($event);
                
                $event->markAsCompleted();
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process outbox event', [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'error' => $e->getMessage(),
                    'traceId' => $event->correlation_id,
                    'tenant_id' => $event->tenant_id,
                ]);
                
                $event->markAsFailed($e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Retry failed events that are retryable
     * 
     * @param int $limit Number of events to retry
     * @return int Number of events retried
     */
    public function retryFailedEvents(int $limit = 50): int
    {
        $events = Outbox::failedRetryable(3) // Max 3 retries
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->lockForUpdate()
            ->get();

        $retried = 0;

        foreach ($events as $event) {
            try {
                // Reset to pending for retry
                $event->update([
                    'status' => Outbox::STATUS_PENDING,
                    'error_message' => null,
                ]);
                
                $retried++;
            } catch (\Exception $e) {
                Log::error('Failed to reset outbox event for retry', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $retried;
    }

    /**
     * Dispatch event from outbox
     */
    private function dispatchEvent(Outbox $outboxEvent): void
    {
        $eventType = $outboxEvent->event_type;
        $payload = $outboxEvent->payload;

        // Handle search indexing events
        if ($eventType === 'ProjectUpdated' && isset($payload['project_id'])) {
            \App\Jobs\IndexProjectJob::dispatch($payload['project_id'])->onQueue('search');
            return;
        }

        if ($eventType === 'TaskUpdated' && isset($payload['task_id'])) {
            \App\Jobs\IndexTaskJob::dispatch($payload['task_id'])->onQueue('search');
            return;
        }

        if ($eventType === 'TaskMoved' && isset($payload['task_id'])) {
            \App\Jobs\IndexTaskJob::dispatch($payload['task_id'])->onQueue('search');
            return;
        }

        // Reconstruct event from payload
        if (class_exists($eventType)) {
            // For events that implement ShouldBroadcast or need special handling
            $event = new $eventType(...$payload);
            event($event);
        } else {
            // For simple events, dispatch via queue
            \Illuminate\Support\Facades\Queue::push(
                'App\Jobs\ProcessOutboxEventJob',
                [
                    'event_type' => $eventType,
                    'event_name' => $outboxEvent->event_name ?? null,
                    'payload' => $payload,
                    'tenant_id' => $outboxEvent->tenant_id,
                    'correlation_id' => $outboxEvent->correlation_id ?? null,
                ]
            );
        }
    }

    /**
     * Clean up old completed events
     * 
     * @param int $daysOld Delete events older than this many days
     * @return int Number of events deleted
     */
    public function cleanupOldEvents(int $daysOld = 7): int
    {
        return Outbox::where('status', Outbox::STATUS_COMPLETED)
            ->where('processed_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Get outbox metrics for monitoring
     * 
     * @return array
     */
    public function getMetrics(): array
    {
        $total = Outbox::count();
        $pending = Outbox::where('status', Outbox::STATUS_PENDING)->count();
        $processing = Outbox::where('status', Outbox::STATUS_PROCESSING)->count();
        $completed = Outbox::where('status', Outbox::STATUS_COMPLETED)->count();
        $failed = Outbox::where('status', Outbox::STATUS_FAILED)->count();
        $failedRetryable = Outbox::failedRetryable()->count();

        // Calculate average processing time for completed events in last hour
        $avgProcessingTime = Outbox::where('status', Outbox::STATUS_COMPLETED)
            ->where('processed_at', '>=', now()->subHour())
            ->whereNotNull('processed_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_seconds')
            ->value('avg_seconds') ?? 0;

        // Get oldest pending event age
        $oldestPending = Outbox::pending()
            ->orderBy('created_at', 'asc')
            ->first();
        
        $oldestPendingAge = $oldestPending 
            ? now()->diffInSeconds($oldestPending->created_at)
            : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'failed_retryable' => $failedRetryable,
            'avg_processing_time_seconds' => round((float) $avgProcessingTime, 2),
            'oldest_pending_age_seconds' => $oldestPendingAge,
            'queue_length' => $pending + $processing, // Events waiting to be processed
            'health_status' => $this->getHealthStatus($pending, $failed, $oldestPendingAge),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get health status based on metrics
     * 
     * @param int $pending
     * @param int $failed
     * @param int $oldestPendingAge
     * @return string
     */
    private function getHealthStatus(int $pending, int $failed, int $oldestPendingAge): string
    {
        // Unhealthy if too many pending events (> 1000)
        if ($pending > 1000) {
            return 'unhealthy';
        }

        // Unhealthy if too many failed events (> 100)
        if ($failed > 100) {
            return 'unhealthy';
        }

        // Degraded if oldest pending event is > 1 hour old
        if ($oldestPendingAge > 3600) {
            return 'degraded';
        }

        // Healthy otherwise
        return 'healthy';
    }

    /**
     * Get metrics per tenant
     * 
     * @param string|null $tenantId
     * @return array
     */
    public function getTenantMetrics(?string $tenantId = null): array
    {
        $query = Outbox::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', Outbox::STATUS_PENDING)->count(),
            'processing' => (clone $query)->where('status', Outbox::STATUS_PROCESSING)->count(),
            'completed' => (clone $query)->where('status', Outbox::STATUS_COMPLETED)->count(),
            'failed' => (clone $query)->where('status', Outbox::STATUS_FAILED)->count(),
            'tenant_id' => $tenantId,
            'timestamp' => now()->toISOString(),
        ];
    }
}

