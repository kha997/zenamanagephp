<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Permission;
use App\Models\Role;

/**
 * Feature tests for Project management endpoints
 */
class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private string $token;
    private Role $projectManagerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->ensureProjectPermissions();
    }

    private function ensureProjectPermissions(): void
    {
        $permissionDefinitions = [
            'projects.view' => 'view',
            'projects.create' => 'create',
            'projects.update' => 'update',
            'projects.delete' => 'delete',
            'project.write' => 'write',
        ];

        $permissionIds = [];
        foreach ($permissionDefinitions as $code => $action) {
            [$module] = explode('.', $code, 2);
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                [
                    'module' => $module,
                    'action' => $action,
                    'description' => "Test permission for {$code}",
                    'name' => $code,
                ]
            );

            if ($permission->name !== $code) {
                $permission->forceFill(['name' => $code])->save();
            }

            $permissionIds[] = $permission->id;
        }

        $role = Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'description' => 'Project manager role for API tests',
                'allow_override' => true,
                'is_active' => true,
                'tenant_id' => $this->tenant->id,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $this->user->roles()->syncWithoutDetaching($role->id);
        $this->projectManagerRole = $role;
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

        $response = $this->apiPostTenant($this->user, (string) $this->tenant->id, '/api/projects', $projectData, $this->token);

        if ($response->status() !== 201) {
            $response->dump();
        }

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

        self::assertStringStartsWith('PRJ-', $response->json('data.code'));

        $this->assertDatabaseHas('projects', [
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

        $response = $this->apiGetTenant($this->user, (string) $this->tenant->id, '/api/projects', $this->token);

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

        $project->users()->syncWithoutDetaching([
            $this->user->id => [
                'role_id' => $this->projectManagerRole->id,
                'id' => (string) Str::ulid(),
            ],
        ]);

        $response = $this->apiGetTenant($this->user, (string) $this->tenant->id, "/api/projects/{$project->id}", $this->token);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'project' => [
                            'id',
                            'tenant_id',
                            'code',
                            'name',
                            'description',
                            'status',
                            'progress',
                            'client',
                            'project_manager',
                            'team_members',
                            'tasks',
                            'documents',
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
            'progress' => 100,
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
        ];

        $response = $this->apiPutTenant($this->user, (string) $this->tenant->id, "/api/projects/{$project->id}", $updateData, $this->token);

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

        $response = $this->apiDeleteTenant($this->user, (string) $this->tenant->id, "/api/projects/{$project->id}", $this->token);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Project deleted successfully'
                ]);

        $this->assertSoftDeleted('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test project validation
     */
    public function test_create_project_validation(): void
    {
        $response = $this->apiPostTenant($this->user, (string) $this->tenant->id, '/api/projects', [], $this->token);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error' => [
                        'details' => [
                            'validation' => [
                                'name',
                                'start_date',
                                'end_date',
                            ]
                        ]
                    ]
                ]);

        $response->assertJsonPath('error.details.validation.name.0', 'Tên dự án là bắt buộc.');
        $response->assertJsonPath('error.details.validation.start_date.0', 'Ngày bắt đầu là bắt buộc.');
        $response->assertJsonPath('error.details.validation.end_date.0', 'Ngày kết thúc là bắt buộc.');
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
            'Authorization' => 'Bearer ' . $otherToken,
            'X-Tenant-ID' => (string) $otherTenant->id,
            'Accept' => 'application/json'
        ])->getJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
    }
}
