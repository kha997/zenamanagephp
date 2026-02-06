<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $token;

    private function apiHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
        ];
    }

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
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('token');
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
            'project.write',
            'project.update',
            'project.delete',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::updateOrCreate(
                ['name' => $permissionCode],
                [
                    'code' => $permissionCode,
                    'module' => 'project',
                    'action' => explode('.', $permissionCode)[1] ?? 'access',
                    'description' => 'Permission for ' . $permissionCode
                ]
            );
        }
        
        // Tạo role admin (lowercase)
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'system',
                'description' => 'System Administrator'
            ]
        );

        // Tạo role Admin (CamelCase) để thỏa điều kiện controller cũ
        $adminRoleCamel = Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                'scope' => 'system',
                'description' => 'System Administrator'
            ]
        );
        
        $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
        
        // Gán permissions cho các role
        $adminRole->permissions()->syncWithoutDetaching($permissionIds);
        $adminRoleCamel->permissions()->syncWithoutDetaching($permissionIds);
        
        // Gán role cho user
        $this->user->roles()->syncWithoutDetaching([$adminRole->id, $adminRoleCamel->id]);
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

        $response = $this->withHeaders($this->apiHeaders())
                         ->getJson('/api/projects');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'status',
                     'status_text',
                     'data',
                     'meta' => [
                         'pagination' => [
                             'page',
                             'per_page',
                             'total',
                             'last_page'
                         ]
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

        $response = $this->withHeaders($this->apiHeaders())
                         ->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'status',
                     'status_text',
                     'data' => [
                         'id',
                         'name',
                         'description',
                         'tenant_id',
                         'status',
                         'created_at',
                         'updated_at'
                     ]
                 ])
                 ->assertJsonPath('data.name', $projectData['name'])
                 ->assertJsonPath('data.tenant_id', (string) $this->tenant->id);

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

        $response = $this->withHeaders($this->apiHeaders())
                         ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'status',
                     'status_text',
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
                 ->assertJsonPath('data.project.name', $project->name);
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
            'status' => 'active',
            'start_date' => $project->start_date->format('Y-m-d'),
            'end_date' => $project->end_date->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->apiHeaders())
                         ->putJson("/api/projects/{$project->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                 ])
                 ->assertJsonPath('data.id', $project->id)
                 ->assertJsonPath('data.name', 'Updated Project Name');

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

        $response = $this->withHeaders($this->apiHeaders())
                         ->deleteJson("/api/projects/{$project->id}");

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

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id
        ]);
    }

    /**
     * Test validation errors
     */
    public function test_create_project_validation_errors()
    {
        $response = $this->withHeaders($this->apiHeaders())
                         ->postJson('/api/projects', []);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'E422.VALIDATION')
                 ->assertJsonPath('error.details.validation.name.0', 'Tên dự án là bắt buộc.');
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_to_projects()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->getJson('/api/projects');

        $response->assertStatus(401);
    }

    /**
     * Test project not found
     */
    public function test_project_not_found()
    {
        $response = $this->withHeaders($this->apiHeaders())
                         ->getJson('/api/projects/999999');

        $response->assertStatus(404)
                 ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }
}
