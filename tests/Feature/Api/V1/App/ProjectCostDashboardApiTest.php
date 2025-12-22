<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectCostDashboardApiTest
 * 
 * Round 223: Project Cost Dashboard API (Variance + Timeline + Forecast)
 * 
 * Tests for project cost dashboard API endpoint with tenant isolation and aggregation logic
 * 
 * @group project-cost-dashboard
 * @group api-v1
 */
class ProjectCostDashboardApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(223001);
        $this->setDomainName('project-cost-dashboard-api');
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

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
    }

    public function test_it_returns_cost_dashboard_with_summary_and_variance(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000,
        ]);

        // Create contract
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000,
        ]);

        // Create approved change order (+100000)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'amount_delta' => 100000,
        ]);

        // Create pending change order (+50000)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'draft',
            'amount_delta' => 50000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'project_id',
                    'currency',
                    'summary' => [
                        'budget_total',
                        'contract_base_total',
                        'contract_current_total',
                        'total_certified_amount',
                        'total_paid_amount',
                        'outstanding_amount',
                    ],
                    'variance' => [
                        'pending_change_orders_total',
                        'rejected_change_orders_total',
                        'forecast_final_cost',
                        'variance_vs_budget',
                        'variance_vs_contract_current',
                    ],
                    'contracts' => [
                        'contract_base_total',
                        'change_orders_approved_total',
                        'change_orders_pending_total',
                        'change_orders_rejected_total',
                        'contract_current_total',
                    ],
                    'time_series' => [
                        'certificates_per_month',
                        'payments_per_month',
                    ],
                ],
            ]);

        $data = $response->json('data');

        // Assert summary totals
        $this->assertEquals(1000000.0, $data['summary']['budget_total']);
        $this->assertEquals(800000.0, $data['summary']['contract_base_total']);
        $this->assertEquals(900000.0, $data['summary']['contract_current_total']); // 800000 + 100000 (approved CO)

        // Assert variance
        $this->assertEquals(50000.0, $data['variance']['pending_change_orders_total']); // draft CO
        $this->assertEquals(950000.0, $data['variance']['forecast_final_cost']); // 900000 + 50000
        $this->assertEquals(-50000.0, $data['variance']['variance_vs_budget']); // 950000 - 1000000
        $this->assertEquals(50000.0, $data['variance']['variance_vs_contract_current']); // 950000 - 900000
    }

    public function test_it_returns_contract_breakdown_block(): void
    {
        Sanctum::actingAs($this->userA);

        // Create multiple contracts
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 500000,
        ]);

        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 700000,
        ]);

        // Create change orders with different statuses
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract1->id,
            'status' => 'approved',
            'amount_delta' => 100000,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract1->id,
            'status' => 'draft',
            'amount_delta' => 50000,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract2->id,
            'status' => 'approved',
            'amount_delta' => 200000,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract2->id,
            'status' => 'rejected',
            'amount_delta' => -30000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Assert contract breakdown
        $this->assertEquals(1200000.0, $data['contracts']['contract_base_total']); // 500000 + 700000
        $this->assertEquals(300000.0, $data['contracts']['change_orders_approved_total']); // 100000 + 200000
        $this->assertEquals(50000.0, $data['contracts']['change_orders_pending_total']); // draft CO
        $this->assertEquals(-30000.0, $data['contracts']['change_orders_rejected_total']); // rejected CO
        $this->assertEquals(1500000.0, $data['contracts']['contract_current_total']); // (500000 + 100000) + (700000 + 200000)
    }

    public function test_it_returns_time_series_for_certificates_and_payments(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 1000000,
        ]);

        // Create certificates in different months (within last 12 months)
        $now = Carbon::now();
        $month1 = $now->copy()->subMonths(2);
        $month2 = $now->copy()->subMonths(1);

        // Certificate 1: with period_end
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'period_end' => $month1->format('Y-m-d'),
            'amount_payable' => 200000,
        ]);

        // Certificate 2: without period_end, use created_at
        $cert2 = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'period_end' => null,
            'amount_payable' => 300000,
        ]);
        $cert2->created_at = $month2;
        $cert2->save();

        // Certificate 3: rejected (should not be included)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'rejected',
            'period_end' => $month1->format('Y-m-d'),
            'amount_payable' => 100000,
        ]);

        // Create payments in different months
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'paid_date' => $month1->format('Y-m-d'),
            'amount_paid' => 150000,
        ]);

        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'paid_date' => $month2->format('Y-m-d'),
            'amount_paid' => 250000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');
        $timeSeries = $data['time_series'];

        // Assert certificates per month
        $certificates = $timeSeries['certificates_per_month'];
        $this->assertGreaterThanOrEqual(2, count($certificates));

        // Find month1 certificate
        $month1Cert = collect($certificates)->first(function ($item) use ($month1) {
            return $item['year'] === (int) $month1->format('Y') 
                && $item['month'] === (int) $month1->format('n');
        });
        $this->assertNotNull($month1Cert);
        $this->assertEquals(200000.0, $month1Cert['amount_payable_approved']);

        // Find month2 certificate
        $month2Cert = collect($certificates)->first(function ($item) use ($month2) {
            return $item['year'] === (int) $month2->format('Y') 
                && $item['month'] === (int) $month2->format('n');
        });
        $this->assertNotNull($month2Cert);
        $this->assertEquals(300000.0, $month2Cert['amount_payable_approved']);

        // Assert payments per month
        $payments = $timeSeries['payments_per_month'];
        $this->assertGreaterThanOrEqual(2, count($payments));

        // Find month1 payment
        $month1Payment = collect($payments)->first(function ($item) use ($month1) {
            return $item['year'] === (int) $month1->format('Y') 
                && $item['month'] === (int) $month1->format('n');
        });
        $this->assertNotNull($month1Payment);
        $this->assertEquals(150000.0, $month1Payment['amount_paid']);

        // Find month2 payment
        $month2Payment = collect($payments)->first(function ($item) use ($month2) {
            return $item['year'] === (int) $month2->format('Y') 
                && $item['month'] === (int) $month2->format('n');
        });
        $this->assertNotNull($month2Payment);
        $this->assertEquals(250000.0, $month2Payment['amount_paid']);
    }

    public function test_it_handles_empty_project_gracefully(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        // All totals should be 0
        $this->assertEquals(0.0, $data['summary']['budget_total']);
        $this->assertEquals(0.0, $data['summary']['contract_base_total']);
        $this->assertEquals(0.0, $data['summary']['contract_current_total']);
        $this->assertEquals(0.0, $data['summary']['total_certified_amount']);
        $this->assertEquals(0.0, $data['summary']['total_paid_amount']);
        $this->assertEquals(0.0, $data['summary']['outstanding_amount']);

        // Variance should be 0
        $this->assertEquals(0.0, $data['variance']['pending_change_orders_total']);
        $this->assertEquals(0.0, $data['variance']['rejected_change_orders_total']);
        $this->assertEquals(0.0, $data['variance']['forecast_final_cost']);
        $this->assertEquals(0.0, $data['variance']['variance_vs_budget']);
        $this->assertEquals(0.0, $data['variance']['variance_vs_contract_current']);

        // Contracts breakdown should be 0
        $this->assertEquals(0.0, $data['contracts']['contract_base_total']);
        $this->assertEquals(0.0, $data['contracts']['change_orders_approved_total']);
        $this->assertEquals(0.0, $data['contracts']['change_orders_pending_total']);
        $this->assertEquals(0.0, $data['contracts']['change_orders_rejected_total']);
        $this->assertEquals(0.0, $data['contracts']['contract_current_total']);

        // Time series should be empty arrays
        $this->assertIsArray($data['time_series']['certificates_per_month']);
        $this->assertCount(0, $data['time_series']['certificates_per_month']);
        $this->assertIsArray($data['time_series']['payments_per_month']);
        $this->assertCount(0, $data['time_series']['payments_per_month']);
    }

    public function test_it_enforces_tenant_isolation_for_cost_dashboard(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines and contracts in tenant B's project
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'amount_budget' => 5000000,
        ]);

        Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'base_amount' => 3000000,
        ]);

        // Try to access tenant B's project cost dashboard from tenant A
        $response = $this->getJson("/api/v1/app/projects/{$this->projectB->id}/cost-dashboard");

        // Should return 404 because project doesn't belong to tenant A
        $response->assertStatus(404);
    }

    public function test_it_includes_proposed_status_in_pending_change_orders(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 1000000,
        ]);

        // Create draft change order
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'draft',
            'amount_delta' => 50000,
        ]);

        // Create proposed change order
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 30000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Pending should include both draft and proposed
        $this->assertEquals(80000.0, $data['variance']['pending_change_orders_total']); // 50000 + 30000
        $this->assertEquals(80000.0, $data['contracts']['change_orders_pending_total']); // 50000 + 30000
    }
}
