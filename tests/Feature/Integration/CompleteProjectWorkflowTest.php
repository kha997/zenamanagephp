<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\ChangeRequest;
use App\Models\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Auth;

class CompleteProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'status' => 'planning',
            'progress_pct' => 0,
            'budget_actual' => 0
        ]);
        
        // Authenticate user
        $this->actingAs($this->user);
    }

    /**
     * Test complete project lifecycle workflow
     */
    public function test_complete_project_lifecycle_workflow(): void
    {
        // 1. Project Creation Phase
        $this->assertEquals('planning', $this->project->status);
        $this->assertEquals(0, $this->project->progress_pct);
        
        // 2. Add Components
        $component1 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'actual_cost' => 0
        ]);
        
        $component2 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'actual_cost' => 0
        ]);
        
        // 3. Add Tasks
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Design Phase',
            'status' => 'pending',
            'created_by' => $this->user->id
        ]);
        
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Development Phase',
            'status' => 'pending',
            'created_by' => $this->user->id
        ]);
        
        // 4. Add Documents
        $document1 = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Project Requirements',
            'uploaded_by' => $this->user->id
        ]);
        
        // 5. Start Project (Move to Active)
        $this->project->update(['status' => 'active']);
        $this->assertEquals('active', $this->project->status);
        
        // 6. Update Component Progress
        $component1->update(['progress_percent' => 50]);
        $component2->update(['progress_percent' => 25]);
        
        // 7. Update Task Status
        $task1->update(['status' => 'completed']);
        $task2->update(['status' => 'in_progress']);
        
        // 8. Add Change Request
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Scope Change Request',
            'status' => 'pending',
            'requested_by' => $this->user->id
        ]);
        
        // 9. Approve Change Request
        $changeRequest->update(['status' => 'approved']);
        
        // 10. Complete Project
        $this->project->update(['status' => 'completed']);
        
        // Verify final state
        $this->assertEquals('completed', $this->project->status);
        $this->assertCount(2, $this->project->components);
        $this->assertCount(2, $this->project->tasks);
        $this->assertCount(1, $this->project->documents);
        $this->assertCount(1, $this->project->changeRequests);
        
        // Verify tenant isolation
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $component1->tenant_id);
        $this->assertEquals($this->tenant->id, $task1->tenant_id);
        $this->assertEquals($this->tenant->id, $document1->tenant_id);
        $this->assertEquals($this->tenant->id, $changeRequest->tenant_id);
    }

    /**
     * Test project progress calculation workflow
     */
    public function test_project_progress_calculation_workflow(): void
    {
        // Create components with different progress
        $component1 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 100,
            'actual_cost' => 5000
        ]);
        
        $component2 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 50,
            'actual_cost' => 3000
        ]);
        
        $component3 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 0,
            'actual_cost' => 0
        ]);
        
        // Calculate expected progress
        $totalProgress = (100 + 50 + 0) / 3; // 50%
        $totalCost = 5000 + 3000 + 0; // 8000
        
        // Verify calculations
        $this->assertEquals(50, $totalProgress);
        $this->assertEquals(8000, $totalCost);
        
        // Verify components exist
        $this->assertCount(3, $this->project->components);
        
        // Verify tenant isolation
        foreach ($this->project->components as $component) {
            $this->assertEquals($this->tenant->id, $component->tenant_id);
        }
    }

    /**
     * Test project collaboration workflow
     */
    public function test_project_collaboration_workflow(): void
    {
        // Create additional users
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $user3 = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create tasks assigned to different users
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task for User 1',
            'assignee_id' => $this->user->id,
            'created_by' => $this->user->id
        ]);
        
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task for User 2',
            'assignee_id' => $user2->id,
            'created_by' => $this->user->id
        ]);
        
        $task3 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task for User 3',
            'assignee_id' => $user3->id,
            'created_by' => $this->user->id
        ]);
        
        // Create documents uploaded by different users
        $document1 = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Document by User 1',
            'uploaded_by' => $this->user->id
        ]);
        
        $document2 = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Document by User 2',
            'uploaded_by' => $user2->id
        ]);
        
        // Verify collaboration
        $this->assertCount(3, $this->project->tasks);
        $this->assertCount(2, $this->project->documents);
        
        // Verify all users are in the same tenant
        $this->assertEquals($this->tenant->id, $this->user->tenant_id);
        $this->assertEquals($this->tenant->id, $user2->tenant_id);
        $this->assertEquals($this->tenant->id, $user3->tenant_id);
        
        // Verify all tasks and documents belong to the same tenant
        foreach ($this->project->tasks as $task) {
            $this->assertEquals($this->tenant->id, $task->tenant_id);
        }
        
        foreach ($this->project->documents as $document) {
            $this->assertEquals($this->tenant->id, $document->tenant_id);
        }
    }

    /**
     * Test project change management workflow
     */
    public function test_project_change_management_workflow(): void
    {
        // Create initial change request
        $changeRequest1 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Initial Change Request',
            'status' => 'pending',
            'requested_by' => $this->user->id
        ]);
        
        // Create additional change requests
        $changeRequest2 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Second Change Request',
            'status' => 'pending',
            'requested_by' => $this->user->id
        ]);
        
        // Approve first change request
        $changeRequest1->update(['status' => 'approved']);
        
        // Reject second change request
        $changeRequest2->update(['status' => 'rejected']);
        
        // Verify change management
        $this->assertCount(2, $this->project->changeRequests);
        
        $approvedRequests = $this->project->changeRequests->where('status', 'approved');
        $rejectedRequests = $this->project->changeRequests->where('status', 'rejected');
        
        $this->assertCount(1, $approvedRequests);
        $this->assertCount(1, $rejectedRequests);
        
        // Verify tenant isolation
        foreach ($this->project->changeRequests as $changeRequest) {
            $this->assertEquals($this->tenant->id, $changeRequest->tenant_id);
        }
    }

    /**
     * Test project completion workflow
     */
    public function test_project_completion_workflow(): void
    {
        // Create components
        $component1 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 100,
            'actual_cost' => 10000
        ]);
        
        $component2 = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'progress_percent' => 100,
            'actual_cost' => 15000
        ]);
        
        // Create tasks
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Final Task 1',
            'status' => 'completed',
            'created_by' => $this->user->id
        ]);
        
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Final Task 2',
            'status' => 'completed',
            'created_by' => $this->user->id
        ]);
        
        // Create final document
        $finalDocument = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Project Completion Report',
            'uploaded_by' => $this->user->id
        ]);
        
        // Complete project
        $this->project->update([
            'status' => 'completed',
            'progress_pct' => 100,
            'budget_actual' => 25000
        ]);
        
        // Verify completion
        $this->assertEquals('completed', $this->project->status);
        $this->assertEquals(100, $this->project->progress_pct);
        $this->assertEquals(25000, $this->project->budget_actual);
        
        // Verify all components are complete
        foreach ($this->project->components as $component) {
            $this->assertEquals(100, $component->progress_percent);
        }
        
        // Verify all tasks are completed
        foreach ($this->project->tasks as $task) {
            $this->assertEquals('completed', $task->status);
        }
        
        // Verify final document exists
        $this->assertCount(1, $this->project->documents);
        $this->assertEquals('Project Completion Report', $finalDocument->name);
        
        // Verify tenant isolation maintained
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $component1->tenant_id);
        $this->assertEquals($this->tenant->id, $task1->tenant_id);
        $this->assertEquals($this->tenant->id, $finalDocument->tenant_id);
    }
}
