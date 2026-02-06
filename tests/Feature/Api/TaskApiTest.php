<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ZenaProject;
use App\Models\ZenaTask;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\DatabaseTrait;

/**
 * Feature tests cho Task API endpoints
 */
class TaskApiTest extends TestCase
{
    use DatabaseTrait;
    use AuthenticationTestTrait;

    protected User $user;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->tenantId = $this->apiFeatureTenant->id;
    }
    
    /**
     * Test get tasks for project
     */
    public function test_can_get_tasks_for_project(): void
    {
        $project = ZenaProject::factory()->create([
            'tenant_id' => $this->tenantId
        ]);
        
        // Tạo tasks cho project
        ZenaTask::factory()->count(5)->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId
        ]);
        
        $response = $this->apiGet('/api/zena/tasks', ['project_id' => $project->id]);

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
                            'dependencies_json'
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
        $project = ZenaProject::factory()->create([
            'tenant_id' => $this->tenantId
        ]);
        
        // Tạo prerequisite tasks
        $task1 = ZenaTask::factory()->create(['project_id' => $project->id, 'tenant_id' => $this->tenantId]);
        $task2 = ZenaTask::factory()->create(['project_id' => $project->id, 'tenant_id' => $this->tenantId]);
        
        $taskData = [
            'name' => 'Dependent Task',
            'title' => 'Dependent Task',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'pending',
            'project_id' => $project->id,
            'dependencies' => [$task1->id, $task2->id]
        ];
        
        $response = $this->apiPost('/api/zena/tasks', $taskData);

        
        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'name' => 'Dependent Task',
                        'project_id' => $project->id,
                        'dependencies_json' => [$task1->id, $task2->id]
                    ]
                ]);
    }
    
    /**
     * Test task status update
     */
    public function test_can_update_task_status(): void
    {
        $project = ZenaProject::factory()->create([
            'tenant_id' => $this->tenantId
        ]);
        
        $task = ZenaTask::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenantId
        ]);
        
        $updateData = [
            'status' => 'in_progress'
        ];
        
        $response = $this->apiPut("/api/zena/tasks/{$task->id}", $updateData);
        
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
