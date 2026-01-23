<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\RbacTestTrait;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature tests cho Project API endpoints
 */
class ProjectApiTest extends TestCase
{
    use DatabaseTrait, RbacTestTrait, WithFaker;
    
    /**
     * Test get projects list
     */
    public function test_can_get_projects_list(): void
    {
        $context = $this->actingAsWithPermissions(['project.read']);
        $user = $context['user'];
        
        // Tạo test projects cho tenant của user
        Project::factory()->count(3)->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo projects cho tenant khác (không được trả về)
        Project::factory()->count(2)->create();
        
        $response = $this->getJson('/api/v1/projects');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'start_date',
                            'end_date',
                            'status',
                            'progress',
                            'actual_cost'
                        ]
                    ]
                ])
                ->assertJson([
                    'status' => 'success'
                ]);
        // Verify chỉ trả về projects của tenant hiện tại
        $this->assertCount(3, $response->json('data'));
    }

    public function test_cannot_list_projects_without_project_read_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to view projects'
                ]);
    }
    
    /**
     * Test create new project
     */
    public function test_can_create_new_project(): void
    {
        $context = $this->actingAsWithPermissions(['project.write']);
        $user = $context['user'];
        
        $projectData = [
            'name' => 'New Test Project',
            'description' => 'Test project description',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning'
        ];

        $response = $this->postJson('/api/v1/projects', $projectData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'name',
                        'description',
                        'tenant_id',
                        'status'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'name' => 'New Test Project',
                        'tenant_id' => $user->tenant_id,
                        'status' => 'planning'
                    ]
                ]);
        
        // Verify project được tạo trong database
        $this->assertDatabaseHas('projects', [
            'name' => 'New Test Project',
            'tenant_id' => $user->tenant_id
        ]);
    }

    public function test_cannot_create_project_without_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $projectData = [
            'name' => 'Unauthorized Project',
            'description' => 'Should be blocked by RBAC',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning'
        ];

        $response = $this->postJson('/api/v1/projects', $projectData);

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to create projects'
                ]);
    }

    public function test_project_store_response_team_members_are_brief(): void
    {
        $this->actingAsWithPermissions(['project.write']);

        $projectData = [
            'name' => 'Brief Team Project',
            'description' => 'Ensure team members serialization is brief',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d'),
            'status' => 'planning'
        ];

        $response = $this->postJson('/api/zena/projects', $projectData);

        $response->assertStatus(201)
                ->assertJsonPath('data.teamMembers', [])
                ->assertJsonMissingPath('data.teamMembers.0.projects');
    }
    
    /**
     * Test create project với invalid data
     */
    public function test_cannot_create_project_with_invalid_data(): void
    {
        $this->actingAsWithPermissions(['project.write']);
        
        $invalidData = [
            'name' => '', // Required field empty
            'start_date' => 'invalid-date',
            'status' => 'invalid-status'
        ];
        
        $response = $this->postJson('/api/v1/projects', $invalidData);
        $response->assertStatus(422)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'errors'
                ])
                ->assertJson([
                    'status' => 'error'
                ]);
    }
    
    /**
     * Test get specific project
     */
    public function test_can_get_specific_project(): void
    {
        $context = $this->actingAsWithPermissions(['project.read']);
        $user = $context['user'];
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Specific Test Project'
        ]);
        
        $response = $this->getJson("/api/v1/projects/{$project->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'id' => $project->id,
                        'name' => 'Specific Test Project',
                        'tenant_id' => $user->tenant_id
                    ]
                ]);
    }
    
    /**
     * Test cannot access project from different tenant
     */
    public function test_cannot_access_project_from_different_tenant(): void
    {
        $this->actingAsWithPermissions(['project.read']);
        
        // Tạo project cho tenant khác
        $otherProject = Project::factory()->create();
        
        $response = $this->getJson("/api/v1/projects/{$otherProject->id}");
        
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Project not found'
                ]);
    }
    
    /**
     * Test update project
     */
    public function test_can_update_project(): void
    {
        $context = $this->actingAsWithPermissions(['project.write']);
        $user = $context['user'];
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Original Name',
            'status' => 'planning'
        ]);
        
        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'active'
        ];
        
        $response = $this->putJson("/api/v1/projects/{$project->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'id' => $project->id,
                        'name' => 'Updated Project Name',
                        'status' => 'active'
                    ]
                ]);
        
        // Verify database được update
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'active'
        ]);
    }
    
    /**
     * Test delete project
     */
    public function test_can_delete_project(): void
    {
        $context = $this->actingAsWithPermissions(['project.write']);
        $user = $context['user'];
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        $response = $this->deleteJson("/api/v1/projects/{$project->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'message' => 'Project deleted successfully'
                    ]
                ]);
        
        // Verify soft delete
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
}
