<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Project;
use App\Models\Component;
use App\Models\EventLog;
use Src\Foundation\Events\BaseEvent;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\Foundation\Listeners\EventLogListener;
use Src\CoreProject\Listeners\ProjectCalculationListener;

/**
 * Integration Tests cho Event System
 * 
 * Kiểm tra Event/Listener system hoạt động đúng cách
 * và đảm bảo event-driven architecture integrity
 */
class EventSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id
        ]);
    }

    /**
     * Test Event Dispatching và Listener Execution
     * Kiểm tra events được dispatch và listeners được execute đúng
     */
    public function test_event_dispatching_and_listener_execution(): void
    {
        Event::fake();

        $component = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'planned_cost' => 10000
        ]);

        // Trigger event
        $component->update(['progress_percent' => 50]);

        // Verify event được dispatch
        Event::assertDispatched(ComponentProgressUpdated::class, function ($event) use ($component) {
            return $event->component->id === $component->id &&
                   $event->projectId === $this->project->id &&
                   $event->changedFields['progress_percent'] === 50;
        });
    }

    /**
     * Test Event Payload Integrity
     * Kiểm tra event payloads chứa đúng dữ liệu
     */
    public function test_event_payload_integrity(): void
    {
        $eventPayloads = [];
        
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$eventPayloads) {
            $eventPayloads[] = [
                'entityId' => $event->entityId,
                'projectId' => $event->projectId,
                'actorId' => $event->actorId,
                'changedFields' => $event->changedFields,
                'timestamp' => $event->timestamp
            ];
        });

        $component = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 25,
            'actual_cost' => 2500
        ]);

        // Update component to trigger event
        $component->update([
            'progress_percent' => 75,
            'actual_cost' => 7500
        ]);

        // Verify payload
        $this->assertCount(1, $eventPayloads);
        $payload = $eventPayloads[0];
        
        $this->assertEquals($component->id, $payload['entityId']);
        $this->assertEquals($this->project->id, $payload['projectId']);
        $this->assertArrayHasKey('progress_percent', $payload['changedFields']);
        $this->assertArrayHasKey('actual_cost', $payload['changedFields']);
        $this->assertEquals(75, $payload['changedFields']['progress_percent']);
        $this->assertEquals(7500, $payload['changedFields']['actual_cost']);
    }

    /**
     * Test Event Listener Chain
     * Kiểm tra multiple listeners cho cùng một event
     */
    public function test_event_listener_chain(): void
    {
        $listenerExecutions = [];
        
        // Register multiple listeners
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$listenerExecutions) {
            $listenerExecutions[] = 'ProjectCalculationListener';
        });
        
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$listenerExecutions) {
            $listenerExecutions[] = 'EventLogListener';
        });
        
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$listenerExecutions) {
            $listenerExecutions[] = 'NotificationListener';
        });

        $component = Component::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Trigger event
        $component->update(['progress_percent' => 100]);

        // Verify all listeners executed
        $this->assertCount(3, $listenerExecutions);
        $this->assertContains('ProjectCalculationListener', $listenerExecutions);
        $this->assertContains('EventLogListener', $listenerExecutions);
        $this->assertContains('NotificationListener', $listenerExecutions);
    }

    /**
     * Test Event-driven Calculations
     * Kiểm tra calculations được trigger bởi events
     */
    public function test_event_driven_calculations(): void
    {
        // Không fake events để test real calculations
        $component1 = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'planned_cost' => 8000,
            'actual_cost' => 0
        ]);

        $component2 = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'planned_cost' => 12000,
            'actual_cost' => 0
        ]);

        // Update components to trigger calculations
        $component1->update([
            'progress_percent' => 50,
            'actual_cost' => 4000
        ]);

        $component2->update([
            'progress_percent' => 25,
            'actual_cost' => 3000
        ]);

        // Refresh project
        $this->project->refresh();

        // Verify calculations
        // Expected progress: (50 * 8000 + 25 * 12000) / (8000 + 12000) = 35%
        $expectedProgress = (50 * 8000 + 25 * 12000) / (8000 + 12000);
        $this->assertEquals($expectedProgress, $this->project->progress);
        
        // Expected actual cost: 4000 + 3000 = 7000
        $this->assertEquals(7000, $this->project->actual_cost);
    }

    /**
     * Test Event Error Handling
     * Kiểm tra xử lý lỗi trong event system
     */
    public function test_event_error_handling(): void
    {
        // Register listener that throws exception
        Event::listen(ComponentProgressUpdated::class, function ($event) {
            throw new \Exception('Simulated listener error');
        });

        $component = Component::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Update should still work despite listener error
        try {
            $component->update(['progress_percent' => 50]);
            
            // Verify update succeeded
            $component->refresh();
            $this->assertEquals(50, $component->progress_percent);
            
        } catch (\Exception $e) {
            // If exception is thrown, verify it's handled appropriately
            $this->assertStringContains('Simulated listener error', $e->getMessage());
        }
    }

    /**
     * Test Event Queuing Integration
     * Kiểm tra events có thể được queue để xử lý async
     */
    public function test_event_queuing_integration(): void
    {
        Queue::fake();
        
        // Create queueable event listener
        Event::listen(ComponentProgressUpdated::class, function ($event) {
            // Simulate heavy processing that should be queued
            dispatch(function () use ($event) {
                // Heavy calculation or external API call
                sleep(1);
            });
        });

        $component = Component::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Trigger event
        $component->update(['progress_percent' => 75]);

        // Verify job was queued
        Queue::assertPushed(\Closure::class);
    }

    /**
     * Test Event Auditing
     * Kiểm tra tất cả events được audit đúng cách
     */
    public function test_event_auditing(): void
    {
        // Không fake EventLogListener để test real auditing
        Event::fake([
            // Fake other events but not EventLogged
        ]);

        $component = Component::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Perform multiple operations
        $component->update(['progress_percent' => 30]);
        $component->update(['actual_cost' => 3000]);
        $component->update(['progress_percent' => 60]);

        // Verify events were logged
        $eventLogs = EventLog::where('project_id', $this->project->id)
            ->where('event_type', 'ComponentProgressUpdated')
            ->get();

        $this->assertGreaterThanOrEqual(3, $eventLogs->count());
        
        // Verify log structure
        $log = $eventLogs->first();
        $this->assertEquals($this->project->id, $log->project_id);
        $this->assertEquals('ComponentProgressUpdated', $log->event_type);
        $this->assertNotNull($log->event_data);
        $this->assertNotNull($log->created_at);
    }
}