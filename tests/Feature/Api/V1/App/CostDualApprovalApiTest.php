<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

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
 * CostDualApprovalApiTest
 * 
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 * 
 * Tests for dual approval workflow for Change Orders, Payment Certificates, and Payments
 * 
 * @group cost-dual-approval
 * @group api-v1
 */
class CostDualApprovalApiTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    protected Tenant $tenant;
    protected User $firstApprover;
    protected User $secondApprover;
    protected User $highPrivilegeUser;
    protected Project $project;
    protected Contract $contract;
    protected CostApprovalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key checks for SQLite
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(241001);
        $this->setDomainName('cost-dual-approval-api');
        $this->setupDomainIsolation();

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create users
        $this->firstApprover = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $this->secondApprover = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $this->highPrivilegeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Attach users to tenant
        $this->firstApprover->tenants()->attach($this->tenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->secondApprover->tenants()->attach($this->tenant->id, [
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

        $pmRole = Role::factory()->create([
            'name' => 'pm',
            'scope' => 'system',
        ]);
        $pmRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
            $costApprovePermission->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'scope' => 'system',
        ]);
        $adminRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
            $costApprovePermission->id,
            $costApproveUnlimitedPermission->id,
        ]);

        $this->firstApprover->roles()->attach([$pmRole->id]);
        $this->secondApprover->roles()->attach([$pmRole->id]);
        $this->highPrivilegeUser->roles()->attach([$adminRole->id]);
        
        // Refresh users to load relationships
        $this->firstApprover->refresh();
        $this->secondApprover->refresh();
        $this->highPrivilegeUser->refresh();
        
        // Ensure relationships are loaded for permission checks
        $this->firstApprover->load('roles.permissions');
        $this->secondApprover->load('roles.permissions');
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

        // Create cost approval policy with thresholds
        $this->policy = CostApprovalPolicy::create([
            'tenant_id' => $this->tenant->id,
            'co_dual_threshold_amount' => 1000000, // 1M threshold
            'certificate_dual_threshold_amount' => 2000000, // 2M threshold
            'payment_dual_threshold_amount' => 1500000, // 1.5M threshold
        ]);
    }

    /**
     * Test first-level approval is recorded for Change Order
     */
    public function test_first_level_approval_recorded_for_change_order(): void
    {
        Sanctum::actingAs($this->firstApprover);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 1500000, // Exceeds threshold
        ]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $changeOrder->id,
                    'status' => 'approved',
                ],
                'meta' => [
                    'dual_approval_stage' => 'first',
                ],
            ]);

        $changeOrder->refresh();
        $this->assertNotNull($changeOrder->first_approved_by);
        $this->assertEquals($this->firstApprover->id, $changeOrder->first_approved_by);
        $this->assertNotNull($changeOrder->first_approved_at);
        $this->assertNull($changeOrder->second_approved_by);
        $this->assertTrue($changeOrder->requires_dual_approval);
        $this->assertEquals('approved', $changeOrder->status);
    }

    /**
     * Test second-level approval requires different user
     */
    public function test_second_level_approval_requires_different_user(): void
    {
        Sanctum::actingAs($this->firstApprover);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 1500000,
            'first_approved_by' => $this->firstApprover->id,
            'first_approved_at' => now(),
            'requires_dual_approval' => true,
        ]);

        // First approver tries to do second approval - should fail
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUAL_APPROVAL_SAME_USER',
                ],
            ]);
    }

    /**
     * Test second-level approval finalizes Change Order
     */
    public function test_second_level_approval_finalizes_change_order(): void
    {
        // First approval
        Sanctum::actingAs($this->firstApprover);
        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 1500000,
        ]);

        $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        // Second approval
        Sanctum::actingAs($this->secondApprover);
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'dual_approval_stage' => 'second',
                ],
            ]);

        $changeOrder->refresh();
        $this->assertNotNull($changeOrder->second_approved_by);
        $this->assertEquals($this->secondApprover->id, $changeOrder->second_approved_by);
        $this->assertNotNull($changeOrder->second_approved_at);
        $this->assertEquals('approved', $changeOrder->status);
    }

    /**
     * Test high-privilege user bypasses dual approval
     */
    public function test_high_privilege_user_bypasses_dual_approval(): void
    {
        Sanctum::actingAs($this->highPrivilegeUser);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 1500000, // Exceeds threshold
        ]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $changeOrder->refresh();
        $this->assertNull($changeOrder->first_approved_by);
        $this->assertNull($changeOrder->second_approved_by);
        $this->assertFalse($changeOrder->requires_dual_approval);
        $this->assertEquals('approved', $changeOrder->status);
    }

    /**
     * Test threshold not exceeded behaves normally
     */
    public function test_threshold_not_exceeded_behaves_normally(): void
    {
        Sanctum::actingAs($this->firstApprover);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 500000, // Below threshold
        ]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        $response->assertStatus(200);

        $changeOrder->refresh();
        $this->assertNull($changeOrder->first_approved_by);
        $this->assertNull($changeOrder->second_approved_by);
        $this->assertFalse($changeOrder->requires_dual_approval);
        $this->assertEquals('approved', $changeOrder->status);
    }

    /**
     * Test dual approval for Payment Certificate
     */
    public function test_dual_approval_for_payment_certificate(): void
    {
        // First approval
        Sanctum::actingAs($this->firstApprover);
        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'IPC-01',
            'status' => 'submitted',
            'amount_payable' => 2500000, // Exceeds threshold
        ]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$certificate->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'dual_approval_stage' => 'first',
                ],
            ]);

        $certificate->refresh();
        $this->assertNotNull($certificate->first_approved_by);
        $this->assertNull($certificate->second_approved_by);

        // Second approval
        Sanctum::actingAs($this->secondApprover);
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$certificate->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'dual_approval_stage' => 'second',
                ],
            ]);

        $certificate->refresh();
        $this->assertNotNull($certificate->second_approved_by);
        $this->assertEquals('approved', $certificate->status);
    }

    /**
     * Test dual approval for Payment
     */
    public function test_dual_approval_for_payment(): void
    {
        // First approval
        Sanctum::actingAs($this->firstApprover);
        $payment = ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'status' => 'planned',
            'amount_paid' => 2000000, // Exceeds threshold
        ]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$payment->id}/mark-paid"
        );

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'dual_approval_stage' => 'first',
                ],
            ]);

        $payment->refresh();
        $this->assertNotNull($payment->first_approved_by);
        $this->assertNull($payment->second_approved_by);

        // Second approval
        Sanctum::actingAs($this->secondApprover);
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$payment->id}/mark-paid"
        );

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'dual_approval_stage' => 'second',
                ],
            ]);

        $payment->refresh();
        $this->assertNotNull($payment->second_approved_by);
        $this->assertEquals('paid', $payment->status);
    }

    /**
     * Test dual approval audit logs are created
     */
    public function test_dual_approval_audit_logs_created(): void
    {
        Sanctum::actingAs($this->firstApprover);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'proposed',
            'amount_delta' => 1500000,
        ]);

        $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        // Check audit log for first approval
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->firstApprover->id,
            'action' => 'co.first_approved',
            'entity_type' => 'ChangeOrder',
            'entity_id' => $changeOrder->id,
        ]);

        // Second approval
        Sanctum::actingAs($this->secondApprover);
        $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        // Check audit log for second approval
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->secondApprover->id,
            'action' => 'co.second_approved',
            'entity_type' => 'ChangeOrder',
            'entity_id' => $changeOrder->id,
        ]);
    }
}
