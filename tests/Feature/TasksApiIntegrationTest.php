<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Tasks API Integration Test
 *
 * Tests the Tasks API functionality including:
 * - Task creation, reading, updating, deletion
 * - Tenant isolation
 * - Permission checks
 * - Response structure validation
 * - Error handling
 */
class TasksApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        // Create users
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'project_manager'
        ]);

        $this->otherUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'role' => 'project_manager'
        ]);

        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function can_create_task_with_valid_data()
    {
        $taskData = [
            'title' => 'Test Task',
            'description' => 'A test task for integration testing',
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id,
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => now()->addDays(7)->toDateString(),
            'estimated_hours' => 8,
            'progress_percent' => 0,
            'tags' => ['test', 'integration'],
            'dependencies' => [],
            'is_milestone' => false,
            'requires_approval' => false
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'project_id',
                    'assignee_id',
                    'priority',
                    'status',
                    'due_date',
                    'estimated_hours',
                    'progress_percent',
                    'tags',
                    'dependencies',
                    'is_milestone',
                    'requires_approval',
                    'created_at',
                    'updated_at'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Test Task',
                    'description' => 'A test task for integration testing',
                    'project_id' => $this->project->id,
                    'assignee_id' => $this->user->id,
                    'priority' => 'high',
                    'status' => 'pending',
                    'estimated_hours' => 8,
                    'progress_percent' => 0,
                    'tags' => ['test', 'integration'],
                    'dependencies' => [],
                    'is_milestone' => false,
                    'requires_approval' => false
                ],
                'message' => 'Task created successfully'
            ]);

        // Verify task was created in database
        $this->assertDatabaseHas('tasks', [
            'name' => 'Test Task', // Note: API uses 'title' but DB uses 'name'
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function cannot_create_task_with_invalid_data()
    {
        $invalidData = [
            'title' => '', // Required field empty
            'project_id' => 'non-existent-id', // Invalid project ID
            'assignee_id' => 'non-existent-user', // Invalid user ID
            'priority' => 'invalid-priority', // Invalid priority
            'status' => 'invalid-status', // Invalid status
            'due_date' => 'invalid-date', // Invalid date
            'estimated_hours' => -5, // Negative hours
            'progress_percent' => 150 // Invalid progress percentage
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'message',
                    'errors'
                ]
            ])
            ->assertJson([
                'success' => false
            ]);

        // Verify no task was created
        $this->assertDatabaseMissing('tasks', [
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function can_retrieve_task_list()
    {
        // Create test tasks
        Task::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority',
                        'due_date',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ])
            ->assertJson([
                'success' => true
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    /** @test */
    public function can_retrieve_specific_task()
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'name' => 'Specific Test Task'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'project_id',
                    'assignee_id',
                    'priority',
                    'status',
                    'due_date',
                    'estimated_hours',
                    'progress_percent',
                    'created_at',
                    'updated_at',
                    'project',
                    'assignee',
                    'creator',
                    'dependencies'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => 'Specific Test Task'
                ]
            ]);
    }

    /** @test */
    public function can_update_task()
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'name' => 'Original Task Name'
        ]);

        $updateData = [
            'title' => 'Updated Task Name',
            'description' => 'Updated description',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'progress_percent' => 50,
            'estimated_hours' => 12,
            'actual_hours' => 6
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'progress_percent',
                    'estimated_hours',
                    'actual_hours'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => 'Updated Task Name',
                    'description' => 'Updated description',
                    'status' => 'in_progress',
                    'priority' => 'urgent',
                    'progress_percent' => 50,
                    'estimated_hours' => 12,
                    'actual_hours' => 6
                ],
                'message' => 'Task updated successfully'
            ]);

        // Verify task was updated in database
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Task Name', // Note: API uses 'title' but DB uses 'name'
            'status' => 'in_progress'
        ]);
    }

    /** @test */
    public function can_delete_task()
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        // Verify task was soft deleted
        $this->assertSoftDeleted('tasks', [
            'id' => $task->id
        ]);
    }

    /** @test */
    public function enforces_tenant_isolation()
    {
        // Create task in other tenant
        $otherTask = Task::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->otherUser->id
        ]);

        // Try to access task from different tenant
        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$otherTask->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Access denied: Task belongs to different tenant'
                ]
            ]);

        // Try to update task from different tenant
        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$otherTask->id}", [
                'title' => 'Hacked Task'
            ]);

        $response->assertStatus(403);

        // Try to delete task from different tenant
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tasks/{$otherTask->id}");

        $response->assertStatus(403);

        // Verify other tenant's task is unchanged
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'name' => $otherTask->name,
            'tenant_id' => $this->otherTenant->id
        ]);
    }

    /** @test */
    public function requires_authentication()
    {
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(401);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Test Task'
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function handles_nonexistent_task()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks/non-existent-id');

        $response->assertStatus(404);
    }

    /** @test */
    public function validates_field_names_in_response()
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify standardized field names
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('due_date', $data);
        $this->assertArrayHasKey('estimated_hours', $data);
        $this->assertArrayHasKey('progress_percent', $data);
        
        // Verify no inconsistent field names
        $this->assertArrayNotHasKey('name', $data);
        $this->assertArrayNotHasKey('end_date', $data);
    }

    /** @test */
    public function can_filter_tasks_by_status()
    {
        // Create tasks with different statuses
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);

        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks?status=pending');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('pending', $data[0]['status']);
    }

    /** @test */
    public function can_filter_tasks_by_project()
    {
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);

        // Create tasks for different projects
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id
        ]);

        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $otherProject->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tasks?project_id={$this->project->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->project->id, $data[0]['project_id']);
    }
}
