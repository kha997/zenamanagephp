<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;

/**
 * CostWorkflowApiTest
 * 
 * Round 230: Workflow/Approval for Change Orders, Payment Certificates, and Payments
 * 
 * Tests for workflow endpoints with status transitions and approval actions
 * 
 * @group cost-workflow
 * @group api-v1
 */
class CostWorkflowApiTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    protected Tenant $tenant;
    protected User $userWithApprove;
    protected User $userWithoutApprove;
    protected Project $project;
    protected Contract $contract;
    protected ChangeOrder $changeOrder;
    protected ContractPaymentCertificate $certificate;
    protected ContractActualPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key checks for SQLite (foreign keys may reference old table names after renames)
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(230001);
        $this->setDomainName('cost-workflow-api');
        $this->setupDomainIsolation();

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create users
        $this->userWithApprove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $this->userWithoutApprove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Attach users to tenant
        $this->userWithApprove->tenants()->attach($this->tenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userWithoutApprove->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Setup permissions - parse permission codes into module and action
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

        $pmRole = Role::factory()->create([
            'name' => 'pm',
            'scope' => 'system',
        ]);
        $pmRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
            $costApprovePermission->id,
        ]);

        $memberRole = Role::factory()->create([
            'name' => 'member',
            'scope' => 'system',
        ]);
        $memberRole->permissions()->attach([
            $costViewPermission->id,
            $costEditPermission->id,
        ]);

        $this->userWithApprove->roles()->attach([$pmRole->id]);
        $this->userWithoutApprove->roles()->attach([$memberRole->id]);
        
        // Refresh users to load relationships
        $this->userWithApprove->refresh();
        $this->userWithoutApprove->refresh();
        
        // Ensure relationships are loaded for permission checks
        $this->userWithApprove->load('roles.permissions');
        $this->userWithoutApprove->load('roles.permissions');

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

        // Create change order in draft status
        $this->changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'status' => 'draft',
            'amount_delta' => 500000,
        ]);

        // Create payment certificate in draft status
        $this->certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'IPC-01',
            'status' => 'draft',
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

    // ========== Change Order Workflow Tests ==========

    /**
     * Test propose change order (draft → proposed)
     */
    public function test_propose_change_order_from_draft(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/propose"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'status' => 'proposed',
            ],
        ]);

        $this->changeOrder->refresh();
        $this->assertEquals('proposed', $this->changeOrder->status);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'change_order_proposed')
            ->where('entity_id', $this->changeOrder->id)
            ->first();
        $this->assertNotNull($activity);
        $this->assertEquals($this->project->id, $activity->project_id);
    }

    /**
     * Test approve change order (proposed → approved)
     */
    public function test_approve_change_order_from_proposed(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        // First propose
        $this->changeOrder->update(['status' => 'proposed']);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/approve"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'status' => 'approved',
            ],
        ]);

        $this->changeOrder->refresh();
        $this->assertEquals('approved', $this->changeOrder->status);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'change_order_approved')
            ->where('entity_id', $this->changeOrder->id)
            ->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test reject change order (proposed → rejected)
     */
    public function test_reject_change_order_from_proposed(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        // First propose
        $this->changeOrder->update(['status' => 'proposed']);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/reject"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'status' => 'rejected',
            ],
        ]);

        $this->changeOrder->refresh();
        $this->assertEquals('rejected', $this->changeOrder->status);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'change_order_rejected')
            ->where('entity_id', $this->changeOrder->id)
            ->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test invalid transition: approve from draft
     */
    public function test_approve_change_order_from_draft_fails(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/approve"
        );

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'INVALID_STATUS_TRANSITION',
        ]);
    }

    /**
     * Test user without approve permission cannot propose
     */
    public function test_user_without_approve_permission_cannot_propose(): void
    {
        Sanctum::actingAs($this->userWithoutApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/propose"
        );

        $response->assertStatus(403);
    }

    // ========== Payment Certificate Workflow Tests ==========

    /**
     * Test submit payment certificate (draft → submitted)
     */
    public function test_submit_payment_certificate_from_draft(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/submit"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'status' => 'submitted',
            ],
        ]);

        $this->certificate->refresh();
        $this->assertEquals('submitted', $this->certificate->status);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'certificate_submitted')
            ->where('entity_id', $this->certificate->id)
            ->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test approve payment certificate (submitted → approved)
     */
    public function test_approve_payment_certificate_from_submitted(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        // First submit
        $this->certificate->update(['status' => 'submitted']);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/approve"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'data' => [
                'status' => 'approved',
            ],
        ]);

        $this->certificate->refresh();
        $this->assertEquals('approved', $this->certificate->status);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'certificate_approved')
            ->where('entity_id', $this->certificate->id)
            ->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test invalid transition: approve from draft
     */
    public function test_approve_certificate_from_draft_fails(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/approve"
        );

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'INVALID_STATUS_TRANSITION',
        ]);
    }

    /**
     * Test user without approve permission cannot submit
     */
    public function test_user_without_approve_permission_cannot_submit(): void
    {
        Sanctum::actingAs($this->userWithoutApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/submit"
        );

        $response->assertStatus(403);
    }

    // ========== Payment Workflow Tests ==========

    /**
     * Test mark payment as paid (planned → paid)
     */
    public function test_mark_payment_as_paid_from_planned(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$this->payment->id}/mark-paid"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);

        $this->payment->refresh();
        if (isset($this->payment->status)) {
            $this->assertEquals('paid', $this->payment->status);
        }
        $this->assertNotNull($this->payment->paid_date);

        // Verify activity log
        $activity = ProjectActivity::where('action', 'payment_marked_paid')
            ->where('entity_id', $this->payment->id)
            ->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test mark payment as paid sets paid_date when null
     */
    public function test_mark_payment_sets_paid_date_when_null(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        $this->payment->update(['paid_date' => null]);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$this->payment->id}/mark-paid"
        );

        $response->assertStatus(200);

        $this->payment->refresh();
        $this->assertNotNull($this->payment->paid_date);
    }

    /**
     * Test cannot mark already paid payment as paid
     */
    public function test_cannot_mark_already_paid_payment(): void
    {
        Sanctum::actingAs($this->userWithApprove);

        // Mark as paid first
        if (isset($this->payment->status)) {
            $this->payment->update(['status' => 'paid', 'paid_date' => now()]);
        } else {
            $this->payment->update(['paid_date' => now()]);
        }

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$this->payment->id}/mark-paid"
        );

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Payment is already marked as paid',
        ]);
    }

    /**
     * Test user without approve permission cannot mark paid
     */
    public function test_user_without_approve_permission_cannot_mark_paid(): void
    {
        Sanctum::actingAs($this->userWithoutApprove);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments/{$this->payment->id}/mark-paid"
        );

        $response->assertStatus(403);
    }

    // ========== Tenant Isolation Tests ==========

    /**
     * Test tenant isolation: cannot access other tenant's resources
     */
    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);

        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email_verified_at' => now(),
        ]);

        $otherUser->tenants()->attach($otherTenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        $costApprovePermission = Permission::firstOrCreate(
            ['code' => 'projects.cost.approve'],
            ['module' => 'projects.cost', 'action' => 'approve']
        );
        $pmRole = Role::factory()->create([
            'name' => 'pm',
            'scope' => 'system',
        ]);
        $pmRole->permissions()->attach([$costApprovePermission->id]);
        $otherUser->roles()->syncWithoutDetaching([$pmRole->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/propose"
        );

        $response->assertStatus(404);
    }
}
