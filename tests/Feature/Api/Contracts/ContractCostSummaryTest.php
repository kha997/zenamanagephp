<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

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
 * Tests for Contract Cost Summary API
 * 
 * Round 45: Contract Cost Control - Cost Summary
 * 
 * Tests that cost summary endpoint returns correct budget, actual, and payments data.
 * 
 * @group contracts
 * @group contract-cost-summary
 */
class ContractCostSummaryTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private Contract $contract;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('contract-cost-summary');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create user with admin role (has tenant.view_contracts)
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-TEST-001',
            'name' => 'Test Contract',
            'currency' => 'USD',
            'total_value' => 1_000_000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that cost summary returns correct values for contract with budget, actual, and payments
     * 
     * Round 45: Contract Cost Control - Cost Summary
     */
    public function test_can_get_contract_cost_summary_for_tenant(): void
    {
        // Budget lines: 600_000 active + 100_000 cancelled
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'total_amount' => 600_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'total_amount' => 100_000.00,
            'status' => 'cancelled',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Expenses: 400_000 active + 100_000 cancelled
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'amount' => 100_000.00,
            'status' => 'cancelled',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Payments: 700_000 scheduled (300_000 paid, 400_000 planned)
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'amount' => 300_000.00,
            'status' => 'paid',
            'due_date' => Carbon::now()->subMonth(),
            'paid_at' => Carbon::now()->subMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'amount' => 400_000.00,
            'status' => 'planned',
            'due_date' => Carbon::now()->addMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // 1 payment overdue (100_000)
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'amount' => 100_000.00,
            'status' => 'due',
            'due_date' => Carbon::now()->subDays(5), // Overdue
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call cost summary endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/cost-summary");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'contract_value',
                    'budget_total',
                    'actual_total',
                    'payments_scheduled_total',
                    'payments_paid_total',
                    'remaining_to_schedule',
                    'remaining_to_pay',
                    'budget_vs_contract_diff',
                    'contract_vs_actual_diff',
                    'overdue_payments_count',
                    'overdue_payments_total',
                ],
            ],
        ]);

        $summary = $response->json('data.summary');

        // Assert values
        $this->assertEquals(1_000_000.00, (float) $summary['contract_value'], 'Contract value should be 1000000', 0.01);
        $this->assertEquals(600_000.00, (float) $summary['budget_total'], 'Budget total should be 600000 (excluding cancelled)', 0.01);
        $this->assertEquals(400_000.00, (float) $summary['actual_total'], 'Actual total should be 400000 (excluding cancelled)', 0.01);
        $this->assertEquals(800_000.00, (float) $summary['payments_scheduled_total'], 'Payments scheduled total should be 300000 + 400000 + 100000 = 800000', 0.01);
        $this->assertEquals(300_000.00, (float) $summary['payments_paid_total'], 'Payments paid total should be 300000', 0.01);
        $this->assertEquals(200_000.00, (float) $summary['remaining_to_schedule'], 'Remaining to schedule should be 1000000 - 800000 = 200000', 0.01);
        $this->assertEquals(500_000.00, (float) $summary['remaining_to_pay'], 'Remaining to pay should be 800000 - 300000 = 500000', 0.01);
        $this->assertEquals(-400_000.00, (float) $summary['budget_vs_contract_diff'], 'Budget vs contract diff should be 600000 - 1000000 = -400000', 0.01);
        $this->assertEquals(600_000.00, (float) $summary['contract_vs_actual_diff'], 'Contract vs actual diff should be 1000000 - 400000 = 600000', 0.01);
        $this->assertEquals(1, $summary['overdue_payments_count'], 'Overdue payments count should be 1');
        $this->assertEquals(100_000.00, (float) $summary['overdue_payments_total'], 'Overdue payments total should be 100000', 0.01);
    }

    /**
     * Test that cost summary is tenant-isolated
     * 
     * Round 45: Contract Cost Control - Multi-tenant isolation
     */
    public function test_contract_cost_summary_is_tenant_isolated(): void
    {
        // Create another tenant with contract
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);

        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'CT-OTHER-001',
            'name' => 'Other Contract',
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

        // User A should NOT be able to get cost summary of contract B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson("/api/v1/app/contracts/{$otherContract->id}/cost-summary");

        $response->assertStatus(404);
    }

    /**
     * Test that cost summary requires view_contracts permission
     * 
     * Round 45: Contract Cost Control - RBAC
     */
    public function test_contract_cost_summary_requires_view_contracts_permission(): void
    {
        // Create user without tenant.view_contracts (guest role)
        $guestUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $guestUser->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_contracts
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($guestUser);
        $guestToken = $guestUser->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET cost-summary
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$guestToken}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/cost-summary");

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that cost summary handles null contract_value correctly
     * 
     * Round 46: Hardening & Polish - Edge case total_value = null
     */
    public function test_contract_cost_summary_handles_null_contract_value(): void
    {
        // Create contract with total_value = null
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-NULL-001',
            'name' => 'Contract Without Value',
            'currency' => 'USD',
            'total_value' => null,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Add budget lines
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'total_amount' => 500_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'total_amount' => 300_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Add expenses
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 200_000.00,
            'status' => 'approved',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Add payments
        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 300_000.00,
            'status' => 'paid',
            'due_date' => Carbon::now()->subMonth(),
            'paid_at' => Carbon::now()->subMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'amount' => 200_000.00,
            'status' => 'planned',
            'due_date' => Carbon::now()->addMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Call cost summary endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson("/api/v1/app/contracts/{$contract->id}/cost-summary");

        $response->assertStatus(200);
        $summary = $response->json('data.summary');

        // Assert contract_value is null
        $this->assertNull($summary['contract_value'], 'Contract value should be null');

        // Assert budget_total and actual_total are still numbers
        $this->assertEquals(800_000.00, (float) $summary['budget_total'], 'Budget total should be 500000 + 300000 = 800000', 0.01);
        $this->assertEquals(600_000.00, (float) $summary['actual_total'], 'Actual total should be 400000 + 200000 = 600000', 0.01);

        // Assert diff fields are null (depend on contract_value)
        $this->assertNull($summary['budget_vs_contract_diff'], 'Budget vs contract diff should be null when contract_value is null');
        $this->assertNull($summary['contract_vs_actual_diff'], 'Contract vs actual diff should be null when contract_value is null');

        // Assert remaining_to_schedule is null (depends on contract_value)
        $this->assertNull($summary['remaining_to_schedule'], 'Remaining to schedule should be null when contract_value is null');

        // Assert remaining_to_pay is still a number (depends on payments, not contract_value)
        $this->assertEquals(200_000.00, (float) $summary['remaining_to_pay'], 'Remaining to pay should be 500000 - 300000 = 200000', 0.01);
    }
}

