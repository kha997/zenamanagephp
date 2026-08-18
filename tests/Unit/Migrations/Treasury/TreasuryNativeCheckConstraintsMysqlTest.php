<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\Migrations\Treasury\Concerns\BuildsTreasuryCheckFixtures;

/**
 * GAP-038 Gate 2 Option B conformance proof (MySQL path). Every row
 * written here via DB::connection('mysql')->table()->insert() is a raw
 * query-builder write that never instantiates an Eloquent model and never
 * fires the `saving` event -- EnforcesRowInvariants cannot run for any
 * insert in this file. If a violating row is still rejected, the
 * rejection came from the database engine itself (a real CHECK
 * constraint), not from application code.
 *
 * This file explicitly targets the named "mysql" connection rather than
 * relying on the test process's default connection: this repository's
 * phpunit.xml pins `<env name="DB_CONNECTION" value="sqlite"/>` WITHOUT
 * force="true", and PHPUnit's own XML <env> wins over an already-set real
 * shell environment variable (verified empirically against this exact
 * repo's CI: a job that exports DB_CONNECTION=mysql still resolves
 * DB::getDriverName() to "sqlite" inside the test process). The only way
 * to genuinely exercise the real MySQL service this repo's
 * automated-testing.yml CI job spins up is to connect to the named
 * "mysql" connection explicitly -- exactly the pattern this repository's
 * own DocumentWorkflowConcurrencyTest/RfiEscalationConcurrencyTest
 * already use. See TreasuryNativeCheckConstraintsTest.php for the
 * SQLite-path equivalent (default connection).
 */
class TreasuryNativeCheckConstraintsMysqlTest extends TestCase
{
    use BuildsTreasuryCheckFixtures;

    private const CONN = 'mysql';

    /** @group stress */
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection(self::CONN)->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove native CHECK '
                . 'constraint enforcement on the actual target production/CI database '
                . 'engine, not sqlite. The "mysql" connection in config/database.php is '
                . 'not reachable in this environment (' . $e->getMessage() . '). Runs for '
                . 'real under this repository\'s automated-testing.yml CI (mysql:8.0 service).'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection(self::CONN)->getPdo()) {
                foreach ([
                    'treasury_fund_chain_members', 'treasury_advance_settlements', 'treasury_advances',
                    'treasury_cost_settlement_allocations', 'treasury_ledger_entries',
                    'treasury_payment_route_legs', 'treasury_payment_routes', 'treasury_financial_documents',
                    'material_receipt_lines', 'material_receipts', 'materials', 'contract_expenses', 'contracts',
                    'contract_payments', 'treasury_financial_parties', 'treasury_wallets',
                    'users', 'projects', 'tenants',
                ] as $table) {
                    DB::connection(self::CONN)->table($table)->delete();
                }
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment -- nothing to clean up.
        }
        parent::tearDown();
    }


    private function ulid(): string
    {
        return (string) Str::ulid();
    }

    // --- treasury_financial_documents ---------------------------------

    public function test_financial_documents_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_financial_documents')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 100,
            'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_financial_documents', ['id' => $id]);
    }

    public function test_financial_documents_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 0,
            'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_financial_documents_source_mutex_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'expense', 'status' => 'draft', 'amount' => 100,
            'source_wallet_id' => $wallet->id, 'source_party_id' => $party->id,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_financial_documents_destination_mutex_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_financial_documents')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft', 'amount' => 100,
            'destination_wallet_id' => $wallet->id, 'destination_party_id' => $party->id,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_payment_routes ---------------------------------------

    public function test_payment_routes_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_payment_routes')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_payment_routes', ['id' => $id]);
    }

    public function test_payment_routes_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 0, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_payment_routes_link_exactly_one_is_rejected_by_database_when_neither_set(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $project = $this->project($tenant, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_payment_routes_contract_wallet_conullable_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $contract = \App\Models\Contract::on(self::CONN)->create([
            'tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id,
            'code' => 'CTR-CHK-'.uniqid(), 'title' => 'HĐ', 'contract_type' => \App\Models\Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);
        $contractPayment = \App\Models\ContractPayment::on(self::CONN)->create(\App\Models\ContractPayment::factory()->raw([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
        ]));

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_payment_routes')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_contract_payment_id' => $contractPayment->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_payment_route_legs ------------------------------------

    public function test_payment_route_legs_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $route = $this->paymentRoute($tenant, $project, $doc, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_payment_route_legs')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id, 'amount' => 100,
            'status' => 'in_transit', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_payment_route_legs', ['id' => $id]);
    }

    public function test_payment_route_legs_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $route = $this->paymentRoute($tenant, $project, $doc, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_payment_route_legs')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id, 'amount' => -5,
            'status' => 'in_transit', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_ledger_entries -----------------------------------------

    public function test_ledger_entries_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_ledger_entries')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "raw:{$id}", 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_ledger_entries', ['id' => $id]);
    }

    public function test_ledger_entries_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_ledger_entries')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 0,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => 'raw:zero', 'created_at' => now(),
        ]);
    }

    public function test_ledger_entries_source_exactly_one_is_rejected_by_database_when_both_set(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $route = $this->paymentRoute($tenant, $project, $doc, self::CONN);
        $leg = $this->paymentRouteLeg($tenant, $route, $wallet, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_ledger_entries')->insert([
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
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $expense = $this->contractExpense($tenant, $project, $user, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_cost_settlement_allocations')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 100, 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_cost_settlement_allocations', ['id' => $id]);
    }

    public function test_cost_settlement_allocations_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $expense = $this->contractExpense($tenant, $project, $user, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 0, 'created_at' => now(),
        ]);
    }

    public function test_cost_settlement_allocations_source_exactly_one_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $party = $this->party($tenant, 'P2', self::CONN);
        $advance = $this->advance($tenant, $project, $party, $doc, self::CONN);
        $settlement = $this->advanceSettlement($tenant, $advance, self::CONN);
        $expense = $this->contractExpense($tenant, $project, $user, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id,
            'financial_document_id' => $doc->id, 'advance_settlement_id' => $settlement->id,
            'cost_source_contract_expense_id' => $expense->id, 'direction' => 'apply',
            'allocated_amount' => 100, 'created_at' => now(),
        ]);
    }

    public function test_cost_settlement_allocations_cost_source_exactly_one_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $expense = $this->contractExpense($tenant, $project, $user, self::CONN);
        $line = $this->materialReceiptLine($tenant, $project, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_cost_settlement_allocations')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'financial_document_id' => $doc->id,
            'cost_source_contract_expense_id' => $expense->id,
            'cost_source_material_receipt_line_id' => $line->id,
            'direction' => 'apply', 'allocated_amount' => 100, 'created_at' => now(),
        ]);
    }

    // --- treasury_advances -------------------------------------------------

    public function test_advances_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_advances')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id, 'originating_financial_document_id' => $doc->id,
            'amount' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_advances', ['id' => $id]);
    }

    public function test_advances_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_advances')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id, 'originating_financial_document_id' => $doc->id,
            'amount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- treasury_advance_settlements ---------------------------------------

    public function test_advance_settlements_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party, self::CONN);
        $advance = $this->advance($tenant, $project, $party, $doc, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_advance_settlements')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => 'approved_expense', 'direction' => 'apply',
            'amount' => 100, 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_advance_settlements', ['id' => $id]);
    }

    public function test_advance_settlements_amount_not_positive_is_rejected_by_database(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $party = $this->party($tenant, self::CONN);
        $doc = $this->advanceOriginatingDocument($tenant, $project, $user, $wallet, $party, self::CONN);
        $advance = $this->advance($tenant, $project, $party, $doc, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_advance_settlements')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => 'approved_expense', 'direction' => 'apply',
            'amount' => 0, 'created_at' => now(),
        ]);
    }

    // --- treasury_fund_chain_members ----------------------------------------

    public function test_fund_chain_members_valid_row_is_accepted_by_raw_insert(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $fundChain = $this->fundChain($tenant, $project, self::CONN);

        $id = $this->ulid();
        DB::connection(self::CONN)->table('treasury_fund_chain_members')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'member_financial_document_id' => $doc->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treasury_fund_chain_members', ['id' => $id]);
    }

    public function test_fund_chain_members_exactly_one_is_rejected_by_database_when_both_set(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $user = $this->user($tenant, self::CONN);
        $project = $this->project($tenant, self::CONN);
        $wallet = $this->wallet($tenant, self::CONN);
        $doc = $this->financialDocument($tenant, $project, $user, $wallet, self::CONN);
        $route = $this->paymentRoute($tenant, $project, $doc, self::CONN);
        $fundChain = $this->fundChain($tenant, $project, self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_fund_chain_members')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'member_financial_document_id' => $doc->id, 'member_payment_route_id' => $route->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_fund_chain_members_exactly_one_is_rejected_by_database_when_neither_set(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = $this->tenant(self::CONN);
        $fundChain = $this->fundChain($tenant, $this->project($tenant, self::CONN), self::CONN);

        $this->expectException(QueryException::class);

        DB::connection(self::CONN)->table('treasury_fund_chain_members')->insert([
            'id' => $this->ulid(), 'tenant_id' => $tenant->id, 'fund_chain_id' => $fundChain->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
