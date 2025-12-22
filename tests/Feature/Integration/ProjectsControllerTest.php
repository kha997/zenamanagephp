<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;

class ProjectsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected string $authToken;

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
        
        // Create auth token
        $this->authToken = $this->user->createToken('test-token')->plainTextToken;
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test project index with authentication and tenant isolation
     */
    public function test_project_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/app/projects');
        
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message'
            ]
        ]);
        $response->assertJson([
            'error' => [
                'code' => 'E401.AUTHENTICATION'
            ]
        ]);
    }

    /**
     * Test project index with proper tenant isolation
     */
    public function test_project_index_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        
        // Create projects for both tenants
        $ourProject = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $theirProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        // Authenticate as our user
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/app/projects');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'from',
                'to'
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next'
            ]
        ]);
        
        // Verify we only see our project
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($ourProject->id, $responseData[0]['id']);
        $this->assertNotContains($theirProject->id, array_column($responseData, 'id'));
    }

    /**
     * Test project creation with proper validation and tenant isolation
     */
    public function test_project_creation_with_validation(): void
    {
        Sanctum::actingAs($this->user);
        
        $projectData = [
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project description',
            'status' => 'active',
            'priority' => 'normal',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'tags' => ['test', 'integration']
        ];
        
        $response = $this->postJson('/api/app/projects', $projectData);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'code',
                'status',
                'tenant_id',
                'created_at'
            ]
        ]);
        
        // Verify project was created with correct tenant_id
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test project creation validation errors
     */
    public function test_project_creation_validation_errors(): void
    {
        Sanctum::actingAs($this->user);
        
        $invalidData = [
            'name' => '', // Required field empty
            'code' => 'INVALID_CODE_TOO_LONG', // Too long
            'status' => 'invalid_status', // Invalid enum
            'priority' => 'invalid_priority' // Invalid enum
        ];
        
        $response = $this->postJson('/api/app/projects', $invalidData);
        
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'fields'
            ]
        ]);
        $response->assertJson([
            'error' => [
                'code' => 'E422.VALIDATION'
            ]
        ]);
    }

    /**
     * Test project update with authorization
     */
    public function test_project_update_with_authorization(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);
        
        Sanctum::actingAs($this->user);
        
        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'completed',
            'progress_pct' => 100
        ];
        
        $response = $this->putJson("/api/app/projects/{$project->id}", $updateData);
        
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'name' => 'Updated Project Name',
                'status' => 'completed',
                'progress_pct' => 100
            ]
        ]);
        
        // Verify database was updated
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'completed',
            'progress_pct' => 100
        ]);
    }

    /**
     * Test project update with tenant isolation
     */
    public function test_project_update_respects_tenant_isolation(): void
    {
        // Create another tenant and project
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        Sanctum::actingAs($this->user);
        
        $updateData = [
            'name' => 'Hacked Project Name'
        ];
        
        $response = $this->putJson("/api/app/projects/{$otherProject->id}", $updateData);
        
        $response->assertStatus(404);
        $response->assertJson([
            'error' => [
                'code' => 'E404.NOT_FOUND'
            ]
        ]);
        
        // Verify other tenant's project was not modified
        $this->assertDatabaseMissing('projects', [
            'id' => $otherProject->id,
            'name' => 'Hacked Project Name'
        ]);
    }

    /**
     * Test project deletion with proper authorization
     */
    public function test_project_deletion_with_authorization(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson("/api/app/projects/{$project->id}");
        
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Project deleted successfully'
        ]);
        
        // Verify project was soft deleted
        $this->assertSoftDeleted('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test project deletion with tenant isolation
     */
    public function test_project_deletion_respects_tenant_isolation(): void
    {
        // Create another tenant and project
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson("/api/app/projects/{$otherProject->id}");
        
        $response->assertStatus(404);
        $response->assertJson([
            'error' => [
                'code' => 'E404.NOT_FOUND'
            ]
        ]);
        
        // Verify other tenant's project was not deleted
        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test project filtering and search
     */
    public function test_project_filtering_and_search(): void
    {
        // Create multiple projects with different attributes
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Project',
            'status' => 'active',
            'priority' => 'high'
        ]);
        
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Beta Project',
            'status' => 'completed',
            'priority' => 'low'
        ]);
        
        Sanctum::actingAs($this->user);
        
        // Test search by name
        $response = $this->getJson('/api/app/projects?q=Alpha');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($project1->id, $responseData[0]['id']);
        
        // Test filter by status
        $response = $this->getJson('/api/app/projects?status=completed');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($project2->id, $responseData[0]['id']);
        
        // Test filter by priority
        $response = $this->getJson('/api/app/projects?priority=high');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($project1->id, $responseData[0]['id']);
    }

    /**
     * Test project pagination
     */
    public function test_project_pagination(): void
    {
        // Create 30 projects
        Project::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);
        
        Sanctum::actingAs($this->user);
        
        // Test first page
        $response = $this->getJson('/api/app/projects?per_page=10');
        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertCount(10, $responseData['data']);
        $this->assertEquals(1, $responseData['meta']['current_page']);
        $this->assertEquals(3, $responseData['meta']['last_page']);
        $this->assertEquals(30, $responseData['meta']['total']);
        
        // Test second page
        $response = $this->getJson('/api/app/projects?per_page=10&page=2');
        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertCount(10, $responseData['data']);
        $this->assertEquals(2, $responseData['meta']['current_page']);
    }

    /**
     * Test project KPI caching
     */
    public function test_project_kpi_caching(): void
    {
        // Create some projects
        Project::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        
        Sanctum::actingAs($this->user);
        
        // First request should cache the data
        $response1 = $this->getJson('/api/app/projects/kpis');
        $response1->assertStatus(200);
        
        // Second request should use cache
        $response2 = $this->getJson('/api/app/projects/kpis');
        $response2->assertStatus(200);
        
        // Responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    /**
     * Test project export functionality
     */
    public function test_project_export(): void
    {
        Project::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/app/projects/export?format=csv');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
        $response->assertHeader('Content-Disposition');
        
        // Verify CSV content
        $csvContent = $response->getContent();
        $this->assertStringContainsString('Name,Code,Status', $csvContent);
        $this->assertStringContainsString('Test Project', $csvContent);
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting(): void
    {
        Sanctum::actingAs($this->user);
        
        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/app/projects');
            $response->assertStatus(200);
        }
        
        // Should still work within rate limit
        $response = $this->getJson('/api/app/projects');
        $response->assertStatus(200);
    }

    /**
     * Test error handling with structured error responses
     */
    public function test_error_handling_structure(): void
    {
        Sanctum::actingAs($this->user);
        
        // Test 404 error
        $response = $this->getJson('/api/app/projects/non-existent-id');
        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message'
            ]
        ]);
        $response->assertJson([
            'error' => [
                'code' => 'E404.NOT_FOUND'
            ]
        ]);
        
        // Verify X-Request-ID header is present
        $response->assertHeader('X-Request-ID');
    }

    /**
     * Test audit logging
     */
    public function test_audit_logging(): void
    {
        Sanctum::actingAs($this->user);
        
        $projectData = [
            'name' => 'Audit Test Project',
            'code' => 'AUDIT-001',
            'status' => 'active'
        ];
        
        $response = $this->postJson('/api/app/projects', $projectData);
        $response->assertStatus(201);
        
        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'project.create',
            'entity_type' => 'project'
        ]);
    }

    /**
     * Test multi-tenant data isolation comprehensively
     */
    public function test_comprehensive_tenant_isolation(): void
    {
        // Create two tenants with users and projects
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $project1 = Project::factory()->create(['tenant_id' => $tenant1->id]);
        $project2 = Project::factory()->create(['tenant_id' => $tenant2->id]);
        
        // Test user1 can only see tenant1 projects
        Sanctum::actingAs($user1);
        $response = $this->getJson('/api/app/projects');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($project1->id, $responseData[0]['id']);
        
        // Test user1 cannot access tenant2 project
        $response = $this->getJson("/api/app/projects/{$project2->id}");
        $response->assertStatus(404);
        
        // Test user2 can only see tenant2 projects
        Sanctum::actingAs($user2);
        $response = $this->getJson('/api/app/projects');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($project2->id, $responseData[0]['id']);
        
        // Test user2 cannot access tenant1 project
        $response = $this->getJson("/api/app/projects/{$project1->id}");
        $response->assertStatus(404);
    }
}