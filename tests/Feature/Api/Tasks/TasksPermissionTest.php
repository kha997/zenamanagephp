<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tasks;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Tasks API tenant permission enforcement
 * 
 * Tests that tasks endpoints properly enforce tenant.permission middleware
 * and use TenancyService for tenant resolution.
 * 
 * @group tasks
 * @group tenant-permissions
 */
class TasksPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(78901);
        $this->setDomainName('tasks-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);
        
        // Create task
        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'title' => 'Test Task', // Some factories use 'title' instead of 'name'
        ]);
    }

    /**
     * Test that GET /api/v1/app/tasks requires tenant.view_tasks permission
     */
    public function test_get_tasks_requires_view_permission(): void
    {
        // Create user with viewer role (has tenant.view_tasks)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should be able to view tasks
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');

        $response->assertStatus(200);
    }

    /**
     * Test that GET /api/v1/app/tasks returns 403 without tenant.view_tasks permission
     */
    public function test_get_tasks_returns_403_without_permission(): void
    {
        // Create user without tenant role (no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Don't attach to tenant, so user has no tenant permissions

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that POST /api/v1/app/tasks requires tenant.manage_tasks permission
     */
    public function test_create_task_requires_manage_permission(): void
    {
        // Create user with admin role (has tenant.manage_tasks)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Admin should be able to create tasks
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-task-' . uniqid(),
        ])->postJson('/api/v1/app/tasks', [
            'project_id' => (string) $this->project->id,
            'name' => 'New Task',
            'description' => 'Test task description',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', [
            'name' => 'New Task',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test that POST /api/v1/app/tasks returns 403 without tenant.manage_tasks permission
     */
    public function test_create_task_returns_403_without_permission(): void
    {
        // Create user with viewer role (only has tenant.view_tasks, not tenant.manage_tasks)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should NOT be able to create tasks
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-task-viewer-' . uniqid(),
        ])->postJson('/api/v1/app/tasks', [
            'project_id' => (string) $this->project->id,
            'name' => 'New Task',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that member role can view tasks but not manage them
     */
    public function test_member_role_can_view_but_not_manage_tasks(): void
    {
        // Create user with member role
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Member should be able to view tasks
        $viewResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');

        $this->assertContains($viewResponse->status(), [200, 404]);

        // Member should NOT be able to delete tasks (requires tenant.manage_tasks)
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tasks/{$this->task->id}");

        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that tasks are scoped to active tenant (using TenancyService)
     */
    public function test_tasks_are_scoped_to_active_tenant(): void
    {
        // Create another tenant and task
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        
        $otherTask = Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
        ]);

        // Create user with admin role in first tenant
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // User should only see tasks from their active tenant
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');

        $response->assertStatus(200);
        $tasks = $response->json('data.data') ?? $response->json('data') ?? [];
        
        // Verify all returned tasks belong to the active tenant
        foreach ($tasks as $task) {
            $taskTenantId = $task['tenant_id'] ?? $task['project']['tenant_id'] ?? null;
            $this->assertEquals(
                $this->tenant->id,
                $taskTenantId,
                'Tasks should only be from active tenant'
            );
        }
        
        // Verify other tenant's task is not included
        $taskIds = array_column($tasks, 'id');
        $this->assertNotContains($otherTask->id, $taskIds, 'Other tenant task should not be visible');
    }
}

