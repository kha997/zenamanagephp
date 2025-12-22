<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;
    protected $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->tenant = Tenant::create([
            'id' => 'test-tenant-1',
            'name' => 'Test Tenant',
            'domain' => 'test.zenamanage.com',
            'slug' => 'test-tenant',
            'status' => 'trial',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => 'test-user-1',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->project = Project::create([
            'id' => 'test-project-1',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'status' => 'active',
            'owner_id' => $this->user->id,
            'priority' => 'normal',
            'progress_pct' => 0,
        ]);

        $this->task = Task::create([
            'id' => 'test-task-1',
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8.0,
            'actual_hours' => 0.0,
            'progress_percent' => 0.0,
        ]);
    }

    /** @test */
    public function it_can_create_calendar_event()
    {
        $eventData = [
            'title' => 'Test Meeting',
            'description' => 'Test meeting description',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'location' => 'Conference Room A',
            'status' => 'confirmed',
            'all_day' => false,
            'project_id' => $this->project->id,
        ];

        $event = CalendarEvent::create([
            'id' => 'test-event-1',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'title' => 'Test Meeting',
            'description' => 'Test meeting description',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'location' => 'Conference Room A',
            'status' => 'confirmed',
            'all_day' => false,
            'project_id' => $this->project->id,
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'Test Meeting',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals('Test Meeting', $event->title);
        $this->assertEquals($this->project->id, $event->project_id);
        $this->assertEquals('confirmed', $event->status);
    }

    /** @test */
    public function it_can_create_event_with_task()
    {
        $event = CalendarEvent::create([
            'id' => 'test-event-2',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'title' => 'Task Deadline',
            'description' => 'Complete test task',
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
            'all_day' => true,
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'task_id' => $this->task->id,
            'project_id' => $this->project->id,
        ]);

        $this->assertEquals($this->task->id, $event->task_id);
        $this->assertTrue($event->all_day);
    }


    /** @test */
    public function it_can_load_project_and_task_relationships()
    {
        $event = CalendarEvent::create([
            'id' => 'test-event-3',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'title' => 'Project Task Event',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);

        $eventWithRelations = CalendarEvent::with(['project', 'task'])->find($event->id);

        $this->assertNotNull($eventWithRelations->project);
        $this->assertNotNull($eventWithRelations->task);
        $this->assertEquals('Test Project', $eventWithRelations->project->name);
        $this->assertEquals('Test Task', $eventWithRelations->task->name);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::create([
            'id' => 'other-tenant-1',
            'name' => 'Other Tenant',
            'domain' => 'other.zenamanage.com',
            'slug' => 'other-tenant',
            'status' => 'trial',
            'is_active' => true,
        ]);

        // Create event for other tenant
        CalendarEvent::create([
            'id' => 'other-event-1',
            'tenant_id' => $otherTenant->id,
            'user_id' => 'other-user-1',
            'title' => 'Other Tenant Event',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);

        // Query events for our tenant
        $events = CalendarEvent::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(0, $events);
        
        // Verify the other tenant's event exists
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Other Tenant Event',
            'tenant_id' => $otherTenant->id,
        ]);
    }
}
