<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Traits\GrantsCostPermissionsTrait;
use Laravel\Sanctum\Sanctum;

/**
 * ContractPaymentApiTest
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Tests for payment certificates and actual payments API endpoints with tenant isolation and CRUD operations
 * 
 * @group contract-payments
 * @group api-v1
 */
class ContractPaymentApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation, GrantsCostPermissionsTrait;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;
    protected Contract $contractA;
    protected Contract $contractB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(221001);
        $this->setDomainName('contract-payment-api');
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

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        $this->grantCostPermissions($this->userA);
        $this->grantCostPermissions($this->userB);

        $this->grantCostPermissions($this->userA, ['projects.cost.view', 'projects.cost.edit']);
        $this->grantCostPermissions($this->userB, ['projects.cost.view', 'projects.cost.edit']);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);

        // Create contracts
        $this->contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CT-A-001',
            'base_amount' => 10000000,
        ]);

        $this->contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'code' => 'CT-B-001',
            'base_amount' => 20000000,
        ]);
    }

    // ==================== Payment Certificates Tests ====================

    public function test_it_lists_payment_certificates_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        // Create payment certificates for contract A
        ContractPaymentCertificate::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
        ]);

        // Create certificate for contract B (should not appear)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payment-certificates");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'contract_id',
                        'code',
                        'title',
                        'status',
                        'amount_payable',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_creates_payment_certificate_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $payload = [
            'code' => 'IPC-01',
            'title' => 'Interim Payment Certificate #01',
            'status' => 'approved',
            'amount_before_retention' => 5000000,
            'retention_percent_override' => 5.0,
            'retention_amount' => 250000,
            'amount_payable' => 4750000,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payment-certificates",
            $payload
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'code',
                    'title',
                    'status',
                    'amount_payable',
                ]
            ]);

        $this->assertDatabaseHas('contract_payment_certificates', [
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'code' => 'IPC-01',
            'status' => 'approved',
        ]);
    }

    public function test_it_updates_payment_certificate_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'draft',
        ]);

        $payload = [
            'status' => 'approved',
            'title' => 'Updated Title',
        ];

        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payment-certificates/{$certificate->id}",
            $payload
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('contract_payment_certificates', [
            'id' => $certificate->id,
            'status' => 'approved',
            'title' => 'Updated Title',
        ]);
    }

    public function test_it_deletes_payment_certificate_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payment-certificates/{$certificate->id}"
        );

        $response->assertStatus(200);

        $this->assertSoftDeleted('contract_payment_certificates', [
            'id' => $certificate->id,
        ]);
    }

    // ==================== Actual Payments Tests ====================

    public function test_it_lists_payments_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        // Create payments for contract A
        ContractActualPayment::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
        ]);

        // Create payment for contract B (should not appear)
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'contract_id',
                        'paid_date',
                        'amount_paid',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_creates_payment_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $payload = [
            'paid_date' => '2024-01-15',
            'amount_paid' => 3000000,
            'currency' => 'VND',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'REF-001',
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments",
            $payload
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'paid_date',
                    'amount_paid',
                ]
            ]);

        $this->assertDatabaseHas('contract_payments', [
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'amount_paid' => 3000000,
        ]);
    }

    public function test_it_creates_payment_for_contract_and_links_certificate(): void
    {
        Sanctum::actingAs($this->userA);

        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
        ]);

        $payload = [
            'paid_date' => '2024-01-15',
            'amount_paid' => 3000000,
            'certificate_id' => $certificate->id,
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments",
            $payload
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('contract_payments', [
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $this->contractA->id,
            'certificate_id' => $certificate->id,
            'amount_paid' => 3000000,
        ]);
    }

    public function test_it_computes_contract_totals_from_certificates_and_payments(): void
    {
        Sanctum::actingAs($this->userA);

        // Create approved change order
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
            'amount_delta' => 2000000,
        ]);

        // Create approved certificates
        $cert1 = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
            'amount_before_retention' => 5000000,
            'retention_amount' => 0,
            'amount_payable' => 5000000,
        ]);

        $cert2 = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
            'amount_before_retention' => 3000000,
            'retention_amount' => 0,
            'amount_payable' => 3000000,
        ]);

        // Create draft certificate (should not count)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'draft',
            'amount_before_retention' => 1000000,
            'retention_amount' => 0,
            'amount_payable' => 1000000,
        ]);

        // Create payments
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'amount_paid' => 4000000,
        ]);

        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'amount_paid' => 2000000,
        ]);

        // Fetch contract
        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // current_amount = base_amount (10000000) + approved CO (2000000) = 12000000
        $this->assertEquals(12000000, $data['current_amount']);

        // total_certified_amount = sum of approved certificates (5000000 + 3000000) = 8000000
        $this->assertEquals(8000000, $data['total_certified_amount']);

        // total_paid_amount = sum of payments (4000000 + 2000000) = 6000000
        $this->assertEquals(6000000, $data['total_paid_amount']);

        // outstanding_amount = current_amount (12000000) - total_paid_amount (6000000) = 6000000
        $this->assertEquals(6000000, $data['outstanding_amount']);
    }

    public function test_it_enforces_tenant_isolation_for_certificates_and_payments(): void
    {
        Sanctum::actingAs($this->userA);

        // Create certificate and payment for tenant B
        $certificateB = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        $paymentB = ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        // Try to access tenant B's certificate from tenant A
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$this->contractB->id}/payment-certificates/{$certificateB->id}"
        );
        $response->assertStatus(404);

        // Try to access tenant B's payment from tenant A
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$this->contractB->id}/payments/{$paymentB->id}"
        );
        $response->assertStatus(404);

        // Try to create certificate for tenant B's contract
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$this->contractB->id}/payment-certificates",
            [
                'code' => 'IPC-01',
                'status' => 'draft',
                'amount_before_retention' => 1000000,
                'retention_amount' => 50000,
                'amount_payable' => 950000,
            ]
        );
        $response->assertStatus(404);
    }

    public function test_cross_tenant_access_returns_project_not_found_for_payments(): void
    {
        Sanctum::actingAs($this->userB);

        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments"
        );

        $response->assertStatus(404)
            ->assertJsonPath('code', 'PROJECT_NOT_FOUND')
            ->assertJsonPath('error.id', 'PROJECT_NOT_FOUND');
    }

    public function test_missing_payment_returns_not_found(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments/non-existent-id"
        );

        $response->assertStatus(404)
            ->assertJsonPath('code', 'PAYMENT_NOT_FOUND')
            ->assertJsonPath('error.id', 'PAYMENT_NOT_FOUND');
    }

    public function test_forbidden_when_missing_cost_permissions(): void
    {
        $restricted = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'viewer',
        ]);
        $restricted->tenants()->attach($this->tenantA->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        $restricted->roles()->detach();

        $payment = ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
        ]);

        Sanctum::actingAs($restricted);
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/payments/{$payment->id}"
        );

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('error.id', 'FORBIDDEN');
    }
}
