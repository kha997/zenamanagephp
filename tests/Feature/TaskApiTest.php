<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Models\User;

class TaskApiTest extends TestCase
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
            'tags' => 'test,api',
        ]);
    }

    /** @test */
    public function test_api_tasks_index_returns_correct_data()
    {
        $response = $this->get('/api/tasks');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'tasks',
                'total',
                'per_page',
                'current_page',
                'last_page'
            ]
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['tasks']);
        
        $task = $data['data']['tasks'][0];
        $this->assertEquals($this->task->id, $task['id']);
        $this->assertEquals('in_progress', $task['status']);
        $this->assertEquals('low', $task['priority']);
    }

    /** @test */
    public function test_api_tasks_with_filters()
    {
        // Test status filter
        $response = $this->get('/api/tasks?status=in_progress');
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data['data']['tasks']);
        
        // Test project filter
        $response = $this->get("/api/tasks?project_id={$this->project->id}");
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data['data']['tasks']);
        
        // Test assignee filter
        $response = $this->get("/api/tasks?assignee_id={$this->user->id}");
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data['data']['tasks']);
    }

    /** @test */
    public function test_api_tasks_search_functionality()
    {
        $response = $this->get('/api/tasks?search=Test Task');
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data['data']['tasks']);
        
        $response = $this->get('/api/tasks?search=NonExistent');
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(0, $data['data']['tasks']);
    }

    /** @test */
    public function test_api_tasks_pagination()
    {
        // Create more tasks for pagination testing
        for ($i = 0; $i < 15; $i++) {
            Task::create([
                'project_id' => $this->project->id,
                'name' => "Task {$i}",
                'description' => "Description {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'start_date' => now(),
                'end_date' => now()->addDays(7),
                'assignee_id' => $this->user->id,
            ]);
        }

        $response = $this->get('/api/tasks?per_page=10');
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(10, $data['data']['tasks']);
        $this->assertEquals(16, $data['data']['total']); // 1 original + 15 new
    }
}