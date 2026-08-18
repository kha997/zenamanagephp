<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryLedgerEntry;
use App\Models\Treasury\TreasuryReconciliation;
use App\Models\Treasury\TreasuryReconciliationEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryReconciliationEntriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_reconciliation_entries'));
        $this->assertTrue(Schema::hasColumns('treasury_reconciliation_entries', [
            'id', 'tenant_id', 'reconciliation_id', 'ledger_entry_id', 'direction',
            'reverses_reconciliation_entry_id', 'actor_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_reconciliation_entries', 'updated_at'));
    }

    public function test_all_fourteen_treasury_tables_exist(): void
    {
        foreach ([
            'treasury_financial_parties', 'treasury_wallets', 'treasury_financial_documents',
            'treasury_payment_routes', 'treasury_payment_route_legs', 'treasury_ledger_entries',
            'treasury_fund_chains', 'treasury_advances', 'treasury_advance_settlements',
            'treasury_cost_settlement_allocations', 'treasury_expense_approvals',
            'treasury_reconciliations', 'treasury_fund_chain_members', 'treasury_reconciliation_entries',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} must exist per design-doc Sec 16's 14-table inventory");
        }
    }

    public function test_allowed_direction_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $document = TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 100,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
        $ledgerEntry = TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'source_financial_document_id' => $document->id,
            'wallet_id' => $wallet->id,
            'direction' => 'debit',
            'amount' => 100,
            'entry_type' => 'funding_posted',
            'posted_at' => now(),
            'original_posting_key' => 'posting-key-1',
        ]);
        $reconciliation = TreasuryReconciliation::create([
            'tenant_id' => $tenant->id,
            'wallet_id' => $wallet->id,
            'reconciliation_type' => 'bank_statement',
            'reconciled_at' => now(),
            'reconciled_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be one of/');

        TreasuryReconciliationEntry::create([
            'tenant_id' => $tenant->id,
            'reconciliation_id' => $reconciliation->id,
            'ledger_entry_id' => $ledgerEntry->id,
            'direction' => 'not_a_real_direction',
            'actor_id' => $user->id,
        ]);
    }
}
