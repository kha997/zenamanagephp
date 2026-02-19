<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Tests\Traits\AuthenticationTrait;

/**
 * Feature tests for Task management endpoints
 */
class TaskTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    private User $user;
    private Tenant $tenant;
    private Project $project;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['task.view', 'task.create', 'task.edit', 'task.delete']
        );
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->apiAs($this->user, $this->tenant);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test creating a new task
     */
    public function test_create_task(): void
    {
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'A test task for unit testing',
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-15'
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201)
                ->assertJsonPath('status', 'success')
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'task' => [
                            'id',
                            'project_id',
                            'name',
                            'description',
                            'status',
                            'priority',
                            'estimated_hours',
                            'start_date',
                            'end_date'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Test Task',
            'project_id' => $this->project->id,
        ]);

        $taskProjectTenantId = DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->where('tasks.name', 'Test Task')
            ->value('projects.tenant_id');
        $this->assertSame((string) $this->tenant->id, (string) $taskProjectTenantId);
    }

    /**
     * Test getting all tasks
     */
    public function test_get_tasks(): void
    {
        // Create some test tasks
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/tasks');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'project_id',
                            'title',
                            'description',
                            'status',
                            'priority',
                            'estimated_hours'
                        ]
                    ]
                ]);

        $this->assertEquals(3, count($response->json('data')));
    }

    /**
     * Test getting tasks by project
     */
    public function test_get_tasks_by_project(): void
    {
        // Create tasks for this project
        Task::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create another project and tasks
        $otherProject = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        Task::factory()->count(2)->create([
            'project_id' => $otherProject->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    /**
     * Test getting a specific task
     */
    public function test_get_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'project_id',
                        'title',
                        'description',
                        'status',
                        'priority',
                        'estimated_hours'
                    ]
                ]);
    }

    /**
     * Test updating a task
     */
    public function test_update_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $updateData = [
            'name' => 'Updated Task Name',
            'description' => 'Updated task description',
            'status' => 'in_progress',
            'priority' => 'high',
            'estimated_hours' => 16
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('data.name', 'Updated Task Name')
                ->assertJsonPath('data.description', 'Updated task description')
                ->assertJsonPath('data.status', 'in_progress')
                ->assertJsonPath('data.priority', 'high')
                ->assertJsonPath('data.estimated_hours', 16);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Task Name',
            'status' => 'in_progress'
        ]);
    }

    /**
     * Test deleting a task
     */
    public function test_delete_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Task deleted successfully'
                ]);

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id
        ]);
    }

    /**
     * Test task validation
     */
    public function test_create_task_validation(): void
    {
        $response = $this->postJson('/api/v1/tasks', []);

        $response->assertStatus(422)
                ->assertJsonPath('error.code', 'E422.VALIDATION')
                ->assertJsonStructure([
                    'error' => [
                        'details' => [
                            'data' => [
                                'validation_errors' => [
                                    'name',
                                    'project_id',
                                ],
                            ],
                        ],
                    ],
                ]);
    }

    /**
     * Test accessing task without authentication
     */
    public function test_access_task_without_auth(): void
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(401);
    }

    /**
     * Test accessing task from different tenant
     */
    public function test_access_task_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherToken = $otherUser->createToken('test-token')->plainTextToken;
        
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }
}
