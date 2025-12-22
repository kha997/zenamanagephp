<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Carbon\Carbon;

/**
 * ProjectCostFlowStatusApiTest
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Tests for GET /api/v1/app/projects/{proj}/cost-flow-status endpoint
 * 
 * @group project-cost-flow-status
 * @group api-v1
 */
class ProjectCostFlowStatusApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;
    protected Contract $contractA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(232001);
        $this->setDomainName('project-cost-flow-status-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Grant projects.cost.view permission to users
        $costViewPermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.view'],
            [
                'module' => 'projects.cost',
                'action' => 'view',
                'description' => 'View project cost data'
            ]
        );
        
        // Get or create PM role and attach permission
        $pmRole = Role::firstOrCreate(['name' => 'pm']);
        if (!$pmRole->permissions()->where('permission_id', $costViewPermission->id)->exists()) {
            $pmRole->permissions()->attach($costViewPermission->id);
        }
        
        // Attach roles to users
        if (!$this->userA->roles()->where('role_id', $pmRole->id)->exists()) {
            $this->userA->roles()->attach($pmRole->id);
        }
        if (!$this->userB->roles()->where('role_id', $pmRole->id)->exists()) {
            $this->userB->roles()->attach($pmRole->id);
        }

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);

        // Create contract for project A
        $this->contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 1000000.0,
        ]);
    }

    /**
     * Test returns OK when no pending/rejected items
     */
    public function test_returns_ok_when_no_pending_rejected(): void
    {
        Sanctum::actingAs($this->userA);

        // Create approved CO and approved certificate
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
        ]);

        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'project_id',
                    'status',
                    'metrics' => [
                        'pending_change_orders',
                        'delayed_change_orders',
                        'rejected_change_orders',
                        'pending_certificates',
                        'delayed_certificates',
                        'rejected_certificates',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('OK', $data['status']);
        $this->assertEquals(0, $data['metrics']['pending_change_orders']);
        $this->assertEquals(0, $data['metrics']['rejected_change_orders']);
        $this->assertEquals(0, $data['metrics']['pending_certificates']);
        $this->assertEquals(0, $data['metrics']['rejected_certificates']);
    }

    /**
     * Test detects PENDING_APPROVAL when CO proposed
     */
    public function test_detects_pending_approval_when_co_proposed(): void
    {
        Sanctum::actingAs($this->userA);

        // Create proposed CO (recent, within 14 days)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
            'updated_at' => Carbon::now()->subDays(5), // 5 days ago
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('PENDING_APPROVAL', $data['status']);
        $this->assertEquals(1, $data['metrics']['pending_change_orders']);
        $this->assertEquals(0, $data['metrics']['delayed_change_orders']);
    }

    /**
     * Test detects DELAYED when CO proposed > 14 days
     */
    public function test_detects_delayed_when_co_proposed_over_14_days(): void
    {
        Sanctum::actingAs($this->userA);

        // Create proposed CO (old, > 14 days)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
            'updated_at' => Carbon::now()->subDays(20), // 20 days ago
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('DELAYED', $data['status']);
        $this->assertEquals(1, $data['metrics']['pending_change_orders']);
        $this->assertEquals(1, $data['metrics']['delayed_change_orders']);
    }

    /**
     * Test detects BLOCKED when CO rejected
     */
    public function test_detects_blocked_when_co_rejected(): void
    {
        Sanctum::actingAs($this->userA);

        // Create rejected CO
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'rejected',
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('BLOCKED', $data['status']);
        $this->assertEquals(1, $data['metrics']['rejected_change_orders']);
    }

    /**
     * Test detects delayed + pending certificate correctly
     */
    public function test_detects_delayed_and_pending_certificate(): void
    {
        Sanctum::actingAs($this->userA);

        // Create delayed certificate (> 14 days)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'submitted',
            'updated_at' => Carbon::now()->subDays(20), // 20 days ago
        ]);

        // Create recent certificate (< 14 days)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'submitted',
            'updated_at' => Carbon::now()->subDays(5), // 5 days ago
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('DELAYED', $data['status']); // Delayed takes priority
        $this->assertEquals(2, $data['metrics']['pending_certificates']);
        $this->assertEquals(1, $data['metrics']['delayed_certificates']);
    }

    /**
     * Test detects BLOCKED when certificate rejected
     */
    public function test_detects_blocked_when_certificate_rejected(): void
    {
        Sanctum::actingAs($this->userA);

        // Create rejected certificate
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'rejected',
        ]);

        // Even with pending CO, BLOCKED takes priority
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('BLOCKED', $data['status']); // BLOCKED takes priority
        $this->assertEquals(1, $data['metrics']['rejected_certificates']);
    }

    /**
     * Test handles empty projects gracefully
     */
    public function test_handles_empty_projects_gracefully(): void
    {
        Sanctum::actingAs($this->userA);

        // No COs or certificates

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('OK', $data['status']);
        $this->assertEquals(0, $data['metrics']['pending_change_orders']);
        $this->assertEquals(0, $data['metrics']['pending_certificates']);
    }

    /**
     * Test enforces permission (403)
     */
    public function test_enforces_permission_403(): void
    {
        // Create user without projects.cost.view permission
        $userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'member',
        ]);
        
        $userWithoutPermission->tenants()->attach($this->tenantA->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userWithoutPermission);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'COST_VIEW_PERMISSION_DENIED',
            ]);
    }

    /**
     * Test enforces tenant isolation (404)
     */
    public function test_enforces_tenant_isolation_404(): void
    {
        Sanctum::actingAs($this->userA);

        // Try to access project from tenant B
        $response = $this->getJson("/api/v1/app/projects/{$this->projectB->id}/cost-flow-status");

        $response->assertStatus(404);
    }

    /**
     * Test status priority: BLOCKED > DELAYED > PENDING_APPROVAL > OK
     */
    public function test_status_priority_hierarchy(): void
    {
        Sanctum::actingAs($this->userA);

        // Test 1: BLOCKED takes priority over DELAYED
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'rejected',
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");
        $data = $response->json('data');
        $this->assertEquals('BLOCKED', $data['status']);

        // Clean up
        ChangeOrder::where('project_id', $this->projectA->id)->delete();

        // Test 2: DELAYED takes priority over PENDING_APPROVAL
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'proposed',
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-flow-status");
        $data = $response->json('data');
        $this->assertEquals('DELAYED', $data['status']);
    }
}
