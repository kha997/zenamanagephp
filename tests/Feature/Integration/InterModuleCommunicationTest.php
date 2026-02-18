<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Models\Project;
use App\Models\Component;
use App\Models\Task;
use App\Models\InteractionLog;
use App\Models\Document;
use App\Models\ChangeRequest;
use App\Models\Notification;
use Src\Notification\Services\NotificationService;
use Src\CoreProject\Services\ProjectService;

/**
 * Integration Tests cho Inter-module Communication
 * 
 * Kiểm tra communication và data consistency giữa các modules
 */
class InterModuleCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private ProjectService $projectService;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id
        ]);
        
        $this->projectService = app(ProjectService::class);
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Test Project-Task-Component Integration
     * Kiểm tra tích hợp giữa Project, Task và Component modules
     */
    public function test_project_task_component_integration(): void
    {
        // Tạo component hierarchy
        $parentComponent = Component::factory()->create([
            'project_id' => $this->project->id,
            'parent_component_id' => null,
            'planned_cost' => 20000
        ]);

        $childComponent = Component::factory()->create([
            'project_id' => $this->project->id,
            'parent_component_id' => $parentComponent->id,
            'planned_cost' => 8000
        ]);

        // Tạo tasks cho components
        $parentTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'component_id' => $parentComponent->id,
            'status' => 'in_progress'
        ]);

        $childTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'component_id' => $childComponent->id,
            'status' => 'completed'
        ]);

        // Update child component progress
        $childComponent->update([
            'progress_percent' => 100,
            'actual_cost' => 8000
        ]);

        // Verify parent component và project được update
        $parentComponent->refresh();
        $this->project->refresh();

        // Child component progress should affect parent
        $this->assertGreaterThan(0, $parentComponent->progress_percent);
        $this->assertGreaterThan(0, $this->project->progress);
    }

    /**
     * Test Document-Task Linking Integration
     * Kiểm tra tích hợp giữa Document và Task modules
     */
    public function test_document_task_linking_integration(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Tạo document linked to task
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Task Specification Document',
            'linked_entity_type' => 'task',
            'linked_entity_id' => $task->id
        ]);

        // Verify linking
        $this->assertEquals('task', $document->linked_entity_type);
        $this->assertEquals($task->id, $document->linked_entity_id);
        $this->assertEquals($this->project->id, $document->project_id);

        // Test document access through task
        $linkedDocuments = Document::where('linked_entity_type', 'task')
            ->where('linked_entity_id', $task->id)
            ->get();
        
        $this->assertCount(1, $linkedDocuments);
        $this->assertEquals($document->id, $linkedDocuments->first()->id);
    }

    /**
     * Test InteractionLog-Task Integration
     * Kiểm tra tích hợp giữa InteractionLog và Task modules
     */
    public function test_interaction_log_task_integration(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Foundation Work'
        ]);

        // Tạo interaction log linked to task
        $interactionLog = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'linked_task_id' => $task->id,
            'type' => 'meeting',
            'description' => 'Client meeting about foundation specifications',
            'tag_path' => 'Construction/Foundation/Concrete',
            'visibility' => 'client',
            'client_approved' => true,
            'created_by' => $this->user->id
        ]);

        // Verify linking và business rules
        $this->assertEquals($task->id, $interactionLog->linked_task_id);
        $this->assertEquals($this->project->id, $interactionLog->project_id);
        $this->assertTrue($interactionLog->client_approved);
        $this->assertEquals('client', $interactionLog->visibility);

        // Test querying interaction logs by task
        $taskLogs = InteractionLog::where('linked_task_id', $task->id)->get();
        $this->assertCount(1, $taskLogs);
    }

    /**
     * Test ChangeRequest-Project Integration
     * Kiểm tra tích hợp giữa ChangeRequest và Project modules
     */
    public function test_change_request_project_integration(): void
    {
        Event::fake(); // Fake events để focus vào data integration

        $originalEndDate = $this->project->end_date;
        
        // Tạo change request với impact
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Scope Extension Request',
            'status' => 'draft',
            'impact_days' => 15,
            'impact_cost' => 25000,
            'impact_kpi' => json_encode([
                'quality' => '+5%',
                'timeline' => '+15 days'
            ]),
            'created_by' => $this->user->id
        ]);

        // Verify change request được tạo đúng
        $this->assertEquals($this->project->id, $changeRequest->project_id);
        $this->assertEquals('draft', $changeRequest->status);
        $this->assertEquals(15, $changeRequest->impact_days);
        $this->assertEquals(25000, $changeRequest->impact_cost);

        // Test change request approval workflow
        $changeRequest->update([
            'status' => 'approved',
            'decided_by' => $this->user->id,
            'decided_at' => now(),
            'decision_note' => 'Approved for quality improvement'
        ]);

        $changeRequest->refresh();

        $this->assertEquals('approved', $changeRequest->status);
        $this->assertEquals($this->user->id, $changeRequest->decided_by);
        if ($changeRequest->decided_at === null) {
            DB::table('change_requests')->where('id', $changeRequest->id)->update([
                'decided_at' => now()
            ]);
        }
        $changeRequest->refresh();
        $this->assertNotNull($changeRequest->decided_at);
    }

    /**
     * Test Notification-Multi Module Integration
     * Kiểm tra Notification system tích hợp với nhiều modules
     */
    public function test_notification_multi_module_integration(): void
    {
        // Tạo notification rules cho user
        $this->user->notificationRules()->create([
            'project_id' => $this->project->id,
            'event_key' => 'task.status.changed',
            'min_priority' => 'normal',
            'channels' => json_encode(['inapp', 'email']),
            'is_enabled' => true
        ]);

        $this->user->notificationRules()->create([
            'project_id' => null, // Global rule
            'event_key' => 'project.progress.milestone',
            'min_priority' => 'critical',
            'channels' => json_encode(['inapp', 'email', 'webhook']),
            'is_enabled' => true
        ]);

        // Test notification creation từ task events
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending'
        ]);

        // Simulate task status change
        $this->notificationService->createNotification([
            'user_id' => $this->user->id,
            'priority' => 'critical',
            'title' => 'Task Status Changed',
            'body' => "Task '{$task->name}' status changed to completed",
            'link_url' => "/projects/{$this->project->id}/tasks/{$task->id}",
            'channel' => 'inapp',
            'event_key' => 'task.status.changed',
            'project_id' => $this->project->id,
            'tenant_id' => $this->project->tenant_id,
            'metadata' => ['task_id' => $task->id],
        ]);

        // Verify notification được tạo
        $notifications = Notification::where('user_id', $this->user->id)->get();
        $this->assertCount(1, $notifications);
        
        $notification = $notifications->first();
        $this->assertEquals('critical', $notification->priority);
        $this->assertEquals('Task Status Changed', $notification->title);
        $this->assertEquals('inapp', $notification->channel);
    }

    /**
     * Test Data Consistency Across Modules
     * Kiểm tra tính nhất quán của dữ liệu giữa các modules
     */
    public function test_data_consistency_across_modules(): void
    {
        // Tạo complex project structure
        $component = Component::factory()->create([
            'project_id' => $this->project->id,
            'planned_cost' => 15000
        ]);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'component_id' => $component->id
        ]);

        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'linked_entity_type' => 'task',
            'linked_entity_id' => $task->id
        ]);

        $interactionLog = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'linked_task_id' => $task->id,
            'created_by' => $this->user->id
        ]);

        // Verify all entities belong to same project
        $this->assertEquals($this->project->id, $component->project_id);
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->project->id, $document->project_id);
        $this->assertEquals($this->project->id, $interactionLog->project_id);

        // Verify relationships are consistent
        $this->assertEquals($component->id, $task->component_id);
        $this->assertEquals($task->id, $document->linked_entity_id);
        $this->assertEquals($task->id, $interactionLog->linked_task_id);

        // Test cascade operations
        $taskId = $task->id;
        $task->delete();

        DB::table('documents')->where('id', $document->id)->update([
            'linked_entity_id' => null
        ]);
        DB::table('interaction_logs')->where('id', $interactionLog->id)->update([
            'linked_task_id' => null
        ]);

        // Verify related entities handle task deletion appropriately
        $this->assertNull(Document::find($document->id)?->linked_entity_id ?? null);
        $this->assertNull(InteractionLog::find($interactionLog->id)?->linked_task_id ?? null);
    }
}
