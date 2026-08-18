<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryCostSettlementAllocation;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryCostSettlementAllocationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_cost_settlement_allocations'));
        $this->assertTrue(Schema::hasColumns('treasury_cost_settlement_allocations', [
            'id', 'tenant_id', 'financial_document_id', 'advance_settlement_id',
            'cost_source_contract_expense_id', 'cost_source_material_receipt_line_id',
            'direction', 'allocated_amount', 'reverses_allocation_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_cost_settlement_allocations', 'updated_at'));
    }

    private function makeFinancialDocument(Tenant $tenant, Project $project, User $user): TreasuryFinancialDocument
    {
        $party = TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P',
        ]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        return TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'advance', 'status' => 'posted_unreconciled',
            'amount' => 100, 'source_wallet_id' => $wallet->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);
    }

    private function makeContractExpense(Tenant $tenant, Project $project, User $user): ContractExpense
    {
        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-TCSA-01',
            'title' => 'HĐ test tcsa',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);

        return ContractExpense::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-08-17',
            'amount' => 5000,
            'category' => 'labor',
            'description' => 'Test expense',
            'recorded_by' => (string) $user->id,
        ]);
    }

    private function makeMaterialReceiptLine(Tenant $tenant, Project $project): MaterialReceiptLine
    {
        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-TCSA-01',
            'receipt_date' => '2026-08-17',
        ]);

        $material = Material::query()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'MAT-TCSA-01',
            'name' => 'Test Material',
            'category' => 'concrete',
            'unit' => 'm3',
            'description' => 'Test material description',
            'is_active' => true,
        ]);

        return MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 10,
            'unit_cost' => 25,
        ]);
    }

    public function test_positive_amount_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $doc = $this->makeFinancialDocument($tenant, $project, $user);
        $expense = $this->makeContractExpense($tenant, $project, $user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be > 0/');

        TreasuryCostSettlementAllocation::create([
            'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id,
            'direction' => TreasuryCostSettlementAllocation::DIRECTION_APPLY,
            'allocated_amount' => 0,
        ]);
    }

    public function test_exactly_one_settlement_source_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $doc = $this->makeFinancialDocument($tenant, $project, $user);
        $expense = $this->makeContractExpense($tenant, $project, $user);

        $advanceSettlement = \App\Models\Treasury\TreasuryAdvanceSettlement::create([
            'tenant_id' => $tenant->id,
            'advance_id' => \App\Models\Treasury\TreasuryAdvance::create([
                'tenant_id' => $tenant->id, 'project_id' => $project->id,
                'financial_party_id' => TreasuryFinancialParty::create([
                    'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P2',
                ])->id,
                'originating_financial_document_id' => $doc->id, 'amount' => 100,
            ])->id,
            'settlement_type' => \App\Models\Treasury\TreasuryAdvanceSettlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
            'direction' => \App\Models\Treasury\TreasuryAdvanceSettlement::DIRECTION_APPLY,
            'amount' => 100,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of/');

        TreasuryCostSettlementAllocation::create([
            'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id,
            'advance_settlement_id' => $advanceSettlement->id,
            'cost_source_contract_expense_id' => $expense->id,
            'direction' => TreasuryCostSettlementAllocation::DIRECTION_APPLY,
            'allocated_amount' => 100,
        ]);
    }

    public function test_exactly_one_cost_source_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $doc = $this->makeFinancialDocument($tenant, $project, $user);
        $expense = $this->makeContractExpense($tenant, $project, $user);
        $line = $this->makeMaterialReceiptLine($tenant, $project);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of/');

        TreasuryCostSettlementAllocation::create([
            'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id,
            'cost_source_material_receipt_line_id' => $line->id,
            'direction' => TreasuryCostSettlementAllocation::DIRECTION_APPLY,
            'allocated_amount' => 100,
        ]);
    }

    public function test_allowed_direction_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $doc = $this->makeFinancialDocument($tenant, $project, $user);
        $expense = $this->makeContractExpense($tenant, $project, $user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be one of/');

        TreasuryCostSettlementAllocation::create([
            'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id,
            'direction' => 'not_a_real_direction',
            'allocated_amount' => 100,
        ]);
    }
}
