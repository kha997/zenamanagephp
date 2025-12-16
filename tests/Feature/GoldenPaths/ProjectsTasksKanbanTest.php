<?php declare(strict_types=1);

namespace Tests\Feature\GoldenPaths;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Golden Path 2: Projects → Tasks (Kanban)
 * 
 * Tests the critical flow: Create project → Open project → Create task → 
 * Drag task in Kanban → Change status
 */
class ProjectsTasksKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
    }

    /** @test */
    public function user_can_create_project(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->postJson('/api/v1/app/projects', [
            'name' => 'Test Project',
            'description' => 'Test project for golden path',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'project' => [
                    'id',
                    'name',
                    'tenant_id',
                ],
            ],
        ]);
        
        $project = $response->json('data.project');
        $this->assertEquals('Test Project', $project['name']);
        $this->assertEquals($this->tenantId, $project['tenant_id']);
    }

    /** @test */
    public function user_can_get_project_details(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'tenant_id' => $this->tenantId,
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/projects/{$project->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'project' => [
                    'id',
                    'name',
                    'tenant_id',
                ],
            ],
        ]);
        
        $data = $response->json('data.project');
        $this->assertEquals($project->id, $data['id']);
    }

    /** @test */
    public function user_can_create_task(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->postJson('/api/v1/app/tasks', [
            'project_id' => $project->id,
            'name' => 'Test Task',
            'status' => 'backlog',
            'description' => 'Test task for golden path',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'task' => [
                    'id',
                    'name',
                    'status',
                    'project_id',
                    'tenant_id',
                ],
            ],
        ]);
        
        $task = $response->json('data.task');
        $this->assertEquals('Test Task', $task['name']);
        $this->assertEquals('backlog', $task['status']);
        $this->assertEquals($this->tenantId, $task['tenant_id']);
    }

    /** @test */
    public function user_can_get_tasks_for_kanban(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'status' => 'backlog',
        ]);
        
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'status' => 'todo',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/tasks?project_id={$project->id}&view=kanban", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'tasks',
            ],
        ]);
        
        $tasks = $response->json('data.tasks');
        $this->assertIsArray($tasks);
        $this->assertGreaterThan(0, count($tasks));
        
        // All tasks should belong to user's tenant
        foreach ($tasks as $task) {
            $this->assertEquals($this->tenantId, $task['tenant_id']);
        }
    }

    /** @test */
    public function user_can_change_task_status(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'status' => 'backlog',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        // Move from backlog to todo
        $response = $this->patchJson("/api/v1/app/tasks/{$task->id}/status", [
            'status' => 'todo',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.task');
        $this->assertEquals('todo', $data['status']);
    }

    /** @test */
    public function invalid_status_transition_returns_error(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'status' => 'backlog',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        // Try invalid transition: backlog → completed (not allowed)
        $response = $this->patchJson("/api/v1/app/tasks/{$task->id}/status", [
            'status' => 'completed',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 409 Conflict or 422 Unprocessable Entity
        $this->assertContains($response->status(), [409, 422]);
        
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
        ]);
        
        $this->assertFalse($response->json('ok'));
        $this->assertNotEmpty($response->json('message'));
    }

    /** @test */
    public function user_cannot_access_other_tenant_projects(): void
    {
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX6Y';
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/projects/{$otherProject->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 404 Not Found (tenant isolation)
        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_access_other_tenant_tasks(): void
    {
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX6Y';
        $otherTask = Task::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/tasks/{$otherTask->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 404 Not Found (tenant isolation)
        $response->assertStatus(404);
    }
}

