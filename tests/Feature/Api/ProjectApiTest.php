<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\DatabaseTrait;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature tests cho Project API endpoints
 */
class ProjectApiTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait, WithFaker;
    
    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant);
        $this->apiAs($this->user, $this->tenant);
    }
    
    /**
     * Test get projects list
     */
    public function test_can_get_projects_list(): void
    {
        $user = $this->user;
        
        // Tạo test projects cho tenant của user
        Project::factory()->count(3)->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo projects cho tenant khác (không được trả về)
        Project::factory()->count(2)->create();
        
        $response = $this->getJson('/api/projects');
        
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
            'budget_actual'
                        ]
                    ]
                ])
                ->assertJson([
                    'status' => 'success'
                ]);
        
        // Verify chỉ trả về projects của tenant hiện tại
        $this->assertCount(3, $response->json('data'));
    }
    
    /**
     * Test create new project
     */
    public function test_can_create_new_project(): void
    {
        $user = $this->user;
        
        $projectData = [
            'name' => 'New Test Project',
            'description' => 'Test project description',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning'
        ];
        
        $response = $this->postJson('/api/projects', $projectData);
        
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
    
    /**
     * Test create project với invalid data
     */
    public function test_cannot_create_project_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '', // Required field empty
            'start_date' => 'invalid-date',
            'status' => 'invalid-status'
        ];
        
        $response = $this->postJson('/api/projects', $invalidData);
        
        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details',
                    ]
                ])
                ->assertJson([
                    'error' => [
                        'code' => 'E422.VALIDATION',
                    ]
                ]);
    }
    
    /**
     * Test get specific project
     */
    public function test_can_get_specific_project(): void
    {
        $user = $this->user;
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Specific Test Project'
        ]);
        
        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'project' => [
                            'id',
                            'name',
                            'tenant_id',
                        ],
                        'metrics' => [
                            'project_id',
                            'progress',
                            'tasks' => [
                                'total',
                                'completed',
                                'pending',
                            ],
                            'status',
                        ],
                    ],
                ])
                ->assertJsonPath('data.project.id', $project->id)
                ->assertJsonPath('data.project.name', 'Specific Test Project')
                ->assertJsonPath('data.project.tenant_id', $user->tenant_id);
    }
    
    /**
     * Test cannot access project from different tenant
     */
    public function test_cannot_access_project_from_different_tenant(): void
    {
        // Tạo project cho tenant khác
        $otherProject = Project::factory()->create();
        
        $response = $this->getJson("/api/projects/{$otherProject->id}");

        $response->assertStatus(404)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details',
                    ]
                ])
                ->assertJsonPath('error.message', 'Project not found');
    }
    
    /**
     * Test update project
     */
    public function test_can_update_project(): void
    {
        $user = $this->user;
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Original Name'
        ]);
        
        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'active',
            'start_date' => $project->start_date->format('Y-m-d'),
            'end_date' => $project->end_date->format('Y-m-d'),
        ];
        
        $response = $this->putJson("/api/projects/{$project->id}", $updateData);
        
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
        $user = $this->user;
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        $response = $this->deleteJson("/api/projects/{$project->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data'
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Project deleted successfully',
                    'data' => []
                ]);

        // Verify project soft deleted
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_can_update_project_status(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => Project::STATUS_PLANNING,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/status", [
            'status' => Project::STATUS_ACTIVE,
            'reason' => 'Mobilization approved',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Project status updated successfully',
                'data' => [
                    'id' => $project->id,
                    'status' => Project::STATUS_ACTIVE,
                ],
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    public function test_update_project_status_rejects_invalid_status(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => Project::STATUS_PLANNING,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/status", [
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'id',
                    'code',
                    'message',
                    'details',
                ],
            ])
            ->assertJsonPath('error.code', 'E422.VALIDATION');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_PLANNING,
        ]);
    }

    public function test_update_project_status_rejects_tenant_mismatch(): void
    {
        $otherTenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/status", [
            'status' => Project::STATUS_ACTIVE,
        ]);

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'E404.NOT_FOUND')
            ->assertJsonPath('error.message', 'Project not found');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_PLANNING,
        ]);
    }

    public function test_update_project_status_rejects_missing_permission(): void
    {
        $userWithoutWritePermission = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'project-status-readonly'],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'description' => 'Readonly project status test role',
                'is_active' => true,
            ]
        );

        $userWithoutWritePermission->roles()->syncWithoutDetaching([$role->id]);
        $userWithoutWritePermission->systemRoles()->syncWithoutDetaching([$role->id]);

        $this->apiAs($userWithoutWritePermission, $this->tenant);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/status", [
            'status' => Project::STATUS_ACTIVE,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'RBAC_ACCESS_DENIED')
            ->assertJsonPath('error.message', 'You do not have sufficient RBAC assignments to access this resource');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_PLANNING,
        ]);
    }
}
