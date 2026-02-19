<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Src\InteractionLogs\Models\InteractionLog;
use Tests\Traits\AuthenticationTrait;

/**
 * Feature tests cho InteractionLog API endpoints
 * 
 * Kiểm tra CRUD operations, permissions, và business logic
 * cho module InteractionLogs
 */
class InteractionLogApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTrait;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected Task $task;
    protected array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createRbacAdminUser();
        $this->tenant = Tenant::findOrFail($this->user->tenant_id);

        $this->authHeaders = $this->authHeadersForUser(
            $this->user,
            $this->apiLoginToken($this->user, $this->tenant)
        );

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test tạo interaction log mới
     */
    public function test_create_interaction_log(): void
    {
        $data = [
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'linked_task_id' => $this->task->id,
            'type' => 'meeting',
            'description' => 'Weekly project meeting',
            'tag_path' => 'Material/Flooring/Granite',
            'visibility' => 'internal',
            'client_approved' => false
        ];

        $response = $this->withHeaders($this->authHeaders)->postJson('/api/v1/interaction-logs', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'project_id',
                        'linked_task_id',
                        'type',
                        'description',
                        'tag_path',
                        'visibility',
                        'client_approved',
                        'created_by',
                        'created_at',
                        'project',
                        'linked_task',
                        'creator'
                    ]
                ]);

        $this->assertDatabaseHas('interaction_logs', [
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
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
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders($this->authHeaders)->getJson('/api/v1/interaction-logs?project_id=' . $this->project->id);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'project_id',
                                'type',
                                'description',
                                'visibility',
                                'client_approved',
                                'created_at'
                            ]
                        ],
                        'meta',
                        'links'
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
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'visibility' => 'internal'
        ]);

        $updateData = [
            'description' => 'Updated meeting notes',
            'visibility' => 'client',
            'client_approved' => true
        ];

        $response = $this->withHeaders($this->authHeaders)->putJson('/api/v1/interaction-logs/' . $log->id, $updateData);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('interaction_logs', [
            'id' => $log->id,
            'description' => 'Updated meeting notes',
            'visibility' => 'client',
            'client_approved' => false
        ]);
    }

    /**
     * Test xóa interaction log
     */
    public function test_delete_interaction_log(): void
    {
        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders($this->authHeaders)->deleteJson('/api/v1/interaction-logs/' . $log->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('interaction_logs', [
            'id' => $log->id
        ]);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant);
        $otherHeaders = $this->authHeadersForUser(
            $otherUser,
            $this->apiLoginToken($otherUser, $otherTenant)
        );

        $log = InteractionLog::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders($otherHeaders)->getJson('/api/v1/interaction-logs/' . $log->id);

        $response->assertStatus(404);
    }
}
