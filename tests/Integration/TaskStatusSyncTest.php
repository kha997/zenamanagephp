<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectManagementService;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for project status sync to tasks
 * 
 * Tests that project status changes properly sync task statuses.
 * Uses seedTasksDomain() for reproducible test data.
 * 
 * @group integration
 * @group tasks
 * @group projects
 */
class TaskStatusSyncTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private ProjectManagementService $projectService;
    protected array $seedData;
    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setDomainSeed(45678);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->project = $this->seedData['projects'][0];
        
        $this->projectService = app(ProjectManagementService::class);
    }
    
    /**
     * @test
     */
    public function test_project_completed_syncs_tasks_to_done(): void
    {
        // Create tasks with different statuses
        $backlogTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Backlog Task',
            'title' => 'Backlog Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $inProgressTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'In Progress Task',
            'title' => 'In Progress Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 2.0,
        ]);
        
        $blockedTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Blocked Task',
            'title' => 'Blocked Task',
            'status' => TaskStatus::BLOCKED->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 3.0,
        ]);
        
        $doneTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Done Task',
            'title' => 'Done Task',
            'status' => TaskStatus::DONE->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 4.0,
        ]);
        
        // Update project to completed using service (which triggers sync)
        $this->projectService->updateProjectStatus((string)$this->project->id, 'completed', (string)$this->seedData['tenant']->id);
        
        // Refresh tasks
        $backlogTask->refresh();
        $inProgressTask->refresh();
        $blockedTask->refresh();
        $doneTask->refresh();
        
        // Non-terminal tasks should be moved to done
        $this->assertEquals(TaskStatus::DONE->value, $backlogTask->status);
        $this->assertEquals(TaskStatus::DONE->value, $inProgressTask->status);
        $this->assertEquals(TaskStatus::DONE->value, $blockedTask->status);
        
        // Already done task should remain done
        $this->assertEquals(TaskStatus::DONE->value, $doneTask->status);
    }
    
    /**
     * @test
     */
    public function test_project_cancelled_syncs_tasks_to_canceled(): void
    {
        // Create tasks with different statuses
        $backlogTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Backlog Task',
            'title' => 'Backlog Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $inProgressTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'In Progress Task',
            'title' => 'In Progress Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 2.0,
        ]);
        
        $doneTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Done Task',
            'title' => 'Done Task',
            'status' => TaskStatus::DONE->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 3.0,
        ]);
        
        // Update project to cancelled using service (which triggers sync)
        $this->projectService->updateProjectStatus((string)$this->project->id, 'cancelled', (string)$this->seedData['tenant']->id);
        
        // Refresh tasks
        $backlogTask->refresh();
        $inProgressTask->refresh();
        $doneTask->refresh();
        
        // Non-terminal tasks should be moved to canceled
        $this->assertEquals(TaskStatus::CANCELED->value, $backlogTask->status);
        $this->assertEquals(TaskStatus::CANCELED->value, $inProgressTask->status);
        
        // Done task should remain done (terminal state)
        $this->assertEquals(TaskStatus::DONE->value, $doneTask->status);
    }
    
    /**
     * @test
     */
    public function test_project_on_hold_syncs_in_progress_to_blocked(): void
    {
        $backlogTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Backlog Task',
            'title' => 'Backlog Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $inProgressTask = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'In Progress Task',
            'title' => 'In Progress Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 2.0,
        ]);
        
        // Update project to on_hold using service (which triggers sync)
        $this->projectService->updateProjectStatus((string)$this->project->id, 'on_hold', (string)$this->seedData['tenant']->id);
        
        // Refresh tasks
        $backlogTask->refresh();
        $inProgressTask->refresh();
        
        // Backlog should remain backlog
        $this->assertEquals(TaskStatus::BACKLOG->value, $backlogTask->status);
        
        // In progress should be moved to blocked
        $this->assertEquals(TaskStatus::BLOCKED->value, $inProgressTask->status);
    }
    
    /**
     * @test
     */
    public function test_project_archived_prevents_task_changes(): void
    {
        $task = Task::create([
            'tenant_id' => $this->seedData['tenant']->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $this->seedData['users'][0]->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        // Update project to archived
        $this->project->update(['status' => 'archived']);
        
        // Try to move task (should be blocked by project status)
        $response = $this->actingAs($this->seedData['users'][0])
            ->patchJson("/api/tasks/{$task->id}/move", [
                'to_status' => TaskStatus::IN_PROGRESS->value,
                'version' => $task->version,
            ]);
        
        // Should be blocked
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'project_status_restricted');
    }
}
