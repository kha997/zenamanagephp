<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        // Tạo user và login để lấy token
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        // Tạo role và permissions
        $this->createRolesAndPermissions();
        
        // Login để lấy token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        // Tạo permissions
        $permissions = [
            'project.create',
            'project.read',
            'project.update',
            'project.delete',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'project',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        // Tạo role admin
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        // Gán permissions cho role
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        // Gán role cho user
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test get all projects
     */
    public function test_can_get_all_projects()
    {
        // Tạo test data
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/projects');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'projects' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'description',
                                 'start_date',
                                 'end_date',
                                 'status',
                                 'progress',
                                 'created_at',
                                 'updated_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test create project
     */
    public function test_can_create_project()
    {
        $projectData = [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning',
            'planned_cost' => 1000000
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/projects', $projectData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'project' => [
                             'id',
                             'name',
                             'description',
                             'start_date',
                             'end_date',
                             'status',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('projects', [
            'name' => $projectData['name'],
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test get single project
     */
    public function test_can_get_single_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'project' => [
                             'id' => $project->id,
                             'name' => $project->name,
                         ]
                     ]
                 ]);
    }

    /**
     * Test update project
     */
    public function test_can_update_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $updateData = [
            'name' => 'Updated Project Name',
            'description' => 'Updated description',
            'status' => 'in_progress'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'project' => [
                             'id' => $project->id,
                             'name' => 'Updated Project Name',
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name'
        ]);
    }

    /**
     * Test delete project
     */
    public function test_can_delete_project()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Dự án đã được xóa thành công'
                     ]
                 ]);

        $this->assertSoftDeleted('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test validation errors
     */
    public function test_create_project_validation_errors()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/projects', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_to_projects()
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }

    /**
     * Test project not found
     */
    public function test_project_not_found()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/projects/999999');

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Dự án không tồn tại'
                 ]);
    }
}