<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

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
 * Tests for Projects and Tasks API cross-tenant isolation
 * 
 * Tests that projects and tasks endpoints properly enforce tenant isolation
 * and prevent cross-tenant access via API routes.
 * 
 * Round 27: Security / RBAC Hardening
 * 
 * @group tenant-projects-tasks-isolation
 * @group tenant-permissions
 */
class TenantProjectsTasksIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Project $projectA;
    private Project $projectB;
    private Task $taskA;
    private Task $taskB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('tenant-projects-tasks-isolation');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create project A in tenant A
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Project',
        ]);
        
        // Create project B in tenant B
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Project',
        ]);
        
        // Create task A in tenant A
        $this->taskA = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Tenant A Task',
        ]);
        
        // Create task B in tenant B
        $this->taskB = Task::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'name' => 'Tenant B Task',
        ]);
    }

    /**
     * Test that tenant A cannot see projects from tenant B
     */
    public function test_tenant_a_cannot_see_tenant_b_projects(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/projects');
        
        $response->assertStatus(200);
        $projects = $response->json('data', []);
        
        // Verify project B is not in the list
        $projectIds = array_column($projects, 'id');
        $this->assertNotContains($this->projectB->id, $projectIds, 'Tenant B project should not be visible in tenant A');
        $this->assertContains($this->projectA->id, $projectIds, 'Tenant A project should be visible');
    }

    /**
     * Test that tenant A cannot access project B directly
     */
    public function test_tenant_a_cannot_access_tenant_b_project(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/projects/{$this->projectB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to access tenant B project');
    }

    /**
     * Test that tenant A cannot update project B
     */
    public function test_tenant_a_cannot_update_tenant_b_project(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/projects/{$this->projectB->id}", [
            'name' => 'Hacked Project Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B project');
        
        // Verify project B is unchanged
        $this->projectB->refresh();
        $this->assertEquals('Tenant B Project', $this->projectB->name, 'Project should not be modified');
    }

    /**
     * Test that tenant A cannot delete project B
     */
    public function test_tenant_a_cannot_delete_tenant_b_project(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/projects/{$this->projectB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to delete tenant B project');
        
        // Verify project B still exists
        $this->assertDatabaseHas('projects', [
            'id' => $this->projectB->id,
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test that tenant A cannot see tasks from tenant B
     */
    public function test_tenant_a_cannot_see_tenant_b_tasks(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');
        
        $response->assertStatus(200);
        $tasks = $response->json('data', []);
        
        // Verify task B is not in the list
        $taskIds = array_column($tasks, 'id');
        $this->assertNotContains($this->taskB->id, $taskIds, 'Tenant B task should not be visible in tenant A');
        $this->assertContains($this->taskA->id, $taskIds, 'Tenant A task should be visible');
    }

    /**
     * Test that tenant A cannot access task B directly
     */
    public function test_tenant_a_cannot_access_tenant_b_task(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/tasks/{$this->taskB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to access tenant B task');
    }

    /**
     * Test that tenant A cannot update task B
     */
    public function test_tenant_a_cannot_update_tenant_b_task(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/tasks/{$this->taskB->id}", [
            'name' => 'Hacked Task Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B task');
        
        // Verify task B is unchanged
        $this->taskB->refresh();
        $this->assertEquals('Tenant B Task', $this->taskB->name, 'Task should not be modified');
    }

    /**
     * Test that tenant A cannot delete task B
     */
    public function test_tenant_a_cannot_delete_tenant_b_task(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tasks/{$this->taskB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to delete tenant B task');
        
        // Verify task B still exists
        $this->assertDatabaseHas('tasks', [
            'id' => $this->taskB->id,
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test that tenant A cannot create task in project B
     */
    public function test_tenant_a_cannot_create_task_in_tenant_b_project(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->postJson("/api/v1/app/projects/{$this->projectB->id}/tasks", [
            'name' => 'Hacked Task',
            'description' => 'Should not be created',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to create task in tenant B project');
        
        // Verify task was not created
        $this->assertDatabaseMissing('tasks', [
            'name' => 'Hacked Task',
            'project_id' => $this->projectB->id,
        ]);
    }

    /**
     * Test that tenant A cannot get tasks for project B
     */
    public function test_tenant_a_cannot_get_tasks_for_tenant_b_project(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/projects/{$this->projectB->id}/tasks");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to get tasks for tenant B project');
    }

    /**
     * Test that tenant B cannot see projects from tenant A
     */
    public function test_tenant_b_cannot_see_tenant_a_projects(): void
    {
        Sanctum::actingAs($this->userB);
        $token = $this->userB->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/projects');
        
        $response->assertStatus(200);
        $projects = $response->json('data', []);
        
        // Verify project A is not in the list
        $projectIds = array_column($projects, 'id');
        $this->assertNotContains($this->projectA->id, $projectIds, 'Tenant A project should not be visible in tenant B');
        $this->assertContains($this->projectB->id, $projectIds, 'Tenant B project should be visible');
    }

    /**
     * Test that tenant B cannot see tasks from tenant A
     */
    public function test_tenant_b_cannot_see_tenant_a_tasks(): void
    {
        Sanctum::actingAs($this->userB);
        $token = $this->userB->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tasks');
        
        $response->assertStatus(200);
        $tasks = $response->json('data', []);
        
        // Verify task A is not in the list
        $taskIds = array_column($tasks, 'id');
        $this->assertNotContains($this->taskA->id, $taskIds, 'Tenant A task should not be visible in tenant B');
        $this->assertContains($this->taskB->id, $taskIds, 'Tenant B task should be visible');
    }
}

