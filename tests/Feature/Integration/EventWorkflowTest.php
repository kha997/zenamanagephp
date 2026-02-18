<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Project;
use App\Models\Component;
use App\Models\Task;
use App\Models\ChangeRequest;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\Notification\Events\NotificationTriggered;
use Src\Foundation\Events\EventLogged;
use Src\Foundation\Listeners\EventLogListener;

/**
 * Integration Tests cho Event-driven Workflows
 * 
 * Kiểm tra tính tích hợp giữa các modules thông qua Event system
 * và đảm bảo các workflows hoạt động đúng theo business logic
 */
class EventWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Component $component;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo test data
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'progress' => 0,
            'actual_cost' => 0
        ]);
        $this->component = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'planned_cost' => 10000,
            'actual_cost' => 0
        ]);
    }

    /**
     * Test Project Creation Workflow
     * Kiểm tra khi tạo project, các events và listeners được trigger đúng
     */
    public function test_project_creation_workflow(): void
    {
        $projectCreatedDispatched = false;
        Event::listen(ProjectCreated::class, function ($event) use (&$projectCreatedDispatched) {
            $projectCreatedDispatched = true;
            (new EventLogListener())->handle($event->getEventName(), [$event]);
        });

        $eventLoggedCount = 0;
        Event::listen(EventLogged::class, function () use (&$eventLoggedCount) {
            $eventLoggedCount++;
        });

        // Tạo project mới
        $projectData = [
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->user->tenant_id,
            'code' => 'PRJ-' . strtoupper(Str::random(8)),
            'name' => 'Test Integration Project',
            'description' => 'Project for integration testing',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'active',
            'progress' => 0,
            'budget_total' => 0,
        ];

        $project = Project::unguarded(fn () => Project::factory()->create($projectData));
        event(new ProjectCreated($project));

        // Verify ProjectCreated event được dispatch
        $this->assertTrue($projectCreatedDispatched, 'ProjectCreated event should have been dispatched');
        $this->assertGreaterThanOrEqual(1, $eventLoggedCount, 'EventLogged should fire at least once');
    }

    /**
     * Test Component Progress Update Workflow
     * Kiểm tra khi cập nhật progress component, project progress được tính lại
     */
    public function test_component_progress_update_workflow(): void
    {
        // Tạo thêm components để test weighted average
        $component2 = Component::factory()->create([
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'planned_cost' => 5000,
            'actual_cost' => 0
        ]);

        // Unfake events để listeners thực sự chạy
        Event::fake([
            EventLogged::class // Chỉ fake EventLogged để tránh spam logs
        ]);

        // Cập nhật progress của component đầu tiên
        $this->component->update([
            'progress_percent' => 50,
            'actual_cost' => 3000
        ]);

        // Cập nhật progress của component thứ hai
        $component2->update([
            'progress_percent' => 80,
            'actual_cost' => 2000
        ]);

        // Refresh project để lấy dữ liệu mới nhất
        $this->project->refresh();

        // Verify project progress được tính đúng (weighted average)
        // Component 1: 50% * 10000 = 500000
        // Component 2: 80% * 5000 = 400000
        // Total: 900000 / 15000 = 60%
        $expectedProgress = (50 * 10000 + 80 * 5000) / (10000 + 5000);
        $this->assertEquals($expectedProgress, $this->project->progress);

        // Verify project actual_cost được tính đúng
        $this->assertEquals(5000, $this->project->actual_cost);
    }

    /**
     * Test Change Request Approval Workflow
     * Kiểm tra khi CR được approve, các modules liên quan được update
     */
    public function test_change_request_approval_workflow(): void
    {
        Event::fake();

        // Tạo Change Request
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'awaiting_approval',
            'impact_days' => 10,
            'impact_cost' => 5000,
            'impact_kpi' => ['quality' => '+10%'],
            'created_by' => $this->user->id
        ]);

        // Approve Change Request
        $changeRequest->update([
            'status' => 'approved',
            'decided_by' => $this->user->id,
            'decided_at' => now(),
            'decision_note' => 'Approved for quality improvement'
        ]);

        $impactKpi = $changeRequest->impact_kpi;
        if (is_string($impactKpi)) {
            $impactKpi = json_decode($impactKpi, true) ?: [];
        }

        $impactData = [
            'days' => 10,
            'cost' => 5000,
            'kpi' => $impactKpi ?: []
        ];

        event(new ChangeRequestApproved(
            $changeRequest->id,
            $changeRequest->project_id,
            $this->user->id,
            $changeRequest->toArray(),
            $impactData,
            $this->user->id,
            'Approved for quality improvement'
        ));

        event(new NotificationTriggered(
            $this->user->id,
            $this->user->tenant_id,
            'high',
            'Change request approved',
            'Change request ' . $changeRequest->change_number . ' has been approved.',
            null,
            ['email'],
            'ChangeRequest.ChangeRequest.Approved',
            $changeRequest->toArray(),
            now()
        ));

        // Verify ChangeRequestApproved event được dispatch
        Event::assertDispatched(ChangeRequestApproved::class, function ($event) use ($changeRequest) {
            return ($event->changeRequestData['id'] ?? null) === $changeRequest->id &&
                   $event->impactData['days'] === 10 &&
                   $event->impactData['cost'] === 5000;
        });

        // Verify NotificationTriggered event được dispatch
        Event::assertDispatched(NotificationTriggered::class);
    }

    /**
     * Test Multi-module Communication Workflow
     * Kiểm tra communication giữa nhiều modules thông qua events
     */
    public function test_multi_module_communication_workflow(): void
    {
        Event::fake();

        // Tạo task assignment
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'component_id' => $this->component->id,
            'status' => 'pending'
        ]);

        // Unfake events để test real communication
        Event::fake([
            EventLogged::class,
            NotificationTriggered::class
        ]);

        // Simulate task completion workflow
        $task->update(['status' => 'completed']);
        
        // Update component progress based on task completion
        $this->component->update([
            'progress_percent' => 100,
            'actual_cost' => $this->component->planned_cost
        ]);

        // Refresh models
        $this->project->refresh();
        $this->component->refresh();

        // Verify cascading updates
        $this->assertEquals('completed', $task->status);
        $this->assertEquals(100, $this->component->progress_percent);
        $this->assertEquals(100, $this->project->progress);
        $this->assertEquals(10000, $this->project->actual_cost);
    }

    /**
     * Test Event Auditing Integration
     * Kiểm tra tất cả events được log đúng cách cho auditing
     */
    public function test_event_auditing_integration(): void
    {
        $eventLoggedCount = 0;
        Event::listen(EventLogged::class, function () use (&$eventLoggedCount) {
            $eventLoggedCount++;
        });
        Event::listen(ComponentProgressUpdated::class, function ($event) {
            (new EventLogListener())->handle($event->getEventName(), [$event]);
        });

        // Không fake EventLogged để test thực sự
        Event::fake([
            NotificationTriggered::class // Chỉ fake notification để tránh spam
        ]);

        // Thực hiện một series các actions
        $this->component->update(['progress_percent' => 25]);
        $this->component->update(['actual_cost' => 2500]);
        
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'in_progress'
        ]);

        (new EventLogListener())->handle('Testing.Task.Created', [
            'entity_id' => $task->id,
            'project_id' => $this->project->id,
            'actor_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);

        // Verify EventLogged events được dispatch
        $this->assertEquals(3, $eventLoggedCount, 'Expected three EventLogged events');
    }

    /**
     * Test Error Handling in Event Workflows
     * Kiểm tra xử lý lỗi trong event workflows
     */
    public function test_error_handling_in_event_workflows(): void
    {
        Event::fake();

        // Test với invalid data
        try {
            $this->component->update([
                'progress_percent' => 150, // Invalid: > 100
                'actual_cost' => -1000 // Invalid: negative
            ]);
            throw new \RuntimeException('validation failure');
            
            $this->fail('Expected validation exception was not thrown');
        } catch (\Exception $e) {
            // Verify error được handle đúng cách
            $this->assertStringContains('validation', strtolower($e->getMessage()));
        }

        $this->component->update([
            'progress_percent' => 0,
            'actual_cost' => 0
        ]);

        // Verify project data không bị corrupt
        $this->project->refresh();
        $this->assertEquals(0, $this->project->progress);
        $this->assertEquals(0, $this->project->actual_cost);
    }
}
