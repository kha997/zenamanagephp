<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TaskService;
use App\Repositories\TaskRepository;
use App\Services\AuditService;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $taskService;
    protected $project;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $taskRepository = $this->createMock(TaskRepository::class);
        $auditService = $this->createMock(AuditService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserAccessTask')->willReturn(true);
        $permissionService->method('canUserModifyTask')->willReturn(true);
        $permissionService->method('canUserDeleteTask')->willReturn(true);
        
        // Mock TaskRepository to return real Task
        $taskRepository->method('create')->willReturnCallback(function($data) {
            return Task::create($data);
        });
        $taskRepository->method('getById')->willReturnCallback(function($id, $tenantId) {
            return Task::find($id);
        });
        $taskRepository->method('update')->willReturnCallback(function($id, $data, $tenantId) {
            $task = Task::find($id);
            if ($task) {
                $task->update($data);
                return $task;
            }
            return null;
        });
        $taskRepository->method('getAll')->willReturnCallback(function($filters) {
            $query = Task::where('tenant_id', $filters['tenant_id']);
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['project_id'])) {
                $query->where('project_id', $filters['project_id']);
            }
            
            return $query->paginate(15);
        });
        
        $this->taskService = new TaskService($taskRepository, $auditService, $permissionService);
        
        // Create test data
        $tenant = \App\Models\Tenant::create([
            'id' => 'test-tenant-1',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        $this->project = Project::create([
            'id' => '01k5e2kkwynze0f37a8a4d3435',
            'name' => 'Test Project',
            'description' => 'Test project for testing',
            'code' => 'TEST001',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'tenant_id' => $tenant->id,
        ]);

        $this->user = User::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt9',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
        ]);
    }

    /** @test */
    public function test_create_task_with_all_fields()
    {
        $taskData = [
            'project_id' => $this->project->id,
            'title' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'in_progress',
            'priority' => 'low',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'assignee_id' => $this->user->id,
            'progress_percent' => 50,
            'estimated_hours' => 8,
            'tags' => 'test,unit',
        ];

        $task = $this->taskService->createTask($taskData, $this->user->id, $this->user->tenant_id);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Test Task', $task->name);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('low', $task->priority);
        $this->assertEquals(50, $task->progress_percent);
    }

    /** @test */
    public function test_update_task_status()
    {
        // Create a task first
        $task = Task::create([
            'project_id' => $this->project->id,
            'name' => 'Original Task',
            'description' => 'Original description',
            'status' => 'pending',
            'priority' => 'medium',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'assignee_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => 'Updated Task',
            'description' => 'Updated description',
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id,
            'status' => 'completed', // Change status
            'priority' => 'high', // Change priority
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'progress_percent' => 100,
            'estimated_hours' => 10,
            'tags' => 'updated,completed',
        ];

        $updatedTask = $this->taskService->updateTask($task->id, $updateData, $this->user->id, $this->user->tenant_id);

        $this->assertInstanceOf(Task::class, $updatedTask);
        $this->assertEquals('completed', $updatedTask->status);
        $this->assertEquals('high', $updatedTask->priority);
        $this->assertEquals('Updated Task', $updatedTask->name);
        $this->assertEquals(100, $updatedTask->progress_percent);
    }

    /** @test */
    public function test_update_task_assignee()
    {
        $newUser = User::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt0',
            'name' => 'New Assignee',
            'email' => 'newassignee@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a task first
        $task = Task::create([
            'project_id' => $this->project->id,
            'name' => 'Task to Reassign',
            'description' => 'Task description',
            'status' => 'in_progress',
            'priority' => 'medium',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'assignee_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => $task->name,
            'description' => $task->description,
            'project_id' => $this->project->id,
            'assignee_id' => $newUser->id, // Change assignee
            'status' => $task->status,
            'priority' => $task->priority,
            'start_date' => $task->start_date->format('Y-m-d'),
            'end_date' => $task->end_date->format('Y-m-d'),
            'progress_percent' => $task->progress_percent,
            'estimated_hours' => $task->estimated_hours,
            'tags' => 'reassigned',
        ];

        $updatedTask = $this->taskService->updateTask($task->id, $updateData, $this->user->id, $this->user->tenant_id);

        $this->assertEquals($newUser->id, $updatedTask->assignee_id);
    }

    /** @test */
    public function test_get_tasks_with_filters()
    {
        // Create multiple tasks with different statuses
        Task::create([
            'project_id' => $this->project->id,
            'name' => 'Pending Task',
            'status' => 'pending',
            'priority' => 'low',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
        ]);

        Task::create([
            'project_id' => $this->project->id,
            'name' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'high',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
        ]);

        // Test status filter
        $pendingTasks = $this->taskService->getTasksList(['status' => 'pending'], $this->user->id, $this->user->tenant_id);
        $this->assertCount(1, $pendingTasks->items());

        $completedTasks = $this->taskService->getTasksList(['status' => 'completed'], $this->user->id, $this->user->tenant_id);
        $this->assertCount(1, $completedTasks->items());

        // Test project filter
        $projectTasks = $this->taskService->getTasksList(['project_id' => $this->project->id], $this->user->id, $this->user->tenant_id);
        $this->assertCount(2, $projectTasks->items());
    }

    /** @test */
    public function test_update_nonexistent_task_throws_exception()
    {
        $updateData = [
            'name' => 'Updated Task',
            'status' => 'completed',
        ];

        $this->expectException(\Exception::class);
        $this->taskService->updateTask('non-existent-id', $updateData, $this->user->id, $this->user->tenant_id);
    }
}
