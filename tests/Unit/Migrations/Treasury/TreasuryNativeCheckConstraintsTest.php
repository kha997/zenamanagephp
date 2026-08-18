<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\Migrations\Treasury\Concerns\BuildsTreasuryCheckFixtures;

/**
 * GAP-038 Gate 2 Option B conformance proof: every row written here via
 * DB::table()->insert() is a raw query-builder write that never
 * instantiates an Eloquent model and never fires the `saving` event --
 * EnforcesRowInvariants cannot run for any insert in this file. If a
 * violating row is still rejected, the rejection came from the database
 * engine itself (a real CHECK constraint on MySQL, or the equivalent
 * BEFORE INSERT trigger on SQLite -- see App\Support\Treasury\
 * TreasuryCheckConstraint), not from application code.
 *
 * This file's DB connection is whatever `DB_CONNECTION` resolves to for
 * the current test run -- `sqlite` locally (phpunit.xml's default,
 * unmodified by this file) and `mysql` in this repository's
 * `automated-testing.yml` CI job, which exports DB_CONNECTION=mysql as a
 * real environment variable before invoking phpunit (phpunit.xml's
 * <env name="DB_CONNECTION" value="sqlite"/> has no force="true", so it
 * never overrides an already-set real env var -- standard PHPUnit
 * precedence). The same test file therefore proves both of this
 * repository's two supported database paths without duplication.
 */
class TreasuryNativeCheckConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTreasuryCheckFixtures;

    private function ulid(): string
    {
        return (string) Str::ulid();
    }

    // --- treasury_financial_documents ---------------------------------

    public function test_financial_documents_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);

        $id = $this->ulid();
        DB::table('treasury_financial_documents')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 100,
            'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_financial_documents', ['id' => $id]);
    }

    public function test_financial_documents_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);

        $this->expectException(QueryException::class);

        DB::table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 0,
            'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_financial_documents_source_mutex_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);

        $this->expectException(QueryException::class);

        DB::table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'expense', 'status' => 'draft', 'amount' => 100,
            'source_wallet_id' => $wallet->id, 'source_party_id' => $party->id,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_financial_documents_destination_mutex_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);

        $this->expectException(QueryException::class);

        DB::table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 100,
            'destination_wallet_id' => $wallet->id, 'destination_party_id' => $party->id,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_payment_routes ---------------------------------------

    public function test_payment_routes_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);

        $id = $this->ulid();
        DB::table('treasury_payment_routes')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_payment_routes', ['id' => $id]);
    }

    public function test_payment_routes_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);

        $this->expectException(QueryException::class);

        DB::table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 0, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_payment_routes_link_exactly_one_is_rejected_by_database_when_neither_set(): void
    {
        $tenant = $this->tenant();
        $project = $this->project($tenant);

        $this->expectException(QueryException::class);

        DB::table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_payment_routes_contract_wallet_conullable_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $contract = \App\Models\Contract::query()->create([
            'tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id,
            'code' => 'CTR-CHK-'.uniqid(), 'title' => 'HĐ', 'contract_type' => \App\Models\Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);
        $contractPayment = \App\Models\ContractPayment::factory()->create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_contract_payment_id' => $contractPayment->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_payment_route_legs ------------------------------------

    public function test_payment_route_legs_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $route = $this->paymentRoute($tenant, $project, $doc);

        $id = $this->ulid();
        DB::table('treasury_payment_route_legs')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id, 'amount' => 100,
            'status' => 'in_transit', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_payment_route_legs', ['id' => $id]);
    }

    public function test_payment_route_legs_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $route = $this->paymentRoute($tenant, $project, $doc);

        $this->expectException(QueryException::class);

        DB::table('treasury_payment_route_legs')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id, 'amount' => -5,
            'status' => 'in_transit', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_ledger_entries -----------------------------------------

    public function test_ledger_entries_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);

        $id = $this->ulid();
        DB::table('treasury_ledger_entries')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "raw:{$id}", 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_ledger_entries', ['id' => $id]);
    }

    public function test_ledger_entries_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);

        $this->expectException(QueryException::class);

        DB::table('treasury_ledger_entries')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 0,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => 'raw:zero', 'created_at' => now(),
        ]);
    }

    public function test_ledger_entries_source_exactly_one_is_rejected_by_database_when_both_set(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $route = $this->paymentRoute($tenant, $project, $doc);
        $leg = $this->paymentRouteLeg($tenant, $route, $wallet);

        $this->expectException(QueryException::class);

        DB::table('treasury_ledger_entries')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id,
            'source_financial_document_id' => $doc->id, 'source_payment_route_leg_id' => $leg->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => 'raw:both', 'created_at' => now(),
        ]);
    }

    // --- treasury_cost_settlement_allocations -----------------------------

    public function test_cost_settlement_allocations_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $expense = $this->contractExpense($tenant, $project, $user);

        $id = $this->ulid();
        DB::table('treasury_cost_settlement_allocations')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 100, 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_cost_settlement_allocations', ['id' => $id]);
    }

    public function test_cost_settlement_allocations_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $expense = $this->contractExpense($tenant, $project, $user);

        $this->expectException(QueryException::class);

        DB::table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 0, 'created_at' => now(),
        ]);
    }

    public function test_cost_settlement_allocations_source_exactly_one_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $party = $this->party($tenant, 'P2');
        $advance = $this->advance($tenant, $project, $party, $doc);
        $settlement = $this->advanceSettlement($tenant, $advance);
        $expense = $this->contractExpense($tenant, $project, $user);

        $this->expectException(QueryException::class);

        DB::table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id, 'advance_settlement_id' => $settlement->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 100, 'created_at' => now(),
        ]);
    }

    public function test_cost_settlement_allocations_cost_source_exactly_one_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $expense = $this->contractExpense($tenant, $project, $user);
        $line = $this->materialReceiptLine($tenant, $project);

        $this->expectException(QueryException::class);

        DB::table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id,
            'cost_source_material_receipt_line_id' => $line->id,
            'direction' => 'apply', 'allocated_amount' => 100, 'created_at' => now(),
        ]);
    }

    // --- treasury_advances -------------------------------------------------

    public function test_advances_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party);

        $id = $this->ulid();
        DB::table('treasury_advances')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id, 'originating_financial_document_id' => $doc->id,
            'amount' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_advances', ['id' => $id]);
    }

    public function test_advances_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party);

        $this->expectException(QueryException::class);

        DB::table('treasury_advances')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id, 'originating_financial_document_id' => $doc->id,
            'amount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_advance_settlements ---------------------------------------

    public function test_advance_settlements_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party);
        $advance = $this->advance($tenant, $project, $party, $doc);

        $id = $this->ulid();
        DB::table('treasury_advance_settlements')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => 'approved_expense', 'direction' => 'apply',
            'amount' => 100, 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_advance_settlements', ['id' => $id]);
    }

    public function test_advance_settlements_amount_not_positive_is_rejected_by_database(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $party = $this->party($tenant);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party);
        $advance = $this->advance($tenant, $project, $party, $doc);

        $this->expectException(QueryException::class);

        DB::table('treasury_advance_settlements')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => 'approved_expense', 'direction' => 'apply',
            'amount' => 0, 'created_at' => now(),
        ]);
    }

    // --- treasury_fund_chain_members ----------------------------------------

    public function test_fund_chain_members_valid_row_is_accepted_by_raw_insert(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $fundChain = $this->fundChain($tenant, $project);

        $id = $this->ulid();
        DB::table('treasury_fund_chain_members')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'member_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_fund_chain_members', ['id' => $id]);
    }

    public function test_fund_chain_members_exactly_one_is_rejected_by_database_when_both_set(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $project = $this->project($tenant);
        $wallet = $this->wallet($tenant);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet);
        $route = $this->paymentRoute($tenant, $project, $doc);
        $fundChain = $this->fundChain($tenant, $project);

        $this->expectException(QueryException::class);

        DB::table('treasury_fund_chain_members')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'member_financial_document_id' => $doc->id, 'member_payment_route_id' => $route->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_fund_chain_members_exactly_one_is_rejected_by_database_when_neither_set(): void
    {
        $tenant = $this->tenant();
        $fundChain = $this->fundChain($tenant, $this->project($tenant));

        $this->expectException(QueryException::class);

        DB::table('treasury_fund_chain_members')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
