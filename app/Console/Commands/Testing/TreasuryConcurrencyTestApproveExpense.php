<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\User;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-066): runs TreasuryExpenseService::approve() from
 * a separate OS process so the concurrency test can race two real MySQL
 * connections for the same cost record's §6.3 cap. --hold keeps the outer
 * transaction (and its locks) open after the approval.
 */
class TreasuryConcurrencyTestApproveExpense extends Command
{
    protected $signature = 'treasury:concurrency-test-approve-expense {project_id} {actor_id} {expense_id} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryExpenseService $expenses): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        DB::beginTransaction();
        try {
            /** @var TreasuryFinancialDocument $expense */
            $expense = TreasuryFinancialDocument::query()->withoutGlobalScopes()->findOrFail($this->argument('expense_id'));
            $expenses->approve($project, $actor, $expense);
            usleep((int) ((float) $this->option('hold') * 1_000_000));
            DB::commit();
            $this->line('OK ' . $expense->id);

            return self::SUCCESS;
        } catch (TreasuryRuleViolation $e) {
            DB::rollBack();
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
