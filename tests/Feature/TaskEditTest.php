<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Models\User;

class TaskEditTest extends TestCase
{
    use RefreshDatabase;

    protected $task;
    protected $project;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
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

        $this->task = Task::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt8',
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'in_progress',
            'priority' => 'low',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'assignee_id' => $this->user->id,
            'progress_percent' => 50,
            'estimated_hours' => 8,
        ]);
    }

    /** @test */
    public function test_task_edit_page_loads_correctly()
    {
        $response = $this->get("/tasks/{$this->task->id}/edit");
        
        $response->assertStatus(200);
        $response->assertViewIs('tasks.edit');
        $response->assertViewHas('task');
        $response->assertViewHas('projects');
        
        // Check if task data is passed correctly
        $viewData = $response->viewData();
        $this->assertEquals($this->task->id, $viewData['task']->id);
        $this->assertEquals('in_progress', $viewData['task']->status);
        $this->assertEquals('low', $viewData['task']->priority);
    }

    /** @test */
    public function test_task_status_update_works()
    {
        $updateData = [
            'name' => 'Updated Task Name',
            'description' => 'Updated description',
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id,
            'status' => 'completed', // Change status
            'priority' => 'high', // Change priority
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'progress_percent' => 100,
            'estimated_hours' => 10,
            'tags' => 'test,updated',
        ];

        $response = $this->put("/tasks/{$this->task->id}", $updateData);
        
        $response->assertRedirect('/tasks');
        
        // Check if task was updated in database
        $this->task->refresh();
        $this->assertEquals('completed', $this->task->status);
        $this->assertEquals('high', $this->task->priority);
        $this->assertEquals('Updated Task Name', $this->task->name);
        $this->assertEquals(100, $this->task->progress_percent);
    }

    /** @test */
    public function test_task_priority_update_works()
    {
        $updateData = [
            'name' => $this->task->name,
            'description' => $this->task->description,
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id,
            'status' => $this->task->status,
            'priority' => 'urgent', // Change priority
            'start_date' => $this->task->start_date->format('Y-m-d'),
            'end_date' => $this->task->end_date->format('Y-m-d'),
            'progress_percent' => $this->task->progress_percent,
            'estimated_hours' => $this->task->estimated_hours,
            'tags' => 'urgent,priority',
        ];

        $response = $this->put("/tasks/{$this->task->id}", $updateData);
        
        $response->assertRedirect('/tasks');
        
        // Check if priority was updated
        $this->task->refresh();
        $this->assertEquals('urgent', $this->task->priority);
    }

    /** @test */
    public function test_task_assignee_update_works()
    {
        $newUser = User::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt0',
            'name' => 'New Assignee',
            'email' => 'newassignee@example.com',
            'password' => bcrypt('password'),
        ]);

        $updateData = [
            'name' => $this->task->name,
            'description' => $this->task->description,
            'project_id' => $this->project->id,
            'assignee_id' => $newUser->id, // Change assignee
            'status' => $this->task->status,
            'priority' => $this->task->priority,
            'start_date' => $this->task->start_date->format('Y-m-d'),
            'end_date' => $this->task->end_date->format('Y-m-d'),
            'progress_percent' => $this->task->progress_percent,
            'estimated_hours' => $this->task->estimated_hours,
            'tags' => 'reassigned',
        ];

        $response = $this->put("/tasks/{$this->task->id}", $updateData);
        
        $response->assertRedirect('/tasks');
        
        // Check if assignee was updated
        $this->task->refresh();
        $this->assertEquals($newUser->id, $this->task->assignee_id);
    }

    /** @test */
    public function test_task_validation_works()
    {
        $invalidData = [
            'name' => '', // Empty name should fail
            'project_id' => 'invalid-project-id',
            'status' => 'invalid-status',
            'priority' => 'invalid-priority',
        ];

        $response = $this->put("/tasks/{$this->task->id}", $invalidData);
        
        $response->assertSessionHasErrors(['name', 'project_id', 'status', 'priority']);
    }

    /** @test */
    public function test_task_not_found_handling()
    {
        $response = $this->get('/tasks/non-existent-task-id/edit');
        
        $response->assertStatus(404);
    }
}
