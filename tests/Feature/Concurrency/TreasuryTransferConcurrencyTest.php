<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvance;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\Treasury\TreasuryPaymentRouteLeg;
use App\Models\Treasury\TreasuryReconciliationEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRouteService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * GAP-064 — proves the negative-balance guard holds under real concurrency:
 * two independent OS processes / MySQL connections each try to move 70 out
 * of a wallet holding 100. Exactly one may succeed; the wallet must end at
 * 30, never −40. Process A holds its transaction open after posting, so a
 * guard without locking would let B read the stale balance and overdraw.
 * Sequential sqlite calls cannot prove this; the test skips without MySQL.
 */
#[Group('stress')]
class TreasuryTransferConcurrencyTest extends TestCase
{
    private ?string $originalDefaultConnection = null;

    /** @group stress */
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove the Treasury wallet lock, not sqlite. '
                . 'The "mysql" connection is not reachable here (' . $e->getMessage() . ').'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                foreach (['audit_logs', 'treasury_reconciliation_entries', 'treasury_reconciliations', 'treasury_cost_settlement_allocations', 'treasury_advance_settlements', 'treasury_advances', 'treasury_expense_approvals', 'treasury_ledger_entries',
                    'treasury_payment_route_legs', 'treasury_payment_routes', 'treasury_financial_documents', 'contract_expenses', 'contract_payments', 'contracts', 'treasury_wallets',
                    'treasury_financial_parties', 'projects', 'user_roles', 'role_permissions', 'users', 'tenants'] as $table) {
                    DB::connection('mysql')->table($table)->delete();
                }
            }
        } catch (\Throwable) {
            // MySQL not reachable — nothing to clean up.
        }
        if ($this->originalDefaultConnection !== null) {
            DB::setDefaultConnection($this->originalDefaultConnection);
        }
        parent::tearDown();
    }

    public function test_two_concurrent_transfers_cannot_overdraw_the_source_wallet(): void
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $tenant->id, 'pm_id' => $actor->id, 'created_by' => $actor->id,
        ]));
        $investor = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'investor', 'name' => 'A']);
        $holder = TreasuryFinancialParty::on('mysql')->create([
            'tenant_id' => $tenant->id, 'party_type' => 'employee', 'name' => 'Z', 'linked_user_id' => $actor->id,
        ]);
        $source = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'employee_cash',
            'name' => 'Source', 'custodian_party_id' => $holder->id,
        ]);
        $destination = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'company_bank', 'name' => 'Dest',
        ]);
        app(TreasuryPostingService::class)->declareFunding($project, $actor, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $source->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
        ]);

        $php = (new PhpExecutableFinder())->find();
        $args = fn (string $hold): array => [
            $php, 'artisan', 'treasury:concurrency-test-transfer',
            (string) $project->id, (string) $actor->id, (string) $source->id, (string) $destination->id, '70', '--hold=' . $hold,
        ];
        $procA = new Process($args('3'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process($args('0'), base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $this->assertSame([0, 1], $exitCodes, 'Exactly one transfer may succeed. ' . $output);
        $this->assertStringContainsString('REFUSED', $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput(), $output);

        $this->assertSame('30.00', app(TreasuryBalanceService::class)->walletBalance($source->fresh()));
        $this->assertSame(1, TreasuryFinancialDocument::on('mysql')
            ->where('project_id', $project->id)->where('document_type', 'internal_transfer')->count());
    }

    /**
     * GAP-066 — two expenses paid from two DIFFERENT wallets (so the wallet
     * lock cannot serialise them) each try to allocate 70 to the same cost of
     * 100. Only the cost-source lock (v17 class 2) plus the locking
     * net-allocation read can stop the second one.
     */
    public function test_two_concurrent_expense_approvals_cannot_overpay_one_cost(): void
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $creator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $approver = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $tenant->id, 'pm_id' => $creator->id, 'created_by' => $creator->id,
        ]));
        $investor = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'investor', 'name' => 'A']);
        $holder = TreasuryFinancialParty::on('mysql')->create([
            'tenant_id' => $tenant->id, 'party_type' => 'employee', 'name' => 'Z', 'linked_user_id' => $creator->id,
        ]);
        $payee = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'labour', 'name' => 'D']);
        $walletA = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'employee_cash', 'name' => 'A', 'custodian_party_id' => $holder->id,
        ]);
        $walletB = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'employee_cash', 'name' => 'B', 'custodian_party_id' => $holder->id,
        ]);
        $posting = app(TreasuryPostingService::class);
        foreach ([$walletA, $walletB] as $wallet) {
            $posting->declareFunding($project, $creator, [
                'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
                'destination_wallet_id' => (string) $wallet->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
            ], true);
        }
        $contract = Contract::on('mysql')->create(Contract::factory()->raw([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'created_by' => $creator->id,
        ]));
        $cost = ContractExpense::on('mysql')->create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '100', 'category' => 'labor', 'description' => 'Cost',
        ]);
        $expenses = app(TreasuryExpenseService::class);
        $ids = [];
        foreach ([$walletA, $walletB] as $wallet) {
            $draft = $expenses->createDraft($project, $creator, [
                'source_wallet_id' => (string) $wallet->id, 'destination_party_id' => (string) $payee->id,
                'amount' => '70', 'transaction_date' => '2026-10-02',
                'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '70']],
            ]);
            $expenses->submit($project, $creator, $draft);
            $ids[] = (string) $draft->id;
        }

        $php = (new PhpExecutableFinder())->find();
        $args = fn (string $expenseId, string $hold): array => [
            $php, 'artisan', 'treasury:concurrency-test-approve-expense', (string) $project->id, (string) $approver->id, $expenseId, '--hold=' . $hold,
        ];
        $procA = new Process($args($ids[0], '3'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process($args($ids[1], '0'), base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $this->assertSame([0, 1], $exitCodes, 'Exactly one approval may succeed. ' . $output);
        $this->assertSame(7000, $expenses->netAllocationCents('contract_expense', (string) $cost->id));
    }

    /**
     * GAP-067 — two processes reconcile the same ledger entry at once. Only
     * the class-4 ledger-entry lock plus the locking active-apply read keep
     * it to one active apply (there is no database constraint for that).
     *
     * @group stress
     */
    public function test_two_concurrent_reconciliations_cannot_both_apply_one_entry(): void
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $tenant->id, 'pm_id' => $actor->id, 'created_by' => $actor->id,
        ]));
        $investor = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'investor', 'name' => 'A']);
        $wallet = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'company_bank', 'name' => 'Bank',
        ]);
        $funding = app(TreasuryPostingService::class)->declareFunding($project, $actor, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $wallet->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
        ]);
        $entryId = (string) TreasuryLedgerEntry::on('mysql')->where('source_financial_document_id', $funding->id)->value('id');

        $php = (new PhpExecutableFinder())->find();
        $args = fn (string $reference, string $hold): array => [
            $php, 'artisan', 'treasury:concurrency-test-reconcile',
            (string) $project->id, (string) $actor->id, (string) $wallet->id, $entryId, $reference, '--hold=' . $hold,
        ];
        $procA = new Process($args('SK-A', '3'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process($args('SK-B', '0'), base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $this->assertSame([0, 1], $exitCodes, 'Exactly one reconciliation may succeed. ' . $output);
        $this->assertSame(1, TreasuryReconciliationEntry::on('mysql')->where('ledger_entry_id', $entryId)->where('direction', 'apply')->count(), $output);
        $this->assertSame('posted_reconciled', TreasuryFinancialDocument::on('mysql')->whereKey($funding->id)->value('status'));
    }

    /**
     * GAP-094 — two processes each move 70 out of a transit wallet holding
     * 100 along the same route. The from-wallet lock (class 0) plus the
     * locking balance read must refuse the second leg.
     *
     * @group stress
     */
    public function test_two_concurrent_legs_cannot_drain_a_transit_wallet_twice(): void
    {
        [$project, $actor, $route, $transit, $site] = $this->routeFixture('100');
        app(TreasuryRouteService::class)->recordLeg($project, $actor, $route, [
            'to_wallet_id' => (string) $transit->id, 'amount' => '100', 'occurred_at' => '2026-10-02',
        ]);

        [$procA, $procB] = $this->raceLegs($project, $actor, $route, (string) $transit->id, (string) $site->id, '70');

        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one leg may leave the transit wallet. ' . $output);
        $this->assertSame('30.00', app(TreasuryBalanceService::class)->walletBalance($transit->fresh()));
        $this->assertSame(2, TreasuryPaymentRouteLeg::on('mysql')->where('payment_route_id', $route->id)->count());
    }

    /**
     * GAP-094 — two processes each record 70 "from outside" on a route of
     * 100. Only the route lock (class 6) plus the locking read of its legs
     * keep the money from outside within the route amount.
     *
     * @group stress
     */
    public function test_two_concurrent_legs_from_outside_cannot_exceed_the_route_amount(): void
    {
        [$project, $actor, $route, $transit] = $this->routeFixture('100');

        [$procA, $procB] = $this->raceLegs($project, $actor, $route, '-', (string) $transit->id, '70');

        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one leg from outside may be recorded. ' . $output);
        $this->assertSame('70.00', app(TreasuryBalanceService::class)->walletBalance($transit->fresh()));
        $this->assertSame(1, TreasuryPaymentRouteLeg::on('mysql')->where('payment_route_id', $route->id)->count());
    }

    /**
     * GAP-095 — two processes each trace 70 of a paid contract payment of 100.
     * Only the contract_payments row lock (v17 §11 class 1) plus the locking
     * read of its routes keep the routes within the payment amount.
     *
     * @group stress
     */
    public function test_two_concurrent_traces_cannot_exceed_the_contract_payment(): void
    {
        [$project, $actor, , , $site] = $this->routeFixture('1');
        $contract = Contract::on('mysql')->create(Contract::factory()->raw([
            'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'created_by' => $actor->id,
        ]));
        $payment = ContractPayment::on('mysql')->create([
            'tenant_id' => $project->tenant_id, 'contract_id' => $contract->id, 'name' => 'Đợt 1',
            'amount' => '100', 'status' => 'paid', 'paid_at' => '2026-10-05',
        ]);

        $php = (new PhpExecutableFinder())->find();
        $args = fn (string $hold): array => [
            $php, 'artisan', 'treasury:concurrency-test-trace-payment',
            (string) $project->id, (string) $actor->id, (string) $payment->id, (string) $site->id, '70', '--hold=' . $hold,
        ];
        $procA = new Process($args('3'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process($args('0'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one trace may be created. ' . $output);
        $this->assertSame(1, TreasuryPaymentRoute::on('mysql')->where('linked_contract_payment_id', $payment->id)->count(), $output);
    }

    /**
     * GAP-096 — a settlement approval and a cash return race on one advance
     * of 100 (70 each). Only one may reduce the outstanding amount; it must
     * end at 30, never below 0 (v17 §6.5, class-3 advance lock).
     *
     * @group stress
     */
    public function test_settlement_and_return_cannot_overdraw_one_advance(): void
    {
        [$project, $creator, $approver, $walletB, $cost, $advanceDoc] = $this->advanceFixture();
        app(TreasuryAdvanceService::class)->requestSettlement($project, $creator, $advanceDoc, [
            'amount' => '70', 'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '70']],
        ]);

        $php = (new PhpExecutableFinder())->find();
        $base = [$php, 'artisan', 'treasury:concurrency-test-advance'];
        $procA = new Process([...$base, 'return', (string) $project->id, (string) $approver->id, (string) $advanceDoc->id, (string) $walletB->id, '70', '--hold=3'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([...$base, 'settle', (string) $project->id, (string) $approver->id, (string) $advanceDoc->id], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one may reduce the advance. ' . $output);
        $advance = TreasuryAdvance::on('mysql')->where('originating_financial_document_id', $advanceDoc->id)->firstOrFail();
        $this->assertSame(3000, app(TreasuryAdvanceService::class)->outstandingCents($advance), $output);
    }

    /**
     * GAP-096 — a settlement approval and an S3 expense approval race on one
     * cost of 100 (70 each). Only one may be allocated (v17 §6.3, class 2).
     *
     * @group stress
     */
    public function test_settlement_and_expense_cannot_overpay_one_cost(): void
    {
        [$project, $creator, $approver, $walletB, $cost, $advanceDoc, $payee] = $this->advanceFixture();
        app(TreasuryAdvanceService::class)->requestSettlement($project, $creator, $advanceDoc, [
            'amount' => '70', 'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '70']],
        ]);
        $expenses = app(TreasuryExpenseService::class);
        $draft = $expenses->createDraft($project, $creator, [
            'source_wallet_id' => (string) $walletB->id, 'destination_party_id' => (string) $payee->id,
            'amount' => '70', 'transaction_date' => '2026-10-02',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '70']],
        ]);
        $expenses->submit($project, $creator, $draft);

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', 'treasury:concurrency-test-approve-expense', (string) $project->id, (string) $approver->id, (string) $draft->id, '--hold=3'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', 'treasury:concurrency-test-advance', 'settle', (string) $project->id, (string) $approver->id, (string) $advanceDoc->id], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        $output = 'A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput();
        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one allocation may be made. ' . $output);
        $this->assertSame(7000, $expenses->netAllocationCents('contract_expense', (string) $cost->id), $output);
    }

    /**
     * A posted advance of 100 paid from wallet A to the creator's party, a
     * second funded wallet B, and a contract cost of 100.
     *
     * @return array{0: Project, 1: User, 2: User, 3: TreasuryWallet, 4: ContractExpense, 5: TreasuryFinancialDocument, 6: TreasuryFinancialParty}
     */
    private function advanceFixture(): array
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $creator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $approver = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $tenant->id, 'pm_id' => $creator->id, 'created_by' => $creator->id,
        ]));
        $investor = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'investor', 'name' => 'A']);
        $holder = TreasuryFinancialParty::on('mysql')->create([
            'tenant_id' => $tenant->id, 'party_type' => 'employee', 'name' => 'Z', 'linked_user_id' => $creator->id,
        ]);
        $payee = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'labour', 'name' => 'D']);
        $wallets = [];
        foreach (['A', 'B'] as $name) {
            $wallets[] = TreasuryWallet::on('mysql')->create([
                'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'employee_cash', 'name' => $name, 'custodian_party_id' => $holder->id,
            ]);
        }
        $posting = app(TreasuryPostingService::class);
        foreach ($wallets as $wallet) {
            $posting->declareFunding($project, $creator, [
                'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
                'destination_wallet_id' => (string) $wallet->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
            ], true);
        }
        $contract = Contract::on('mysql')->create(Contract::factory()->raw([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'created_by' => $creator->id,
        ]));
        $cost = ContractExpense::on('mysql')->create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '100', 'category' => 'labor', 'description' => 'Cost',
        ]);
        $advances = app(TreasuryAdvanceService::class);
        $draft = $advances->createDraft($project, $creator, [
            'source_wallet_id' => (string) $wallets[0]->id, 'destination_party_id' => (string) $holder->id,
            'amount' => '100', 'transaction_date' => '2026-10-02',
        ]);
        $advances->submit($project, $creator, $draft);
        $advanceDoc = $advances->approve($project, $approver, $draft->refresh());

        return [$project, $creator, $approver, $wallets[1], $cost, $advanceDoc, $payee];
    }

    /**
     * A funding route of $amount into a site wallet, an actor holding
     * treasury.manage_routes for every project, and a transit wallet.
     *
     * @return array{0: Project, 1: User, 2: TreasuryPaymentRoute, 3: TreasuryWallet, 4: TreasuryWallet}
     */
    private function routeFixture(string $amount): array
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $role = Role::on('mysql')->firstOrCreate(['name' => 'gap094-route-race'], ['scope' => 'system']);
        foreach (['treasury.manage_routes', 'treasury.all_projects'] as $code) {
            $permission = Permission::on('mysql')->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'treasury', 'action' => substr($code, strlen('treasury.'))]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $actor->roles()->syncWithoutDetaching([$role->id]);
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $tenant->id, 'pm_id' => $actor->id, 'created_by' => $actor->id,
        ]));
        $investor = TreasuryFinancialParty::on('mysql')->create(['tenant_id' => $tenant->id, 'party_type' => 'investor', 'name' => 'A']);
        $transit = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'intermediary_control', 'name' => 'Transit',
        ]);
        $site = TreasuryWallet::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'wallet_type' => 'employee_cash', 'name' => 'Site',
        ]);
        $route = app(TreasuryRouteService::class)->createRoute($project, $actor, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $site->id, 'amount' => $amount, 'transaction_date' => '2026-10-01',
        ]);

        return [$project, $actor, $route, $transit, $site];
    }

    /**
     * @return array{0: Process, 1: Process}
     */
    private function raceLegs(Project $project, User $actor, TreasuryPaymentRoute $route, string $from, string $to, string $amount): array
    {
        $php = (new PhpExecutableFinder())->find();
        $args = fn (string $hold): array => [
            $php, 'artisan', 'treasury:concurrency-test-route-leg',
            (string) $project->id, (string) $actor->id, (string) $route->id, $from, $to, $amount, '--hold=' . $hold,
        ];
        $procA = new Process($args('3'), base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process($args('0'), base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        usleep(1_500_000);
        $procB->start();
        $procA->wait();
        $procB->wait();

        return [$procA, $procB];
    }
}
