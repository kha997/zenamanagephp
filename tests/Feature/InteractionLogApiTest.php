<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\InteractionLogs\Models\InteractionLog;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

/**
 * Feature tests cho InteractionLog API endpoints
 * 
 * Kiểm tra CRUD operations, permissions, và business logic
 * cho module InteractionLogs
 */
class InteractionLogApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected Task $task;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::factory()->create();
        
        // Tạo user và login
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        // Tạo project và task
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id
        ]);
        
        // Tạo roles và permissions
        $this->createRolesAndPermissions();
        
        // Login để lấy token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Test tạo interaction log mới
     */
    public function test_create_interaction_log(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'linked_task_id' => $this->task->id,
            'type' => 'meeting',
            'description' => 'Weekly project meeting',
            'tag_path' => 'Material/Flooring/Granite',
            'visibility' => 'internal',
            'client_approved' => false
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/v1/interaction-logs', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'interaction_log' => [
                            'id',
                            'project_id',
                            'linked_task_id',
                            'type',
                            'description',
                            'tag_path',
                            'visibility',
                            'client_approved',
                            'created_by',
                            'created_at'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('interaction_logs', [
            'project_id' => $this->project->id,
            'type' => 'meeting',
            'description' => 'Weekly project meeting',
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test lấy danh sách interaction logs
     */
    public function test_get_interaction_logs(): void
    {
        // Tạo test data
        InteractionLog::factory(5)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/v1/interaction-logs?project_id=' . $this->project->id);

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
                                'visibility',
                                'created_at'
                            ]
                        ],
                        'pagination'
                    ]
                ]);
    }

    /**
     * Test cập nhật interaction log
     */
    public function test_update_interaction_log(): void
    {
        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'visibility' => 'internal'
        ]);

        $updateData = [
            'description' => 'Updated meeting notes',
            'visibility' => 'client',
            'client_approved' => true
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/v1/interaction-logs/' . $log->id, $updateData);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('interaction_logs', [
            'id' => $log->id,
            'description' => 'Updated meeting notes',
            'visibility' => 'client',
            'client_approved' => true
        ]);
    }

    /**
     * Test xóa interaction log
     */
    public function test_delete_interaction_log(): void
    {
        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/v1/interaction-logs/' . $log->id);

        $response->assertStatus(204);
        
        $this->assertSoftDeleted('interaction_logs', [
            'id' => $log->id
        ]);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        // Tạo tenant khác
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'password' => Hash::make('password123')
        ]);
        
        // Login với user khác
        $otherLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $otherUser->email,
            'password' => 'password123'
        ]);
        $otherToken = $otherLoginResponse->json('data.token');
        
        // Tạo log với user đầu tiên
        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);
        
        // User khác không thể truy cập
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson('/api/v1/interaction-logs/' . $log->id);
        
        $response->assertStatus(404);
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions(): void
    {
        $permissions = [
            'interaction_log.create',
            'interaction_log.read',
            'interaction_log.update',
            'interaction_log.delete'
        ];

        foreach ($permissions as $permissionCode) {
            Permission::factory()->create([
                'code' => $permissionCode,
                'module' => 'interaction_logs',
                'action' => explode('.', $permissionCode)[1]
            ]);
        }

        $role = Role::factory()->create([
            'name' => 'Project Manager',
            'scope' => 'system'
        ]);

        // Gán permissions cho role
        $role->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );

        // Gán role cho user
        $this->user->systemRoles()->attach($role->id);
    }
}