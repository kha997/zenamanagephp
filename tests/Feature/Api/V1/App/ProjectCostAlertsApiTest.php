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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * ProjectCostAlertsApiTest
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 * 
 * Tests for GET /api/v1/app/projects/{proj}/cost-alerts endpoint
 * 
 * @group project-cost-alerts
 * @group api-v1
 */
class ProjectCostAlertsApiTest extends TestCase
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
        $this->setDomainSeed(227001);
        $this->setDomainName('project-cost-alerts-api');
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

    /**
     * Test computes pending_change_orders_overdue
     */
    public function test_computes_pending_change_orders_overdue(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 100000.0,
        ]);

        // Create overdue pending CO (created 20 days ago)
        $overdueCO = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'draft',
            'amount_delta' => 10000.0,
            'created_at' => Carbon::now()->subDays(20),
        ]);

        // Create recent pending CO (created 5 days ago - not overdue)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 5000.0,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'project_id',
                    'alerts',
                    'details' => [
                        'pending_co_count',
                        'overdue_co_count',
                        'unpaid_certificates_count',
                        'cost_health_status',
                        'pending_change_orders_total',
                        'budget_total',
                        'threshold_days',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertContains('pending_change_orders_overdue', $data['alerts']);
        $this->assertEquals(2, $data['details']['pending_co_count']);
        $this->assertEquals(1, $data['details']['overdue_co_count']);
    }

    /**
     * Test computes approved_certificates_unpaid
     */
    public function test_computes_approved_certificates_unpaid(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 100000.0,
        ]);

        // Create approved certificate (approved 20 days ago)
        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'amount_payable' => 50000.0,
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        // Create payment that only partially covers the certificate
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'certificate_id' => $certificate->id,
            'amount_paid' => 20000.0, // Less than 50,000
            'paid_date' => Carbon::now()->subDays(10),
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertContains('approved_certificates_unpaid', $data['alerts']);
        $this->assertEquals(1, $data['details']['unpaid_certificates_count']);
    }

    /**
     * Test does not alert for recent approved certificates
     */
    public function test_does_not_alert_for_recent_approved_certificates(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 100000.0,
        ]);

        // Create approved certificate (approved 5 days ago - not old enough)
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'amount_payable' => 50000.0,
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotContains('approved_certificates_unpaid', $data['alerts']);
    }

    /**
     * Test detects cost_health_warning from R226 status
     */
    public function test_detects_cost_health_warning(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 950000.0,
        ]);

        // Create pending change order that makes it AT_RISK
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 30000.0,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertContains('cost_health_warning', $data['alerts']);
        $this->assertEquals('AT_RISK', $data['details']['cost_health_status']);
    }

    /**
     * Test detects OVER_BUDGET cost health warning
     */
    public function test_detects_over_budget_cost_health_warning(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 950000.0,
        ]);

        // Create pending change order that makes it OVER_BUDGET
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 100000.0,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertContains('cost_health_warning', $data['alerts']);
        $this->assertEquals('OVER_BUDGET', $data['details']['cost_health_status']);
    }

    /**
     * Test computes pending_co_high_impact
     */
    public function test_computes_pending_co_high_impact(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000.0,
        ]);

        // Create pending CO with total > 10% of budget (120,000 > 100,000)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 120000.0,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertContains('pending_co_high_impact', $data['alerts']);
        $this->assertEquals('120000.00', $data['details']['pending_change_orders_total']);
        $this->assertEquals('1000000.00', $data['details']['budget_total']);
    }

    /**
     * Test does not alert for low impact pending CO
     */
    public function test_does_not_alert_for_low_impact_pending_co(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000.0,
        ]);

        // Create pending CO with total < 10% of budget (50,000 < 100,000)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 50000.0,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotContains('pending_co_high_impact', $data['alerts']);
    }

    /**
     * Test handles project with no alerts
     */
    public function test_handles_project_with_no_alerts(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        // Create contract with base amount that keeps it UNDER_BUDGET
        Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000.0,
        ]);

        // No pending COs, no certificates, health is UNDER_BUDGET (not AT_RISK or OVER_BUDGET)

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data['alerts']);
        $this->assertEmpty($data['alerts']);
        $this->assertEquals('UNDER_BUDGET', $data['details']['cost_health_status']);
    }

    /**
     * Test enforces tenant isolation
     */
    public function test_enforces_tenant_isolation(): void
    {
        Sanctum::actingAs($this->userA);

        // Try to access project from tenant B
        $response = $this->getJson("/api/v1/app/projects/{$this->projectB->id}/cost-alerts");

        $response->assertStatus(404);
    }

    /**
     * Test alerts calculation matches dashboard/summary/health services
     */
    public function test_alerts_calculation_matches_services(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 900000.0,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 50000.0,
        ]);

        // Get cost alerts
        $alertsResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-alerts");
        $alertsData = $alertsResponse->json('data');

        // Get cost dashboard
        $dashboardResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");
        $dashboardData = $dashboardResponse->json('data');

        // Get cost summary
        $summaryResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-summary");
        $summaryData = $summaryResponse->json('data');

        // Get cost health
        $healthResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");
        $healthData = $healthResponse->json('data');

        // Verify math matches
        $this->assertEquals(
            $summaryData['totals']['budget_total'],
            (float) $alertsData['details']['budget_total']
        );
        $this->assertEquals(
            $dashboardData['variance']['pending_change_orders_total'],
            (float) $alertsData['details']['pending_change_orders_total']
        );
        $this->assertEquals(
            $healthData['cost_health_status'],
            $alertsData['details']['cost_health_status']
        );
    }
}
