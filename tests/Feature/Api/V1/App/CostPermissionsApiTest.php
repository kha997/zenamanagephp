<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Contract;
use App\Models\ChangeOrder;
use App\Models\ContractPaymentCertificate;
use App\Models\ContractActualPayment;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Cost Permissions API Test
 * 
 * Round 229: Cost Vertical Permissions
 * 
 * Tests that cost-related endpoints properly enforce permissions:
 * - projects.cost.view: Required for viewing cost data
 * - projects.cost.edit: Required for creating/updating/deleting cost entities
 * - projects.cost.export: Required for PDF exports (or projects.cost.view)
 * 
 * @group cost
 * @group permissions
 * @group rbac
 */
class CostPermissionsApiTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Project $project;
    private Contract $contract;
    private ChangeOrder $changeOrder;
    private ContractPaymentCertificate $certificate;
    private ContractActualPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('cost-permissions');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
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
            'name' => 'Test Contract',
        ]);
        
        // Create change order
        $this->changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
        ]);
        
        // Create payment certificate
        $this->certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'PC-001',
        ]);
        
        // Create payment
        $this->payment = ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'certificate_id' => $this->certificate->id,
        ]);
    }

    /**
     * Create a user with specific permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Create role with permissions
        $role = Role::factory()->create([
            'name' => 'test_role_' . uniqid(),
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Test that user with projects.cost.view can view cost data
     */
    public function test_user_with_cost_view_can_view_cost_data(): void
    {
        $user = $this->createUserWithPermissions(['projects.cost.view']);
        Sanctum::actingAs($user);

        // Can view cost summary
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-summary");
        $response->assertStatus(200);

        // Can view cost dashboard
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-dashboard");
        $response->assertStatus(200);

        // Can view cost health
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-health");
        $response->assertStatus(200);

        // Can view cost alerts
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-alerts");
        $response->assertStatus(200);

        // Can view contracts
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts");
        $response->assertStatus(200);

        // Can view contract detail
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}");
        $response->assertStatus(200);

        // Can view change orders
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders");
        $response->assertStatus(200);

        // Can view payment certificates
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates");
        $response->assertStatus(200);

        // Can view payments
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments");
        $response->assertStatus(200);
    }

    /**
     * Test that user with projects.cost.view but without projects.cost.edit cannot mutate cost entities
     */
    public function test_user_with_cost_view_only_cannot_mutate_cost_entities(): void
    {
        $user = $this->createUserWithPermissions(['projects.cost.view']);
        Sanctum::actingAs($user);

        // Cannot create contract
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts", [
            'code' => 'CT-002',
            'name' => 'New Contract',
        ]);
        $response->assertStatus(403);

        // Cannot update contract
        $response = $this->patchJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}", [
            'name' => 'Updated Contract',
        ]);
        $response->assertStatus(403);

        // Cannot delete contract
        $response = $this->deleteJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}");
        $response->assertStatus(403);

        // Cannot create change order
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders", [
            'code' => 'CO-002',
            'title' => 'New CO',
        ]);
        $response->assertStatus(403);

        // Cannot create payment certificate
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates", [
            'code' => 'PC-002',
        ]);
        $response->assertStatus(403);

        // Cannot create payment
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments", [
            'paid_date' => now()->toDateString(),
            'amount_paid' => 1000,
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test that user with projects.cost.edit can mutate cost entities
     */
    public function test_user_with_cost_edit_can_mutate_cost_entities(): void
    {
        $user = $this->createUserWithPermissions(['projects.cost.view', 'projects.cost.edit']);
        Sanctum::actingAs($user);

        // Can create contract
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts", [
            'code' => 'CT-002',
            'name' => 'New Contract',
            'base_amount' => 100000,
            'currency' => 'VND',
            'status' => 'draft',
        ]);
        $response->assertStatus(201);

        // Can update contract
        $response = $this->patchJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}", [
            'name' => 'Updated Contract',
        ]);
        $response->assertStatus(200);

        // Can create change order
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders", [
            'code' => 'CO-002',
            'title' => 'New CO',
            'status' => 'draft',
            'amount_delta' => 5000,
        ]);
        $response->assertStatus(201);

        // Can create payment certificate
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates", [
            'code' => 'PC-002',
            'status' => 'draft',
            'amount_before_retention' => 10000,
            'retention_amount' => 1000,
            'amount_payable' => 9000,
        ]);
        $response->assertStatus(201);

        // Can create payment
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payments", [
            'paid_date' => now()->toDateString(),
            'amount_paid' => 1000,
        ]);
        $response->assertStatus(201);
    }

    /**
     * Test that user without any cost permissions cannot access cost endpoints
     */
    public function test_user_without_cost_permissions_cannot_access_cost_endpoints(): void
    {
        $user = $this->createUserWithPermissions(['projects.view']); // Only project view, no cost permissions
        Sanctum::actingAs($user);

        // Cannot view cost summary
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-summary");
        $response->assertStatus(403);

        // Cannot view cost dashboard
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-dashboard");
        $response->assertStatus(403);

        // Cannot view contracts
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts");
        $response->assertStatus(403);
    }

    /**
     * Test that user with projects.cost.view can export PDFs
     */
    public function test_user_with_cost_view_can_export_pdfs(): void
    {
        $user = $this->createUserWithPermissions(['projects.cost.view']);
        Sanctum::actingAs($user);

        // Can export contract PDF
        $response = $this->get("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/export/pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Can export change order PDF
        $response = $this->get("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/export/pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Can export payment certificate PDF
        $response = $this->get("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$this->certificate->id}/export/pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test that user without cost permissions cannot export PDFs
     */
    public function test_user_without_cost_permissions_cannot_export_pdfs(): void
    {
        $user = $this->createUserWithPermissions(['projects.view']);
        Sanctum::actingAs($user);

        // Cannot export contract PDF
        $response = $this->get("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/export/pdf");
        $response->assertStatus(403);

        // Cannot export change order PDF
        $response = $this->get("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$this->changeOrder->id}/export/pdf");
        $response->assertStatus(403);
    }

    /**
     * Test tenant isolation - user from different tenant cannot access cost data
     */
    public function test_tenant_isolation_for_cost_data(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);

        // Create user in other tenant with cost permissions
        $otherUser = $this->createUserWithPermissions(['projects.cost.view', 'projects.cost.edit']);
        $otherUser->tenant_id = $otherTenant->id;
        $otherUser->save();

        Sanctum::actingAs($otherUser);

        // Cannot access cost data from different tenant
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/cost-summary");
        $response->assertStatus(404); // Project not found due to tenant isolation

        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/contracts");
        $response->assertStatus(404);
    }
}
