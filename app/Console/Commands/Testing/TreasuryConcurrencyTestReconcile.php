<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Project;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-067): runs TreasuryReconciliationService::reconcile()
 * from a separate OS process so the concurrency test can race two real MySQL
 * connections for the same ledger entry ("at most one active apply", v17 §11
 * class 4). --hold keeps the outer transaction (and its locks) open after the
 * reconciliation.
 */
class TreasuryConcurrencyTestReconcile extends Command
{
    protected $signature = 'treasury:concurrency-test-reconcile {project_id} {actor_id} {wallet_id} {ledger_entry_id} {reference} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryReconciliationService $reconciliation): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        DB::beginTransaction();
        try {
            /** @var TreasuryWallet $wallet */
            $wallet = TreasuryWallet::query()->withoutGlobalScopes()->findOrFail($this->argument('wallet_id'));
            $rec = $reconciliation->reconcile($project, $actor, $wallet, [
                'reconciliation_type' => 'bank_statement',
                'external_reference' => (string) $this->argument('reference'),
                'reconciled_at' => now()->toDateString(),
                'ledger_entry_ids' => [(string) $this->argument('ledger_entry_id')],
            ]);
            usleep((int) ((float) $this->option('hold') * 1_000_000));
            DB::commit();
            $this->line('OK ' . $rec->id);

            return self::SUCCESS;
        } catch (TreasuryRuleViolation $e) {
            DB::rollBack();
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
