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
 * Test để kiểm tra event dispatching với Event::fake()
 */
class EventDispatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_update_dispatches_event_with_fake(): void
    {
        // Don't fake events to see if they actually get dispatched
        // Event::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'progress_pct' => 0,
            'budget_actual' => 0,
            'id' => \Illuminate\Support\Str::ulid()
        ]);

        $component = Component::factory()->create([
            'project_id' => $project->id,
            'progress_percent' => 0,
            'planned_cost' => 8000,
            'actual_cost' => 0,
            'parent_component_id' => null
        ]);

        // Update component
        $component->update(['progress_percent' => 50]);

        // Check if event was dispatched by listening for it
        $dispatchedEvents = [];
        Event::listen(ComponentProgressUpdated::class, function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $event;
        });

        // Update again to trigger event
        $component->update(['progress_percent' => 75]);

        // Check if event was dispatched
        $this->assertCount(1, $dispatchedEvents, 'ComponentProgressUpdated event should be dispatched');
        $this->assertEquals($component->id, $dispatchedEvents[0]->componentId);
        $this->assertEquals($project->id, $dispatchedEvents[0]->projectId);
        $this->assertEquals(75, $dispatchedEvents[0]->newProgress);
    }
}
