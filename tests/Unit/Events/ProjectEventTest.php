<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ProjectCreated;
use App\Events\ProjectUpdated;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;

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
        $user = $this->createAuthenticatedUser($project->tenant);

        event(new ProjectCreated($project, $user));

        Event::assertDispatched(ProjectCreated::class, function ($event) use ($project, $user) {
            return $event->project->id === $project->id
                && $event->actor?->id === $user->id;
        });
    }
    
    /**
     * Test ProjectUpdated event
     */
    public function test_project_updated_event_is_dispatched(): void
    {
        Event::fake();

        $project = Project::factory()->create();
        $user = $this->createAuthenticatedUser($project->tenant);
        $originalData = $project->toArray();

        $project->update(['name' => 'Updated Name']);

        event(new ProjectUpdated($project, $originalData, $user));

        Event::assertDispatched(ProjectUpdated::class, function ($event) use ($project, $user) {
            return $event->project->id === $project->id
                && $event->project->name === 'Updated Name'
                && $event->actor?->id === $user->id;
        });
    }
    
    /**
     * Test event payload structure
     */
    public function test_project_event_payload_structure(): void
    {
        $project = Project::factory()->create();
        $user = $this->createAuthenticatedUser($project->tenant);

        $event = new ProjectCreated($project, $user);

        $this->assertInstanceOf(Project::class, $event->project);
        $this->assertSame($project->id, $event->project->id);
        $this->assertInstanceOf(User::class, $event->actor);
        $this->assertSame($user->id, $event->actor->id);
        $this->assertIsArray($event->toArray());
    }

    private function createAuthenticatedUser(?Tenant $tenant = null): User
    {
        $attributes = [];
        if ($tenant) {
            $attributes['tenant_id'] = $tenant->id;
        }

        $user = User::factory()->create($attributes);
        Auth::login($user);

        return $user;
    }
}
