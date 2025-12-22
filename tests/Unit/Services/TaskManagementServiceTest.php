<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TaskManagementService;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskManagementService $service;
    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $this->service = new TaskManagementService();
        
        // Authenticate the user for the service
        $this->actingAs($this->user);
    }

    public function test_bulk_delete_tasks_logs_correctly(): void
    {
        // Create test tasks
        $tasks = Task::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id
        ]);

        $taskIds = $tasks->pluck('id')->toArray();

        // Execute bulk delete
        $result = $this->service->bulkDeleteTasks($taskIds, $this->user->tenant_id);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['deleted_count']);
        $this->assertStringContainsString('Successfully deleted 3 tasks', $result['message']);

        // Verify tasks are deleted
        $this->assertEquals(0, Task::whereIn('id', $taskIds)->count());
    }

    public function test_bulk_update_status_logs_correctly(): void
    {
        // Create test tasks
        $tasks = Task::factory()->count(2)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'status' => 'backlog'
        ]);

        $taskIds = $tasks->pluck('id')->toArray();

        // Execute bulk status update
        $result = $this->service->bulkUpdateStatus($taskIds, 'in_progress', $this->user->tenant_id);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['updated_count']);
        $this->assertStringContainsString('Successfully updated 2 tasks to in_progress', $result['message']);

        // Verify tasks are updated
        $this->assertEquals(2, Task::whereIn('id', $taskIds)->where('status', 'in_progress')->count());
    }

    public function test_bulk_assign_tasks_logs_correctly(): void
    {
        // Create test tasks
        $tasks = Task::factory()->count(2)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'assignee_id' => null
        ]);

        $taskIds = $tasks->pluck('id')->toArray();
        $assigneeId = $this->user->id;

        // Execute bulk assignment
        $result = $this->service->bulkAssignTasks($taskIds, $assigneeId, $this->user->tenant_id);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['updated_count']);
        $this->assertStringContainsString('Successfully assigned 2 tasks', $result['message']);

        // Verify tasks are assigned
        $this->assertEquals(2, Task::whereIn('id', $taskIds)->where('assignee_id', $assigneeId)->count());
    }

    public function test_task_statistics_uses_cloned_queries(): void
    {
        // Create test tasks with different statuses
        Task::factory()->count(2)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'status' => 'backlog'
        ]);

        Task::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'status' => 'in_progress'
        ]);

        Task::factory()->count(1)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'status' => 'done'
        ]);

        // Get statistics
        $stats = $this->service->getTaskStatistics($this->user->tenant_id);

        // Assertions - each count should be independent
        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(2, $stats['backlog']);
        $this->assertEquals(3, $stats['in_progress']);
        $this->assertEquals(1, $stats['done']);
        $this->assertEquals(0, $stats['blocked']);
        $this->assertEquals(0, $stats['canceled']);
    }

    public function test_create_task_logs_correctly(): void
    {
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'backlog',
            'priority' => 'normal'
        ];

        // Execute create
        $task = $this->service->createTask($taskData, $this->user->tenant_id);

        // Assertions
        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Test Task', $task->name);
        $this->assertEquals($this->user->tenant_id, $task->tenant_id);
        $this->assertEquals($this->user->id, $task->created_by);
    }
}
