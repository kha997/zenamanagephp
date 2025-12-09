<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;

/**
 * CostGovernanceOverviewApiTest
 * 
 * Round 243: Admin Cost Governance Dashboard / Overview
 * 
 * Tests for cost governance overview API
 * 
 * @group cost-governance-overview
 * @group api-v1
 * @group admin
 */
class CostGovernanceOverviewApiTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $adminUser;
    protected User $regularUser;
    protected Project $project;
    protected Project $otherProject;
    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key checks for SQLite
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(243001);
        $this->setDomainName('cost-governance-overview-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        $this->otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);

        // Create users
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'pm',
            'email_verified_at' => now(),
        ]);

        // Attach users to tenant
        $this->adminUser->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $this->regularUser->tenants()->attach($this->tenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Setup permissions
        $costGovernanceViewPermission = Permission::firstOrCreate(
            ['code' => 'system.cost_governance.view'],
            ['module' => 'system', 'action' => 'cost_governance.view']
        );

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'scope' => 'system',
        ]);
        $adminRole->permissions()->attach([$costGovernanceViewPermission->id]);

        $pmRole = Role::factory()->create([
            'name' => 'pm',
            'scope' => 'system',
        ]);

        $this->adminUser->roles()->attach([$adminRole->id]);
        $this->regularUser->roles()->attach([$pmRole->id]);
        
        // Refresh users to load relationships
        $this->adminUser->refresh();
        $this->regularUser->refresh();

        // Create projects
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);

        $this->otherProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Project',
        ]);

        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'base_amount' => 1000000,
        ]);
    }

    /**
     * Test admin can view cost governance overview
     */
    public function test_admin_can_view_cost_governance_overview(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create test data
        // Change Orders
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'proposed',
            'amount_delta' => 100000,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'approved',
            'requires_dual_approval' => true,
            'first_approved_by' => $this->adminUser->id,
            'second_approved_by' => null,
            'amount_delta' => 200000,
        ]);

        // Certificates
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'submitted',
            'amount_payable' => 50000,
        ]);

        // Payments
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'paid_date' => now(),
            'amount_paid' => 30000,
        ]);

        $response = $this->getJson('/api/v1/admin/cost-governance-overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'summary' => [
                        'change_orders' => ['total', 'pending_approval', 'awaiting_dual_approval', 'blocked_by_policy'],
                        'certificates' => ['total', 'pending_approval', 'awaiting_dual_approval', 'blocked_by_policy'],
                        'payments' => ['total', 'pending_approval', 'awaiting_dual_approval', 'blocked_by_policy'],
                    ],
                    'top_projects_by_risk' => [
                        '*' => [
                            'project_id',
                            'project_name',
                            'pending_co',
                            'pending_certificates',
                            'pending_payments',
                            'awaiting_dual_approval',
                            'policy_blocked_items',
                            'over_budget_percent',
                        ],
                    ],
                    'recent_policy_events' => [
                        '*' => [
                            'type',
                            'entity_id',
                            'project_id',
                            'project_name',
                            'code',
                            'amount',
                            'threshold',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['summary']['change_orders']['total']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['change_orders']['pending_approval']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['change_orders']['awaiting_dual_approval']);
    }

    /**
     * Test respects tenant isolation
     */
    public function test_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create data for current tenant
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'proposed',
        ]);

        // Create data for other tenant
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Tenant Project',
        ]);
        $otherContract = Contract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $otherProject->id,
        ]);
        ChangeOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $otherProject->id,
            'contract_id' => $otherContract->id,
            'status' => 'proposed',
        ]);

        $response = $this->getJson('/api/v1/admin/cost-governance-overview');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only see data from current tenant
        $this->assertEquals(1, $data['summary']['change_orders']['total']);
        $this->assertEquals(1, $data['summary']['change_orders']['pending_approval']);
    }

    /**
     * Test requires permission
     */
    public function test_requires_permission(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/v1/admin/cost-governance-overview');

        $response->assertStatus(403);
        $responseData = $response->json();
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('error', $responseData);
    }

    /**
     * Test top projects by risk sorted correctly
     */
    public function test_top_projects_by_risk_sorted_correctly(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create project with high risk (policy blocked)
        $highRiskProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Risk Project',
        ]);
        $highRiskContract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $highRiskProject->id,
        ]);
        $highRiskCo = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $highRiskProject->id,
            'contract_id' => $highRiskContract->id,
            'status' => 'proposed',
        ]);

        // Create audit log for policy block
        DB::table('audit_logs')->insert([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'project_id' => $highRiskProject->id,
            'entity_type' => 'ChangeOrder',
            'entity_id' => $highRiskCo->id,
            'action' => 'co.policy_blocked',
            'payload_before' => null,
            'payload_after' => json_encode(['code' => 'policy.threshold_exceeded']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create project with medium risk (dual approval)
        $mediumRiskProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Medium Risk Project',
        ]);
        $mediumRiskContract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $mediumRiskProject->id,
        ]);
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $mediumRiskProject->id,
            'contract_id' => $mediumRiskContract->id,
            'status' => 'approved',
            'requires_dual_approval' => true,
            'first_approved_by' => $this->adminUser->id,
            'second_approved_by' => null,
        ]);

        $response = $this->getJson('/api/v1/admin/cost-governance-overview');

        $response->assertStatus(200);
        $data = $response->json('data');
        $topProjects = $data['top_projects_by_risk'];

        // High risk project should be first (has policy blocked items)
        $this->assertGreaterThanOrEqual(1, count($topProjects));
        $firstProject = $topProjects[0];
        $this->assertGreaterThanOrEqual(1, $firstProject['policy_blocked_items']);
    }

    /**
     * Test recent policy events listed
     */
    public function test_recent_policy_events_listed(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create audit log with policy event
        $co = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'proposed',
        ]);

        DB::table('audit_logs')->insert([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'entity_type' => 'ChangeOrder',
            'entity_id' => $co->id,
            'action' => 'co.policy_blocked',
            'payload_before' => null,
            'payload_after' => json_encode([
                'code' => 'policy.threshold_exceeded',
                'amount' => 150000000,
                'threshold' => 100000000,
            ]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/cost-governance-overview');

        $response->assertStatus(200);
        $data = $response->json('data');
        $events = $data['recent_policy_events'];

        $this->assertGreaterThanOrEqual(1, count($events));
        $event = $events[0];
        $this->assertEquals('co', $event['type']);
        $this->assertEquals('policy.threshold_exceeded', $event['code']);
        $this->assertEquals(150000000, $event['amount']);
        $this->assertEquals(100000000, $event['threshold']);
    }
}
