<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;

/**
 * Feature tests for Project management endpoints
 */
class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test creating a new project
     */
    public function test_create_project(): void
    {
        $projectData = [
            'code' => 'PRJ-TEST-001',
            'name' => 'Test Project',
            'description' => 'A test project for unit testing',
            'status' => 'active',
            'progress' => 0,
            'budget_total' => 100000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'status',
                        'progress',
                        'budget_total',
                        'start_date',
                        'end_date',
                        'tenant_id'
                    ]
                ]);

        $this->assertDatabaseHas('projects', [
            'code' => 'PRJ-TEST-001',
            'name' => 'Test Project',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test getting all projects
     */
    public function test_get_projects(): void
    {
        // Create some test projects
        Project::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/projects');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'description',
                            'status',
                            'progress',
                            'budget_total'
                        ]
                    ]
                ]);

        $this->assertEquals(3, count($response->json('data')));
    }

    /**
     * Test getting a specific project
     */
    public function test_get_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'status',
                        'progress',
                        'budget_total'
                    ]
                ]);
    }

    /**
     * Test updating a project
     */
    public function test_update_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $updateData = [
            'name' => 'Updated Project Name',
            'description' => 'Updated project description',
            'status' => 'completed',
            'progress' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson("/api/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'name' => 'Updated Project Name',
                        'description' => 'Updated project description',
                        'status' => 'completed',
                        'progress' => 100
                    ]
                ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'completed'
        ]);
    }

    /**
     * Test deleting a project
     */
    public function test_delete_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Project deleted successfully'
                ]);

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test project validation
     */
    public function test_create_project_validation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/projects', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'code']);
    }

    /**
     * Test accessing project without authentication
     */
    public function test_access_project_without_auth(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertStatus(401);
    }

    /**
     * Test accessing project from different tenant
     */
    public function test_access_project_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherToken = $otherUser->createToken('test-token')->plainTextToken;
        
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
    }
}
