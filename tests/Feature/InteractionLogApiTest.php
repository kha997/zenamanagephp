<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\InteractionLogs\Models\InteractionLog;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

class InteractionLogApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $project;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->createRolesAndPermissions();
        
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
        $permissions = [
            'interaction_log.create',
            'interaction_log.read',
            'interaction_log.update',
            'interaction_log.delete',
            'interaction_log.approve_client',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'interaction_log',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test create interaction log
     */
    public function test_can_create_interaction_log()
    {
        $logData = [
            'project_id' => $this->project->id,
            'type' => 'meeting',
            'description' => 'Client meeting about project progress',
            'tag_path' => 'Material/Flooring/Granite',
            'visibility' => 'internal'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/interaction-logs', $logData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'interaction_log' => [
                             'id',
                             'project_id',
                             'type',
                             'description',
                             'tag_path',
                             'visibility',
                             'client_approved',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('interaction_logs', [
            'project_id' => $this->project->id,
            'type' => 'meeting',
            'visibility' => 'internal'
        ]);
    }

    /**
     * Test get interaction logs by project
     */
    public function test_can_get_interaction_logs_by_project()
    {
        InteractionLog::factory()->count(3)->create([
            'project_id' => $this->project->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/projects/{$this->project->id}/interaction-logs");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'interaction_logs' => [
                             '*' => [
                                 'id',
                                 'project_id',
                                 'type',
                                 'description',
                                 'tag_path',
                                 'visibility',
                                 'created_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test approve interaction log for client
     */
    public function test_can_approve_interaction_log_for_client()
    {
        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'visibility' => 'client',
            'client_approved' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/interaction-logs/{$log->id}/approve-for-client");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'interaction_log' => [
                             'id' => $log->id,
                             'client_approved' => true
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('interaction_logs', [
            'id' => $log->id,
            'client_approved' => true
        ]);
    }

    /**
     * Test get logs by tag path
     */
    public function test_can_get_logs_by_tag_path()
    {
        InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'tag_path' => 'Material/Flooring/Granite'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/interaction-logs/by-tag-path?tag_path=Material/Flooring/Granite');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'interaction_logs' => [
                             '*' => [
                                 'id',
                                 'tag_path',
                                 'description'
                             ]
                         ]
                     ]
                 ]);
    }

    /**
     * Test project statistics
     */
    public function test_can_get_project_statistics()
    {
        InteractionLog::factory()->count(5)->create([
            'project_id' => $this->project->id,
            'type' => 'meeting'
        ]);

        InteractionLog::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'type' => 'call'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/projects/{$this->project->id}/interaction-logs/statistics");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'statistics' => [
                             'total_logs',
                             'by_type',
                             'by_visibility',
                             'client_approved_count'
                         ]
                     ]
                 ]);
    }

    /**
     * Test validation errors
     */
    public function test_create_interaction_log_validation_errors()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/interaction-logs', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['project_id', 'type', 'description']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_to_interaction_logs()
    {
        $response = $this->getJson('/api/v1/interaction-logs');

        $response->assertStatus(401);
    }
}