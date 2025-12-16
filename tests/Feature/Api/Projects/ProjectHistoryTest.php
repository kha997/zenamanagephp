<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project History API
 * 
 * Round 170: Project Documents & History Endpoints
 * 
 * Tests that project history endpoint returns activity log with proper tenant isolation and RBAC.
 * 
 * @group projects
 * @group history
 */
class ProjectHistoryTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(17002);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'userA@test.com',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email' => 'userB@test.com',
        ]);
        
        // Attach users to tenants with appropriate roles
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'pm']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'pm']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test happy path - same tenant, has permission
     */
    public function test_history_returns_project_activities(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Create activities for this project
        // Using DB::table() because ProjectActivity model uses ULIDs but migration uses integer ID
        $activity1Id = DB::table('project_activities')->insertGetId([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CREATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was created',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity2Id = DB::table('project_activities')->insertGetId([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_UPDATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was updated',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'action',
                    'action_label',
                    'entity_type',
                    'entity_id',
                    'message',
                    'description',
                    'metadata',
                    'user',
                    'created_at',
                    'time_ago',
                ]
            ],
            'message'
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        // Verify activities are ordered by created_at desc (newest first)
        $this->assertEquals((string) $activity2Id, $data[0]['id']);
        $this->assertEquals((string) $activity1Id, $data[1]['id']);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_history_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        // Create activity for tenant A project
        DB::table('project_activities')->insert([
            'project_id' => $projectA->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CREATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was created',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to access from tenant B
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson("/api/v1/app/projects/{$projectA->id}/history");

        // Should return 404 (not found) due to tenant isolation
        $response->assertStatus(404);
    }

    /**
     * Test empty history list
     */
    public function test_history_returns_empty_array_when_no_activities(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Empty Project',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    /**
     * Test project not found
     */
    public function test_history_returns_404_for_nonexistent_project(): void
    {
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/nonexistent-id/history");

        $response->assertStatus(404);
        // Laravel's route model binding returns a different error format
        // Just verify it's a 404
    }

    /**
     * Test history filtering by action
     */
    public function test_history_can_be_filtered_by_action(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Create activities with different actions
        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CREATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was created',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_UPDATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was updated',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history?action=created");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('created', $data[0]['action']);
    }

    /**
     * Test history filtering by entity_type
     */
    public function test_history_can_be_filtered_by_entity_type(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Create activities with different entity types
        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CREATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was created',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_TASK_UPDATED,
            'entity_type' => ProjectActivity::ENTITY_TASK,
            'description' => 'Task was updated',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history?entity_type=Task");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Task', $data[0]['entity_type']);
    }

    /**
     * Test history limit parameter
     */
    public function test_history_respects_limit_parameter(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Create 10 activities
        for ($i = 0; $i < 10; $i++) {
            DB::table('project_activities')->insert([
                'project_id' => $project->id,
                'user_id' => $this->userA->id,
                'action' => ProjectActivity::ACTION_UPDATED,
                'entity_type' => ProjectActivity::ENTITY_PROJECT,
                'description' => "Activity {$i}",
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history?limit=5");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(5, $data);
    }

    /**
     * Test history includes user information
     */
    public function test_history_includes_user_information(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CREATED,
            'entity_type' => ProjectActivity::ENTITY_PROJECT,
            'description' => 'Project was created',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['user']);
        $this->assertEquals((string) $this->userA->id, $data[0]['user']['id']);
        $this->assertEquals($this->userA->name, $data[0]['user']['name']);
        $this->assertEquals($this->userA->email, $data[0]['user']['email']);
    }

    /**
     * Test history filtering by entity_id
     * 
     * Round 231: Cost Workflow Timeline
     */
    public function test_history_can_be_filtered_by_entity_id(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $entityId1 = 'entity-001';
        $entityId2 = 'entity-002';

        // Create activities with different entity_ids
        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CHANGE_ORDER_PROPOSED,
            'entity_type' => ProjectActivity::ENTITY_CHANGE_ORDER,
            'entity_id' => $entityId1,
            'description' => 'Change order proposed',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CHANGE_ORDER_APPROVED,
            'entity_type' => ProjectActivity::ENTITY_CHANGE_ORDER,
            'entity_id' => $entityId1,
            'description' => 'Change order approved',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CHANGE_ORDER_PROPOSED,
            'entity_type' => ProjectActivity::ENTITY_CHANGE_ORDER,
            'entity_id' => $entityId2,
            'description' => 'Another change order proposed',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history?entity_id={$entityId1}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals($entityId1, $data[0]['entity_id']);
        $this->assertEquals($entityId1, $data[1]['entity_id']);
    }

    /**
     * Test history filtering by entity_type and entity_id together
     * 
     * Round 231: Cost Workflow Timeline
     */
    public function test_history_can_be_filtered_by_entity_type_and_entity_id(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $changeOrderId = 'co-001';
        $certificateId = 'cert-001';

        // Create activities for different cost entities
        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CHANGE_ORDER_PROPOSED,
            'entity_type' => ProjectActivity::ENTITY_CHANGE_ORDER,
            'entity_id' => $changeOrderId,
            'description' => 'Change order proposed',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_CERTIFICATE_SUBMITTED,
            'entity_type' => ProjectActivity::ENTITY_PAYMENT_CERTIFICATE,
            'entity_id' => $certificateId,
            'description' => 'Certificate submitted',
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/history?entity_type=" . ProjectActivity::ENTITY_CHANGE_ORDER . "&entity_id={$changeOrderId}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(ProjectActivity::ENTITY_CHANGE_ORDER, $data[0]['entity_type']);
        $this->assertEquals($changeOrderId, $data[0]['entity_id']);
    }
}

