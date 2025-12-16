<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\CostApprovalPolicy;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;

/**
 * CostApprovalPolicyApiTest
 * 
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 * 
 * Tests for cost approval policy API and enforcement
 * 
 * @group cost-approval-policy
 * @group api-v1
 * @group admin
 */
class CostApprovalPolicyApiTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $regularUser;
    protected User $highPrivilegeUser;
    protected Project $project;
    protected Contract $contract;
    protected ChangeOrder $changeOrder;
    protected ContractPaymentCertificate $certificate;
    protected ContractActualPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key checks for SQLite
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(239001);
        $this->setDomainName('cost-approval-policy-api');
        $this->setupDomainIsolation();

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
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

        $this->highPrivilegeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
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
        
        $this->highPrivilegeUser->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        // Setup permissions
        $costViewPermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.view'],
            ['module' => 'projects.cost', 'action' => 'view']
        );
        $costEditPermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.edit'],
            ['module' => 'projects.cost', 'action' => 'edit']
        );
        $costApprovePermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.approve'],
            ['module' => 'projects.cost', 'action' => 'approve']
        );
        $costApproveUnlimitedPermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.approve_unlimited'],
            ['module' => 'projects.cost', 'action' => 'approve_unlimited']
        );
        $systemCostPoliciesManagePermission = Permission::firstOrCreate(
            ['code' => 'system.cost_policies.manage'],
            ['module' => 'system', 'action' => 'cost_policies.manage']
        );

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'scope' => 'system',
        ]);
        $adminRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
            $costApprovePermission->id,
            $costApproveUnlimitedPermission->id,
            $systemCostPoliciesManagePermission->id,
        ]);

        $pmRole = Role::factory()->create([
            'name' => 'pm',
            'scope' => 'system',
        ]);
        $pmRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
            $costApprovePermission->id,
        ]);

        $this->adminUser->roles()->attach([$adminRole->id]);
        $this->regularUser->roles()->attach([$pmRole->id]);
        $this->highPrivilegeUser->roles()->attach([$adminRole->id]);
        
        // Refresh users to load relationships
        $this->adminUser->refresh();
        $this->regularUser->refresh();
        $this->highPrivilegeUser->refresh();
        
        // Ensure relationships are loaded for permission checks
        $this->adminUser->load('roles.permissions');
        $this->regularUser->load('roles.permissions');
        $this->highPrivilegeUser->load('roles.permissions');

        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);

        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'code' => 'CT-001',
            'base_amount' => 10000000,
        ]);

        // Create change order in proposed status
        $this->changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 500000,
        ]);

        // Create payment certificate in submitted status
        $this->certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'IPC-01',
            'status' => 'submitted',
            'amount_payable' => 2000000,
        ]);

        // Create payment with status planned
        $this->payment = ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'planned',
            'amount_paid' => 1500000,
            'paid_date' => null,
        ]);
    }

    // ========== Policy API Tests ==========

    /**
     * Test admin can view cost policy
     */
    public function test_admin_can_view_cost_policy(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/admin/cost-approval-policy');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'tenant_id' => $this->tenant->id,
            ],
        ]);
    }

    /**
     * Test admin can update cost policy
     */
    public function test_admin_can_update_cost_policy(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson('/api/v1/admin/cost-approval-policy', [
            'co_dual_threshold_amount' => 100000000,
            'certificate_dual_threshold_amount' => 80000000,
            'payment_dual_threshold_amount' => 50000000,
            'over_budget_threshold_percent' => 10.0,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'tenant_id' => $this->tenant->id,
                'co_dual_threshold_amount' => 100000000,
                'certificate_dual_threshold_amount' => 80000000,
                'payment_dual_threshold_amount' => 50000000,
                'over_budget_threshold_percent' => 10.0,
            ],
        ]);

        // Verify policy was saved
        $policy = CostApprovalPolicy::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($policy);
        $this->assertEquals(100000000, (float) $policy->co_dual_threshold_amount);
    }

    /**
     * Test validation of policy fields
     */
    public function test_validation_of_policy_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson('/api/v1/admin/cost-approval-policy', [
            'co_dual_threshold_amount' => -100, // Invalid: negative
            'over_budget_threshold_percent' => 2000, // Invalid: exceeds max
        ]);

        $response->assertStatus(422);
        // Check that at least one validation error exists
        $this->assertTrue(
            $response->json('details.validation') !== null || 
            $response->json('details') !== null
        );
    }

    /**
     * Test requires permission to manage cost policy
     */
    public function test_requires_permission_to_manage_cost_policy(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/v1/admin/cost-approval-policy');

        $response->assertStatus(403);
    }

    // ========== Policy Enforcement Tests ==========

    /**
     * Test default behavior when policy not set
     */
    public function test_default_behavior_when_policy_not_set(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Approve CO - should work as before (no policy blocking)
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/approve"
        );

        $response->assertStatus(200);
        $this->changeOrder->refresh();
        $this->assertEquals('approved', $this->changeOrder->status);
    }

    /**
     * Test blocks CO approval when threshold exceeded for non-high-privilege user
     */
    public function test_blocks_co_approval_when_threshold_exceeded_for_non_high_privilege_user(): void
    {
        // Create policy with threshold
        CostApprovalPolicy::create([
            'tenant_id' => $this->tenant->id,
            'co_dual_threshold_amount' => 100000, // Lower than CO amount (500000)
        ]);

        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/approve"
        );

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'policy.threshold_exceeded',
        ]);

        // Verify CO was not approved
        $this->changeOrder->refresh();
        $this->assertEquals('proposed', $this->changeOrder->status);
    }

    /**
     * Test allows CO approval for high-privilege user even if threshold exceeded
     */
    public function test_allows_co_approval_for_high_privilege_user_even_if_threshold_exceeded(): void
    {
        // Create policy with threshold
        CostApprovalPolicy::create([
            'tenant_id' => $this->tenant->id,
            'co_dual_threshold_amount' => 100000, // Lower than CO amount (500000)
        ]);

        Sanctum::actingAs($this->highPrivilegeUser);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/approve"
        );

        $response->assertStatus(200);
        $this->changeOrder->refresh();
        $this->assertEquals('approved', $this->changeOrder->status);
    }

    /**
     * Test blocks certificate approval when threshold exceeded
     */
    public function test_blocks_certificate_approval_when_threshold_exceeded(): void
    {
        // Create policy with threshold
        CostApprovalPolicy::create([
            'tenant_id' => $this->tenant->id,
            'certificate_dual_threshold_amount' => 1000000, // Lower than certificate amount (2000000)
        ]);

        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/approve"
        );

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'policy.threshold_exceeded',
        ]);
    }

    /**
     * Test blocks payment approval when threshold exceeded
     */
    public function test_blocks_payment_approval_when_threshold_exceeded(): void
    {
        // Create policy with threshold
        CostApprovalPolicy::create([
            'tenant_id' => $this->tenant->id,
            'payment_dual_threshold_amount' => 1000000, // Lower than payment amount (1500000)
        ]);

        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$this->payment->id}/mark-paid"
        );

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'policy.threshold_exceeded',
        ]);
    }
}
