<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Models\Project;
use App\Models\User;
use Src\CoreProject\Events\ProjectCreated;
use Src\CoreProject\Events\ProjectUpdated;
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
        Event::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();

        // Manually dispatch event (normally done in service)
        event(new ProjectCreated($project, $user));

        Event::assertDispatched(ProjectCreated::class, function ($event) use ($project, $user) {
            return $event->project->id === $project->id &&
                   $event->user?->id === $user->id;
        });
    }
    
    /**
     * Test ProjectUpdated event
     */
    public function test_project_updated_event_is_dispatched(): void
    {
        Event::fake();
        
        $project = Project::factory()->create();
        $originalData = $project->toArray();
        $user = User::factory()->create();

        $project->update(['name' => 'Updated Name']);
        $changedFields = ['name'];

        // Manually dispatch event
        event(new ProjectUpdated($project, $originalData, $changedFields, $user));

        Event::assertDispatched(ProjectUpdated::class, function ($event) use ($project, $user) {
            return $event->project->id === $project->id &&
                   $event->project->name === 'Updated Name' &&
                   $event->user?->id === $user->id;
        });
    }
    
    /**
     * Test event payload structure
     */
    public function test_project_event_payload_structure(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $event = new ProjectCreated($project, $user);

        $this->assertInstanceOf(Project::class, $event->project);
        $this->assertEquals($project->id, $event->project->id);
        $this->assertEquals($user->id, $event->user->id);
        $this->assertIsArray($event->toArray());
    }
}
