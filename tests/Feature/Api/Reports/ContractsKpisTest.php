<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Contracts & Payments KPIs in Reports
 * 
 * Round 38: Contracts & Payments KPIs Integration
 * 
 * Tests that contracts KPIs are properly included in ReportsController::getKpis()
 * response and that values are correctly calculated with tenant isolation.
 * 
 * @group reports
 * @group contracts
 * @group kpis
 */
class ContractsKpisTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('contracts-kpis');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create user with admin role (has tenant.view_reports)
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($this->user);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that contracts KPIs structure is present for tenant with contracts
     * 
     * Round 38: Contracts KPIs Integration
     */
    public function test_contracts_kpis_structure_present_for_tenant_with_contracts(): void
    {
        // Create contracts with different statuses
        $activeContract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 100000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        $completedContract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_COMPLETED,
            'total_value' => 50000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        $cancelledContract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_CANCELLED,
            'total_value' => 20000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        // Create payments for active contract
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $activeContract->id,
            'amount' => 30000.00,
            'status' => 'paid',
            'due_date' => Carbon::now()->subMonth(),
            'paid_at' => Carbon::now()->subMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $activeContract->id,
            'amount' => 20000.00,
            'status' => 'planned',
            'due_date' => Carbon::now()->addMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        // Create overdue payment
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $activeContract->id,
            'amount' => 10000.00,
            'status' => 'due',
            'due_date' => Carbon::now()->subDays(5), // Overdue
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        // Call KPIs endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');
        
        // Assert response structure
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_reports',
                'recent_reports',
                'by_type',
                'downloads',
                'trends',
                'period',
                'contracts' => [
                    'total_count',
                    'active_count',
                    'completed_count',
                    'cancelled_count',
                    'total_value',
                    'payments' => [
                        'scheduled_total',
                        'paid_total',
                        'overdue_total',
                        'overdue_count',
                        'remaining_to_schedule',
                        'remaining_to_pay',
                    ],
                ],
            ],
        ]);
        
        // Assert contracts KPIs values
        $contracts = $response->json('data.contracts');
        $this->assertEquals(3, $contracts['total_count'], 'Should have 3 contracts');
        $this->assertEquals(1, $contracts['active_count'], 'Should have 1 active contract');
        $this->assertEquals(1, $contracts['completed_count'], 'Should have 1 completed contract');
        $this->assertEquals(1, $contracts['cancelled_count'], 'Should have 1 cancelled contract');
        $this->assertEquals(170000.00, (float) $contracts['total_value'], 'Total value should be 170000.00', 0.01);
        
        // Assert payments KPIs
        $payments = $contracts['payments'];
        $this->assertEquals(60000.00, (float) $payments['scheduled_total'], 'Scheduled total should be 60000.00', 0.01);
        $this->assertEquals(30000.00, (float) $payments['paid_total'], 'Paid total should be 30000.00', 0.01);
        $this->assertEquals(10000.00, (float) $payments['overdue_total'], 'Overdue total should be 10000.00', 0.01);
        $this->assertEquals(1, $payments['overdue_count'], 'Should have 1 overdue payment');
        $this->assertEquals(110000.00, (float) $payments['remaining_to_schedule'], 'Remaining to schedule should be 110000.00', 0.01);
        $this->assertEquals(30000.00, (float) $payments['remaining_to_pay'], 'Remaining to pay should be 30000.00', 0.01);
    }

    /**
     * Test that contracts KPIs return zero for tenant without contracts
     * 
     * Round 38: Contracts KPIs Integration
     */
    public function test_contracts_kpis_zero_for_tenant_without_contracts(): void
    {
        // Call KPIs endpoint without creating any contracts
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');
        
        // Assert response structure
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'contracts' => [
                    'total_count',
                    'active_count',
                    'completed_count',
                    'cancelled_count',
                    'total_value',
                    'payments',
                ],
            ],
        ]);
        
        // Assert contracts KPIs are zero
        $contracts = $response->json('data.contracts');
        $this->assertEquals(0, $contracts['total_count'], 'Total count should be 0');
        $this->assertEquals(0, $contracts['active_count'], 'Active count should be 0');
        $this->assertEquals(0, $contracts['completed_count'], 'Completed count should be 0');
        $this->assertEquals(0, $contracts['cancelled_count'], 'Cancelled count should be 0');
        $this->assertEquals(0.0, (float) $contracts['total_value'], 'Total value should be 0.0');
        
        // Assert payments KPIs are zero
        $payments = $contracts['payments'];
        $this->assertEquals(0.0, (float) $payments['scheduled_total'], 'Scheduled total should be 0.0');
        $this->assertEquals(0.0, (float) $payments['paid_total'], 'Paid total should be 0.0');
        $this->assertEquals(0.0, (float) $payments['overdue_total'], 'Overdue total should be 0.0');
        $this->assertEquals(0, $payments['overdue_count'], 'Overdue count should be 0');
        $this->assertNull($payments['remaining_to_schedule'], 'Remaining to schedule should be null when no contracts');
        $this->assertEquals(0.0, (float) $payments['remaining_to_pay'], 'Remaining to pay should be 0.0');
    }

    /**
     * Test that contracts KPIs are tenant-isolated
     * 
     * Round 38: Contracts KPIs Integration - Multi-tenant isolation
     */
    public function test_contracts_kpis_are_tenant_isolated(): void
    {
        // Create contracts for this tenant
        Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 100000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
        
        // Create another tenant with contracts
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 50000.00,
        ]);
        
        Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => Contract::STATUS_COMPLETED,
            'total_value' => 30000.00,
        ]);
        
        // Call KPIs endpoint for this tenant
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');
        
        // Assert only this tenant's contracts are counted
        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');
        $this->assertEquals(1, $contracts['total_count'], 'Should only count contracts from active tenant');
        $this->assertEquals(1, $contracts['active_count'], 'Should only count active contracts from active tenant');
        $this->assertEquals(0, $contracts['completed_count'], 'Should not count other tenant\'s completed contracts');
        $this->assertEquals(100000.00, (float) $contracts['total_value'], 'Should only sum total_value from active tenant', 0.01);
    }

    /**
     * Test that RBAC (view_reports permission) still works correctly
     * 
     * Round 38: Contracts KPIs Integration - RBAC verification
     */
    public function test_rbac_view_reports_permission_still_works(): void
    {
        // Create user without tenant.view_reports (guest role)
        $guestUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $guestUser->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_reports
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($guestUser);
        $guestToken = $guestUser->createToken('test-token')->plainTextToken;
        
        // Guest should NOT be able to GET reports/kpis
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$guestToken}",
        ])->getJson('/api/v1/app/reports/kpis');
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that contracts KPIs include budget and actual blocks
     * 
     * Round 45: Contract Cost Control - Budget vs Actual KPIs
     */
    public function test_contracts_kpis_include_budget_and_actual_blocks(): void
    {
        // Contract 1: total_value = 1_000_000
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 1_000_000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Budget lines: 600_000 active, 100_000 cancelled
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'total_amount' => 600_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'total_amount' => 100_000.00,
            'status' => 'cancelled',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Expenses: 400_000 active, 100_000 cancelled
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'amount' => 100_000.00,
            'status' => 'cancelled',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Contract 2: total_value = 500_000
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 500_000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Budget lines: 700_000 active (over budget)
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract2->id,
            'total_amount' => 700_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Expenses: 600_000 active (overrun)
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract2->id,
            'amount' => 600_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call KPIs endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');

        // Assert budget block
        $this->assertArrayHasKey('budget', $contracts, 'Budget block should exist');
        $budget = $contracts['budget'];
        $this->assertEquals(1_300_000.00, (float) $budget['budget_total'], 'Budget total should be 600000 + 700000 = 1300000', 0.01);
        $this->assertEquals(2, $budget['active_line_count'], 'Active line count should be 2');
        $this->assertEquals(1, $budget['over_budget_contracts_count'], 'Over budget contracts count should be 1 (contract2)');

        // Assert actual block
        $this->assertArrayHasKey('actual', $contracts, 'Actual block should exist');
        $actual = $contracts['actual'];
        $this->assertEquals(1_000_000.00, (float) $actual['actual_total'], 'Actual total should be 400000 + 600000 = 1000000', 0.01);
        $this->assertEquals(2, $actual['line_count'], 'Line count should be 2');
        $this->assertEquals(1, $actual['overrun_contracts_count'], 'Overrun contracts count should be 1 (contract2)');
        
        // contract_vs_actual_diff_total = (1000000 - 400000) + (500000 - 600000) = 600000 - 100000 = 500000
        $this->assertEquals(500_000.00, (float) $actual['contract_vs_actual_diff_total'], 'Contract vs actual diff total should be 500000', 0.01);
    }

    /**
     * Test that budget and actual KPIs are tenant-isolated
     * 
     * Round 45: Contract Cost Control - Multi-tenant isolation
     */
    public function test_contracts_kpis_budget_and_actual_are_tenant_isolated(): void
    {
        // Create contract for this tenant with budget and expenses
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 1_000_000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'total_amount' => 500_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 300_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create another tenant with budget and expenses
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);

        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 2_000_000.00,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contract_id' => $otherContract->id,
            'total_amount' => 1_500_000.00,
            'status' => 'approved',
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contract_id' => $otherContract->id,
            'amount' => 1_200_000.00,
            'status' => 'approved',
        ]);

        // Call KPIs endpoint for this tenant
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');

        // Assert only this tenant's budget and actual are counted
        $budget = $contracts['budget'];
        $this->assertEquals(500_000.00, (float) $budget['budget_total'], 'Should only count budget from active tenant', 0.01);
        $this->assertEquals(1, $budget['active_line_count'], 'Should only count budget lines from active tenant');

        $actual = $contracts['actual'];
        $this->assertEquals(300_000.00, (float) $actual['actual_total'], 'Should only count expenses from active tenant', 0.01);
        $this->assertEquals(1, $actual['line_count'], 'Should only count expenses from active tenant');
    }

    /**
     * Test that existing keys are preserved when budget and actual are added
     * 
     * Round 45: Contract Cost Control - Backwards compatibility
     */
    public function test_contracts_kpis_preserve_existing_keys(): void
    {
        // Create a contract with payments
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 100000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 50000.00,
            'status' => 'paid',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call KPIs endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');

        // Assert all existing keys are present
        $this->assertArrayHasKey('total_count', $contracts);
        $this->assertArrayHasKey('active_count', $contracts);
        $this->assertArrayHasKey('completed_count', $contracts);
        $this->assertArrayHasKey('cancelled_count', $contracts);
        $this->assertArrayHasKey('total_value', $contracts);
        $this->assertArrayHasKey('payments', $contracts);
        $this->assertArrayHasKey('budget', $contracts);
        $this->assertArrayHasKey('actual', $contracts);

        // Assert payments block structure is preserved
        $payments = $contracts['payments'];
        $this->assertArrayHasKey('scheduled_total', $payments);
        $this->assertArrayHasKey('paid_total', $payments);
        $this->assertArrayHasKey('overdue_total', $payments);
        $this->assertArrayHasKey('overdue_count', $payments);
        $this->assertArrayHasKey('remaining_to_schedule', $payments);
        $this->assertArrayHasKey('remaining_to_pay', $payments);
    }

    /**
     * Test that contracts KPIs handle contracts with null total_value correctly
     * 
     * Round 46: Hardening & Polish - Edge case total_value = null
     * 
     * Contract B (total_value = null) should contribute to budget_total and actual_total,
     * but NOT to contract_vs_actual_diff_total or overrun_contracts_count.
     */
    public function test_contracts_kpis_budget_and_actual_ignore_contracts_without_total_value_in_diffs(): void
    {
        // Contract A: total_value = 1_000_000
        $contractA = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 1_000_000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // BudgetLines active: 600_000
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contractA->id,
            'total_amount' => 600_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Expenses active: 400_000
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contractA->id,
            'amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Contract B: total_value = null
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => null,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // BudgetLines active: 700_000
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contractB->id,
            'total_amount' => 700_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Expenses active: 800_000
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contractB->id,
            'amount' => 800_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call KPIs endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');

        // Assert budget block
        $budget = $contracts['budget'];
        // budget_total should include both A and B: 600_000 + 700_000 = 1_300_000
        $this->assertEquals(1_300_000.00, (float) $budget['budget_total'], 'Budget total should include both contracts', 0.01);

        // Assert actual block
        $actual = $contracts['actual'];
        // actual_total should include both A and B: 400_000 + 800_000 = 1_200_000
        $this->assertEquals(1_200_000.00, (float) $actual['actual_total'], 'Actual total should include both contracts', 0.01);

        // contract_vs_actual_diff_total should ONLY count contract A (with total_value != null)
        // A: 1_000_000 - 400_000 = 600_000
        // B: NOT counted (total_value = null)
        $this->assertEquals(600_000.00, (float) $actual['contract_vs_actual_diff_total'], 'Contract vs actual diff should only count contracts with total_value', 0.01);

        // overrun_contracts_count should NOT count contract B (total_value = null)
        // Contract A: actual (400_000) < total_value (1_000_000) → not overrun
        // Contract B: NOT counted (total_value = null)
        $this->assertEquals(0, $actual['overrun_contracts_count'], 'Overrun count should not include contracts without total_value');
    }

    /**
     * Test that contracts KPIs don't crash when all contracts have null total_value
     * 
     * Round 46: Hardening & Polish - Edge case total_value = null
     */
    public function test_contracts_kpis_with_all_contracts_without_total_value_do_not_crash(): void
    {
        // Create 2 contracts, both with total_value = null
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => null,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => null,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Add some budgetLines and expenses for fun
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'total_amount' => 500_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'amount' => 300_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract2->id,
            'total_amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract2->id,
            'amount' => 200_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call KPIs endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/kpis');

        $response->assertStatus(200);
        $contracts = $response->json('data.contracts');

        // Assert budget block
        $budget = $contracts['budget'];
        $this->assertEquals(900_000.00, (float) $budget['budget_total'], 'Budget total should be 500000 + 400000 = 900000', 0.01);
        $this->assertGreaterThan(0, $budget['budget_total'], 'Budget total should be > 0');

        // Assert actual block
        $actual = $contracts['actual'];
        $this->assertEquals(500_000.00, (float) $actual['actual_total'], 'Actual total should be 300000 + 200000 = 500000', 0.01);
        $this->assertGreaterThan(0, $actual['actual_total'], 'Actual total should be > 0');

        // contract_vs_actual_diff_total should be 0 (no contracts with total_value)
        $this->assertEquals(0.0, (float) $actual['contract_vs_actual_diff_total'], 'Contract vs actual diff should be 0 when no contracts have total_value', 0.01);

        // overrun_contracts_count should be 0 (no contracts with total_value to check)
        $this->assertEquals(0, $actual['overrun_contracts_count'], 'Overrun count should be 0 when no contracts have total_value');
    }
}

