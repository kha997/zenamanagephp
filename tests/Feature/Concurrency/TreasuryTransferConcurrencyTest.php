<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPostingService;
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
                foreach (['audit_logs', 'treasury_cost_settlement_allocations', 'treasury_expense_approvals', 'treasury_ledger_entries',
                    'treasury_financial_documents', 'contract_expenses', 'contracts', 'treasury_wallets',
                    'treasury_financial_parties', 'projects', 'users', 'tenants'] as $table) {
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
}
