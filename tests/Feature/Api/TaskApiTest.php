<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\ZenaComponent;
use App\Models\Task;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;

/**
 * Feature tests cho Task API endpoints
 */
class TaskApiTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait;
    
    /**
     * Test get tasks for project
     */
    public function test_can_get_tasks_for_project(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo tasks cho project
        Task::factory()->count(5)->create([
            'project_id' => $project->id
        ]);
        
        $response = $this->getJson("/api/v1/projects/{$project->id}/tasks");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'project_id',
                            'name',
                            'start_date',
                            'end_date',
                            'status',
                            'dependencies'
                        ]
                    ]
                ]);
        
        // Verify trả về tasks
        $this->assertCount(5, $response->json('data'));
    }
    
    /**
     * Test create task with dependencies
     */
    public function test_can_create_task_with_dependencies(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo prerequisite tasks
        $task1 = Task::factory()->create(['project_id' => $project->id]);
        $task2 = Task::factory()->create(['project_id' => $project->id]);
        
        $taskData = [
            'name' => 'Dependent Task',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'pending',
            'dependencies' => [$task1->id, $task2->id]
        ];
        
        $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", $taskData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'name' => 'Dependent Task',
                        'dependencies' => [$task1->id, $task2->id]
                    ]
                ]);
    }
    
    /**
     * Test task status update
     */
    public function test_can_update_task_status(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending'
        ]);
        
        $updateData = [
            'status' => 'in_progress'
        ];
        
        $response = $this->putJson("/api/v1/tasks/{$task->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'status' => 'in_progress'
                    ]
                ]);
        
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress'
        ]);
    }
}