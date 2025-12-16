<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Outbox;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Outbox Tests
 * 
 * Tests transactional outbox pattern for reliable event publishing.
 * Ensures events are persisted and can be recovered even if worker is down.
 */
class OutboxTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private OutboxService $outboxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->outboxService = app(OutboxService::class);
    }

    /**
     * Test that events are persisted to outbox within transaction
     */
    public function test_events_persisted_to_outbox(): void
    {
        $this->actingAs($this->user);

        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        // Add event to outbox within transaction
        DB::transaction(function () use ($project) {
            $project->update(['name' => 'Updated Project']);
            
            $this->outboxService->addEvent(
                'ProjectUpdated',
                'Project.Project.Updated',
                [
                    'project_id' => $project->id,
                    'tenant_id' => $project->tenant_id,
                    'name' => $project->name,
                ],
                'test_correlation_id'
            );
        });

        // Verify event was persisted
        $outboxEvent = Outbox::where('event_type', 'ProjectUpdated')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($outboxEvent, 'Event should be persisted to outbox');
        $this->assertEquals(Outbox::STATUS_PENDING, $outboxEvent->status);
        $this->assertEquals('test_correlation_id', $outboxEvent->correlation_id);
    }

    /**
     * Test that events persist even if transaction rolls back (outbox should rollback too)
     */
    public function test_outbox_rollback_on_transaction_failure(): void
    {
        $this->actingAs($this->user);

        try {
            DB::transaction(function () {
                $this->outboxService->addEvent(
                    'TestEvent',
                    'Test.Event',
                    ['test' => 'data'],
                    'test_correlation_id'
                );
                
                // Force transaction to fail
                throw new \Exception('Transaction failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Verify event was NOT persisted (rolled back)
        $outboxEvent = Outbox::where('event_type', 'TestEvent')->first();
        $this->assertNull($outboxEvent, 'Event should not be persisted if transaction rolls back');
    }

    /**
     * Test that worker can process pending events
     */
    public function test_worker_processes_pending_events(): void
    {
        Queue::fake();

        // Create pending event
        $outboxEvent = Outbox::create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'ProjectUpdated',
            'event_name' => 'Project.Project.Updated',
            'payload' => ['project_id' => 'test123'],
            'status' => Outbox::STATUS_PENDING,
            'correlation_id' => 'test_correlation_id',
        ]);

        // Process pending events
        $processed = $this->outboxService->processPendingEvents(100);

        $this->assertEquals(1, $processed, 'Should process 1 pending event');

        // Verify event was marked as completed
        $outboxEvent->refresh();
        $this->assertEquals(Outbox::STATUS_COMPLETED, $outboxEvent->status);
        $this->assertNotNull($outboxEvent->processed_at);
    }

    /**
     * Test that consumer is idempotent (can process same event multiple times safely)
     */
    public function test_consumer_is_idempotent(): void
    {
        Queue::fake();

        // Create completed event
        $outboxEvent = Outbox::create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'ProjectUpdated',
            'event_name' => 'Project.Project.Updated',
            'payload' => ['project_id' => 'test123'],
            'status' => Outbox::STATUS_COMPLETED,
            'processed_at' => now(),
            'correlation_id' => 'test_correlation_id',
        ]);

        // Try to process again (should skip)
        $processed = $this->outboxService->processPendingEvents(100);

        $this->assertEquals(0, $processed, 'Should skip already processed event');

        // Verify event remains completed
        $outboxEvent->refresh();
        $this->assertEquals(Outbox::STATUS_COMPLETED, $outboxEvent->status);
    }

    /**
     * Test that events are not lost when worker is down
     */
    public function test_events_not_lost_when_worker_down(): void
    {
        // Create multiple pending events
        for ($i = 0; $i < 5; $i++) {
            Outbox::create([
                'tenant_id' => $this->tenant->id,
                'event_type' => 'ProjectUpdated',
                'event_name' => 'Project.Project.Updated',
                'payload' => ['project_id' => "test{$i}"],
                'status' => Outbox::STATUS_PENDING,
                'correlation_id' => "test_correlation_{$i}",
            ]);
        }

        // Simulate worker being down - events remain in outbox
        $pendingCount = Outbox::where('status', Outbox::STATUS_PENDING)->count();
        $this->assertEquals(5, $pendingCount, 'Events should remain in outbox when worker is down');

        // When worker comes back up, it can process all events
        Queue::fake();
        $processed = $this->outboxService->processPendingEvents(100);

        $this->assertEquals(5, $processed, 'Worker should process all pending events when restarted');
    }

    /**
     * Test retry mechanism for failed events
     */
    public function test_retry_failed_events(): void
    {
        // Create failed event that is retryable
        $outboxEvent = Outbox::create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'ProjectUpdated',
            'event_name' => 'Project.Project.Updated',
            'payload' => ['project_id' => 'test123'],
            'status' => Outbox::STATUS_FAILED,
            'retry_count' => 1, // Less than max (3)
            'error_message' => 'Temporary error',
            'correlation_id' => 'test_correlation_id',
        ]);

        // Retry failed events
        $retried = $this->outboxService->retryFailedEvents(50);

        $this->assertEquals(1, $retried, 'Should retry 1 failed event');

        // Verify event was reset to pending
        $outboxEvent->refresh();
        $this->assertEquals(Outbox::STATUS_PENDING, $outboxEvent->status);
        $this->assertNull($outboxEvent->error_message);
    }

    /**
     * Test that events are not retried after max retries
     */
    public function test_events_not_retried_after_max_retries(): void
    {
        // Create failed event with max retries exceeded
        $outboxEvent = Outbox::create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'ProjectUpdated',
            'event_name' => 'Project.Project.Updated',
            'payload' => ['project_id' => 'test123'],
            'status' => Outbox::STATUS_FAILED,
            'retry_count' => 3, // Max retries reached
            'error_message' => 'Permanent error',
            'correlation_id' => 'test_correlation_id',
        ]);

        // Try to retry
        $retried = $this->outboxService->retryFailedEvents(50);

        $this->assertEquals(0, $retried, 'Should not retry event after max retries');

        // Verify event remains failed
        $outboxEvent->refresh();
        $this->assertEquals(Outbox::STATUS_FAILED, $outboxEvent->status);
        $this->assertEquals(3, $outboxEvent->retry_count);
    }

    /**
     * Test outbox metrics
     */
    public function test_outbox_metrics(): void
    {
        // Create events in different states
        Outbox::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => Outbox::STATUS_PENDING,
        ]);
        
        Outbox::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => Outbox::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
        
        Outbox::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'status' => Outbox::STATUS_FAILED,
            'retry_count' => 1,
        ]);

        $metrics = $this->outboxService->getMetrics();

        $this->assertEquals(6, $metrics['total']);
        $this->assertEquals(3, $metrics['pending']);
        $this->assertEquals(2, $metrics['completed']);
        $this->assertEquals(1, $metrics['failed']);
        $this->assertArrayHasKey('health_status', $metrics);
    }
}
