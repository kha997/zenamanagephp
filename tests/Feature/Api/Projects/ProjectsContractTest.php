<?php

namespace Tests\Feature\Api\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ProjectsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        
        // Create test projects
        $this->projects = Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function projects_api_returns_correct_response_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'progress',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ]
            ]);
    }

    /** @test */
    public function projects_api_supports_pagination()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/projects?page=1&per_page=2');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 2,
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /** @test */
    public function projects_api_supports_filtering()
    {
        Sanctum::actingAs($this->user);

        // Update one project to active status
        $this->projects->first()->update(['status' => 'active']);
        // Ensure other projects have different status
        $this->projects->skip(1)->each(function($project) {
            $project->update(['status' => 'planning']);
        });

        $response = $this->json('GET', '/api/projects?status=active');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('active', $data[0]['status']);
    }

    /** @test */
    public function projects_api_supports_search()
    {
        Sanctum::actingAs($this->user);

        // Update one project with specific name
        $this->projects->first()->update(['name' => 'Test Project Search']);

        $response = $this->json('GET', '/api/projects?search=Test Project');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Test Project', $data[0]['name']);
    }

    /** @test */
    public function single_project_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $project = $this->projects->first();

        $response = $this->json('GET', "/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'progress',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    /** @test */
    public function create_project_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $projectData = [
            'name' => 'New Test Project',
            'description' => 'Test project description',
            'code' => 'TEST-001',
            'status' => 'planning',
            'priority' => 'medium',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(30)->format('Y-m-d'),
        ];

        $response = $this->json('POST', '/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'New Test Project',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function update_project_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $project = $this->projects->first();

        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'active',
        ];

        $response = $this->json('PUT', "/api/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'status',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function delete_project_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $project = $this->projects->first();

        $response = $this->json('DELETE', "/api/projects/{$project->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    /** @test */
    public function projects_api_respects_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);

        // Create projects for other tenant
        Project::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/projects');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(3, $data); // Only our tenant's projects
        
        // Verify all returned projects belong to our tenant
        foreach ($data as $project) {
            $this->assertEquals($this->tenant->id, $project['tenant_id']);
        }
    }
}
