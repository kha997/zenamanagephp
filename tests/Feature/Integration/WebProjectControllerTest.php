<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;

class WebProjectControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user with tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
    }

    /**
     * Test web project index with session authentication
     */
    public function test_web_project_index_requires_authentication(): void
    {
        $response = $this->get('/app/projects');
        
        $response->assertStatus(302); // Redirect to login
        $response->assertRedirect('/login');
    }

    /**
     * Test web project index with proper tenant isolation
     */
    public function test_web_project_index_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        
        // Create projects for both tenants
        $ourProject = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $theirProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        // Authenticate via session
        $this->actingAs($this->user);
        
        $response = $this->get('/app/projects');
        
        $response->assertStatus(200);
        $response->assertViewIs('app.projects.index');
        
        // Verify view data contains only our projects
        $viewData = $response->viewData('projects');
        $this->assertCount(1, $viewData);
        $this->assertEquals($ourProject->id, $viewData->first()->id);
    }

    /**
     * Test web project creation with validation
     */
    public function test_web_project_creation_with_validation(): void
    {
        $this->actingAs($this->user);
        
        $projectData = [
            'name' => 'Web Test Project',
            'code' => 'WEB-001',
            'description' => 'Web test project description',
            'status' => 'active',
            'priority' => 'normal',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'tags' => ['web', 'test']
        ];
        
        $response = $this->post('/app/projects', $projectData);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Project created successfully'
        ]);
        
        // Verify project was created with correct tenant_id
        $this->assertDatabaseHas('projects', [
            'name' => 'Web Test Project',
            'code' => 'WEB-001',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test web project creation validation errors
     */
    public function test_web_project_creation_validation_errors(): void
    {
        $this->actingAs($this->user);
        
        $invalidData = [
            'name' => '', // Required field empty
            'code' => 'INVALID_CODE_TOO_LONG', // Too long
            'status' => 'invalid_status' // Invalid enum
        ];
        
        $response = $this->post('/app/projects', $invalidData);
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed'
        ]);
        $response->assertJsonStructure([
            'errors'
        ]);
    }

    /**
     * Test web project update with authorization
     */
    public function test_web_project_update_with_authorization(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);
        
        $this->actingAs($this->user);
        
        $updateData = [
            'name' => 'Updated Web Project Name',
            'status' => 'completed',
            'progress_pct' => 100
        ];
        
        $response = $this->put("/app/projects/{$project->id}", $updateData);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Project updated successfully'
        ]);
        
        // Verify database was updated
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Web Project Name',
            'status' => 'completed',
            'progress_pct' => 100
        ]);
    }

    /**
     * Test web project update with tenant isolation
     */
    public function test_web_project_update_respects_tenant_isolation(): void
    {
        // Create another tenant and project
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $this->actingAs($this->user);
        
        $updateData = [
            'name' => 'Hacked Web Project Name'
        ];
        
        $response = $this->put("/app/projects/{$otherProject->id}", $updateData);
        
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Project not found'
        ]);
        
        // Verify other tenant's project was not modified
        $this->assertDatabaseMissing('projects', [
            'id' => $otherProject->id,
            'name' => 'Hacked Web Project Name'
        ]);
    }

    /**
     * Test web project deletion with proper authorization
     */
    public function test_web_project_deletion_with_authorization(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->delete("/app/projects/{$project->id}");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Project deleted successfully'
        ]);
        
        // Verify project was soft deleted
        $this->assertSoftDeleted('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test web project deletion with tenant isolation
     */
    public function test_web_project_deletion_respects_tenant_isolation(): void
    {
        // Create another tenant and project
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $this->actingAs($this->user);
        
        $response = $this->delete("/app/projects/{$otherProject->id}");
        
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Project not found'
        ]);
        
        // Verify other tenant's project was not deleted
        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test web project show with tenant isolation
     */
    public function test_web_project_show_respects_tenant_isolation(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/projects/{$project->id}");
        
        $response->assertStatus(200);
        $response->assertViewIs('app.projects.show');
        
        // Verify view data contains our project
        $viewData = $response->viewData('project');
        $this->assertEquals($project->id, $viewData->id);
    }

    /**
     * Test web project show with cross-tenant access
     */
    public function test_web_project_show_blocks_cross_tenant_access(): void
    {
        // Create another tenant and project
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/projects/{$otherProject->id}");
        
        $response->assertStatus(404);
    }

    /**
     * Test web project archive functionality
     */
    public function test_web_project_archive(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => 'active'
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->post("/app/projects/{$project->id}/archive");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Project archived successfully'
        ]);
        
        // Verify project was archived
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'archived'
        ]);
    }

    /**
     * Test web project restore functionality
     */
    public function test_web_project_restore(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => 'archived'
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->post("/app/projects/{$project->id}/restore");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Project restored successfully'
        ]);
        
        // Verify project was restored
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'active'
        ]);
    }

    /**
     * Test web project filtering and search
     */
    public function test_web_project_filtering_and_search(): void
    {
        // Create multiple projects with different attributes
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Web Project',
            'status' => 'active',
            'priority' => 'high'
        ]);
        
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Beta Web Project',
            'status' => 'completed',
            'priority' => 'low'
        ]);
        
        $this->actingAs($this->user);
        
        // Test search by name
        $response = $this->get('/app/projects?q=Alpha');
        $response->assertStatus(200);
        $viewData = $response->viewData('projects');
        $this->assertCount(1, $viewData);
        $this->assertEquals($project1->id, $viewData->first()->id);
        
        // Test filter by status
        $response = $this->get('/app/projects?status=completed');
        $response->assertStatus(200);
        $viewData = $response->viewData('projects');
        $this->assertCount(1, $viewData);
        $this->assertEquals($project2->id, $viewData->first()->id);
    }

    /**
     * Test web project pagination
     */
    public function test_web_project_pagination(): void
    {
        // Create 30 projects
        Project::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);
        
        $this->actingAs($this->user);
        
        // Test first page
        $response = $this->get('/app/projects?per_page=10');
        $response->assertStatus(200);
        
        $viewData = $response->viewData('projects');
        $this->assertCount(10, $viewData);
        
        // Test second page
        $response = $this->get('/app/projects?per_page=10&page=2');
        $response->assertStatus(200);
        
        $viewData = $response->viewData('projects');
        $this->assertCount(10, $viewData);
    }

    /**
     * Test web project error handling
     */
    public function test_web_project_error_handling(): void
    {
        $this->actingAs($this->user);
        
        // Test 404 error
        $response = $this->get('/app/projects/non-existent-id');
        $response->assertStatus(404);
        
        // Test invalid method
        $response = $this->patch('/app/projects/1');
        $response->assertStatus(405); // Method Not Allowed
    }

    /**
     * Test web project audit logging
     */
    public function test_web_project_audit_logging(): void
    {
        $this->actingAs($this->user);
        
        $projectData = [
            'name' => 'Web Audit Test Project',
            'code' => 'WEB-AUDIT-001',
            'status' => 'active'
        ];
        
        $response = $this->post('/app/projects', $projectData);
        $response->assertStatus(200);
        
        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'project.create',
            'entity_type' => 'project'
        ]);
    }

    /**
     * Test comprehensive web project tenant isolation
     */
    public function test_comprehensive_web_tenant_isolation(): void
    {
        // Create two tenants with users and projects
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $project1 = Project::factory()->create(['tenant_id' => $tenant1->id]);
        $project2 = Project::factory()->create(['tenant_id' => $tenant2->id]);
        
        // Test user1 can only see tenant1 projects
        $this->actingAs($user1);
        $response = $this->get('/app/projects');
        $response->assertStatus(200);
        $viewData = $response->viewData('projects');
        $this->assertCount(1, $viewData);
        $this->assertEquals($project1->id, $viewData->first()->id);
        
        // Test user1 cannot access tenant2 project
        $response = $this->get("/app/projects/{$project2->id}");
        $response->assertStatus(404);
        
        // Test user2 can only see tenant2 projects
        $this->actingAs($user2);
        $response = $this->get('/app/projects');
        $response->assertStatus(200);
        $viewData = $response->viewData('projects');
        $this->assertCount(1, $viewData);
        $this->assertEquals($project2->id, $viewData->first()->id);
        
        // Test user2 cannot access tenant1 project
        $response = $this->get("/app/projects/{$project1->id}");
        $response->assertStatus(404);
    }
}