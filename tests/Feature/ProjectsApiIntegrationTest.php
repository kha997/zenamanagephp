<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;

/**
 * Projects API Integration Test
 *
 * Tests the Projects API functionality including:
 * - Project creation, reading, updating, deletion
 * - Tenant isolation
 * - Permission checks
 * - Response structure validation
 * - Error handling
 */
class ProjectsApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;
    protected Tenant $tenant;
    protected Tenant $otherTenant;

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

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function can_create_project_with_valid_data()
    {
        $projectData = [
            'name' => 'Test Project',
            'description' => 'A test project for integration testing',
            'budget_total' => 50000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'planning',
            'priority' => 'high',
            'category' => 'development',
            'tags' => ['test', 'integration'],
            'is_public' => false,
            'requires_approval' => true
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'budget_total',
                    'start_date',
                    'end_date',
                    'status',
                    'priority',
                    'category',
                    'tags',
                    'is_public',
                    'requires_approval',
                    'created_at',
                    'updated_at'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Project',
                    'description' => 'A test project for integration testing',
                    'budget_total' => 50000,
                    'status' => 'planning',
                    'priority' => 'high',
                    'category' => 'development',
                    'tags' => ['test', 'integration'],
                    'is_public' => false,
                    'requires_approval' => true
                ],
                'message' => 'Project created successfully'
            ]);

        // Verify project was created in database
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $this->tenant->id,
            'status' => 'planning'
        ]);
    }

    /** @test */
    public function cannot_create_project_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Required field empty
            'budget_total' => -1000, // Negative budget
            'start_date' => 'invalid-date', // Invalid date
            'end_date' => now()->subDays(1)->toDateString(), // End date before start date
            'status' => 'invalid-status', // Invalid status
            'priority' => 'invalid-priority' // Invalid priority
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/projects', $invalidData);

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

        // Verify no project was created
        $this->assertDatabaseMissing('projects', [
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function can_retrieve_project_list()
    {
        // Create test projects
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'priority',
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
    public function can_retrieve_specific_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Specific Test Project'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'budget_total',
                    'start_date',
                    'end_date',
                    'status',
                    'priority',
                    'created_at',
                    'updated_at',
                    'tasks',
                    'documents',
                    'teams'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'name' => 'Specific Test Project'
                ]
            ]);
    }

    /** @test */
    public function can_update_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Original Project Name'
        ]);

        $updateData = [
            'name' => 'Updated Project Name',
            'description' => 'Updated description',
            'status' => 'active',
            'priority' => 'high',
            'progress_percent' => 25
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'priority',
                    'progress_percent'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'name' => 'Updated Project Name',
                    'description' => 'Updated description',
                    'status' => 'active',
                    'priority' => 'high',
                    'progress_percent' => 25
                ],
                'message' => 'Project updated successfully'
            ]);

        // Verify project was updated in database
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'active'
        ]);
    }

    /** @test */
    public function can_delete_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Project deleted successfully'
                ]
            ]);

        // Verify project was soft deleted
        $this->assertSoftDeleted('projects', [
            'id' => $project->id
        ]);
    }

    /** @test */
    public function enforces_tenant_isolation()
    {
        // Create project in other tenant
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'user_id' => $this->otherUser->id
        ]);

        // Try to access project from different tenant
        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$otherProject->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Project not found'
                ]
            ]);

        // Try to update project from different tenant
        $response = $this->actingAs($this->user)
            ->putJson("/api/projects/{$otherProject->id}", [
                'name' => 'Hacked Project'
            ]);

        $response->assertStatus(404);

        // Try to delete project from different tenant
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/projects/{$otherProject->id}");

        $response->assertStatus(404);

        // Verify other tenant's project is unchanged
        $this->assertDatabaseHas('projects', [
            'id' => $otherProject->id,
            'name' => $otherProject->name,
            'tenant_id' => $this->otherTenant->id
        ]);
    }

    /** @test */
    public function requires_authentication()
    {
        $response = $this->getJson('/api/projects');
        $response->assertStatus(401);

        $response = $this->postJson('/api/projects', [
            'name' => 'Test Project'
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function requires_proper_permissions()
    {
        // Create user with insufficient permissions
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        $response = $this->actingAs($member)
            ->postJson('/api/projects', [
                'name' => 'Test Project'
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function handles_nonexistent_project()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/projects/non-existent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Project not found'
                ]
            ]);
    }

    /** @test */
    public function validates_field_names_in_response()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify standardized field names
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('budget_total', $data);
        $this->assertArrayHasKey('start_date', $data);
        $this->assertArrayHasKey('end_date', $data);
        $this->assertArrayHasKey('progress_percent', $data);
        
        // Verify no inconsistent field names
        $this->assertArrayNotHasKey('title', $data);
        $this->assertArrayNotHasKey('progress_pct', $data);
    }
}
