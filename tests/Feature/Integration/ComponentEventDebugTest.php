<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Models\Project;
use App\Models\Component;
use Src\CoreProject\Events\ComponentProgressUpdated;

/**
 * Simple test để debug Component event dispatching
 */
class ComponentEventDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_update_dispatches_event(): void
    {
        // Don't fake events to see if they actually get dispatched
        // Event::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);

        $component = Component::factory()->create([
            'project_id' => $project->id,
            'progress_percent' => 0,
            'planned_cost' => 10000
        ]);

        // Listen for events
        $dispatchedEvents = [];
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $event;
        });

        // Trigger event
        $result = $component->update(['progress_percent' => 50]);
        
        // Check if update was successful
        $this->assertTrue($result, 'Component update should succeed');
        
        // Check if progress was actually updated
        $component->refresh();
        $this->assertEquals(50, $component->progress_percent, 'Progress should be updated to 50');
        
        // Check if event was dispatched
        $this->assertCount(1, $dispatchedEvents, 'ComponentProgressUpdated event should be dispatched');
        $this->assertEquals($component->id, $dispatchedEvents[0]->componentId, 'Event should have correct component ID');
        $this->assertEquals(50, $dispatchedEvents[0]->newProgress, 'Event should have correct new progress');
    }
}
