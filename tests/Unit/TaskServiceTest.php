<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TaskService;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
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
        
        $this->taskService = new TaskService();
        
        // Create test data
        $this->project = Project::create([
            'id' => '01k5e2kkwynze0f37a8a4d3435',
            'name' => 'Test Project',
            'description' => 'Test project for testing',
            'code' => 'TEST001',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->user = User::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt9',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function test_create_task_with_all_fields()
    {
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Test Task',
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

        $task = $this->taskService->createTask($taskData);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Test Task', $task->name);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('low', $task->priority);
        $this->assertEquals($this->user->id, $task->assignee_id);
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

        $updatedTask = $this->taskService->updateTask($task->id, $updateData);

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

        $updatedTask = $this->taskService->updateTask($task->id, $updateData);

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
        ]);

        Task::create([
            'project_id' => $this->project->id,
            'name' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'high',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        // Test status filter
        $pendingTasks = $this->taskService->getTasks(['status' => 'pending']);
        $this->assertCount(1, $pendingTasks->items());

        $completedTasks = $this->taskService->getTasks(['status' => 'completed']);
        $this->assertCount(1, $completedTasks->items());

        // Test project filter
        $projectTasks = $this->taskService->getTasks(['project_id' => $this->project->id]);
        $this->assertCount(2, $projectTasks->items());
    }

    /** @test */
    public function test_update_nonexistent_task_returns_null()
    {
        $updateData = [
            'name' => 'Updated Task',
            'status' => 'completed',
        ];

        $result = $this->taskService->updateTask('non-existent-id', $updateData);
        $this->assertNull($result);
    }
}
