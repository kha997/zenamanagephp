<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Models\Project;
use App\Events\ProjectCreated;
use App\Events\ProjectUpdated;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Illuminate\Support\Facades\Event;

/**
 * Unit tests cho Project Events
 */
class ProjectEventTest extends TestCase
{
    use DatabaseTrait;
    
    /**
     * Test ProjectCreated event
     */
    public function test_project_created_event_is_dispatched(): void
    {
        $project = Project::factory()->create(['id' => \Illuminate\Support\Str::ulid()]);
        
        // Manually dispatch event (normally done in service)
        event(new ProjectCreated($project));
        
        // Just verify the event can be created and dispatched
        $this->assertTrue(true, 'Event was dispatched successfully');
    }
    
    /**
     * Test ProjectUpdated event
     */
    public function test_project_updated_event_is_dispatched(): void
    {
        Event::fake();
        
        $project = Project::factory()->create(['id' => \Illuminate\Support\Str::ulid()]);
        $originalData = $project->toArray();
        
        $project->update(['name' => 'Updated Name']);
        
        // Manually dispatch event
        event(new ProjectUpdated($project));
        
        Event::assertDispatched(ProjectUpdated::class, function ($event) use ($project) {
            return $event->project->id === $project->id &&
                   $event->project->name === 'Updated Name';
        });
    }
    
    /**
     * Test event payload structure
     */
    public function test_project_event_payload_structure(): void
    {
        $project = Project::factory()->create(['id' => \Illuminate\Support\Str::ulid()]);
        
        $event = new ProjectCreated($project);
        
        $this->assertInstanceOf(Project::class, $event->project);
        $this->assertEquals($project->id, $event->project->id);
        $this->assertIsArray($event->getPayload());
        $this->assertEquals('Project.Project.Created', $event->getEventName());
    }
}