<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for Project Delete Rules
 * 
 * Tests that projects cannot be deleted if they have tasks
 * 
 * @group projects
 */
class ProjectDeleteTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected $user;
    protected $tenant;
    protected $seedData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(23456);
        $this->setDomainName('projects');
        $this->setupDomainIsolation();
        
        // Seed projects domain test data
        $this->seedData = TestDataSeeder::seedProjectsDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use project manager user from seed data
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@projects-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        // Authenticate user using Sanctum
        Sanctum::actingAs($this->user);
    }

    /**
     * Test delete project không có task → success
     */
    public function test_delete_project_without_tasks_returns_success(): void
    {
        // Create a project without tasks
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
        
        // Verify project was soft deleted
        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    /**
     * Test delete project có task → fail với error code PROJECT_HAS_ACTIVE_TASKS
     */
    public function test_delete_project_with_tasks_returns_error(): void
    {
        // Create a project with tasks
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        // Create a task for the project
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
        ]);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");
        
        $response->assertStatus(409)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.message', fn ($message) => 
                            str_contains($message, 'công việc đang tồn tại')
                        )
                        ->etc()
                );
        
        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test delete project có task done → fail (rule chặn mọi task)
     */
    public function test_delete_project_with_done_tasks_returns_error(): void
    {
        // Create a project with done tasks
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        // Create a done task for the project
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'done',
        ]);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");
        
        $response->assertStatus(409);
        
        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test delete project có task pending → fail
     */
    public function test_delete_project_with_pending_tasks_returns_error(): void
    {
        // Create a project with pending tasks
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        // Create a pending task for the project
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");
        
        $response->assertStatus(409);
        
        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test delete project có soft-deleted task → fail (rule chặn mọi task kể cả soft-deleted)
     */
    public function test_delete_project_with_soft_deleted_tasks_returns_error(): void
    {
        // Create a project
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        // Create and soft-delete a task for the project
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $task->delete(); // Soft delete
        
        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");
        
        $response->assertStatus(409);
        
        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test RBAC - user without delete permission cannot delete project
     */
    public function test_user_without_delete_permission_cannot_delete_project(): void
    {
        // Create a member user (not PM/admin)
        $member = \App\Models\User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        // Create a project owned by PM
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id, // Owned by PM, not member
        ]);

        // Authenticate as member
        Sanctum::actingAs($member);

        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");

        // Should return 403 Forbidden (member cannot delete PM's project)
        $response->assertStatus(403);
        $response->assertJson(fn ($json) =>
            $json->where('success', false)
                ->etc()
        );

        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test tenant isolation - user from tenant A cannot delete tenant B project
     */
    public function test_delete_project_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, [
            'role' => 'pm',
        ]);

        // Create a project in the original tenant
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);

        // Authenticate as user from other tenant
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/app/projects/{$project->id}");

        // Should return 403 or 404 (tenant isolation)
        // Policy typically returns 403, but route model binding might return 404
        $this->assertContains($response->status(), [403, 404]);

        // Verify project was NOT deleted
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'deleted_at' => null,
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify tenant isolation - project still belongs to original tenant
        $this->assertNotEquals($otherTenant->id, $project->fresh()->tenant_id);
        $this->assertEquals($this->tenant->id, $project->fresh()->tenant_id);
    }
}

