<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Project;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-064): runs TreasuryPostingService::transfer() from
 * a genuinely separate OS process so the concurrency test can race two real
 * MySQL connections. --hold keeps the outer transaction (and its locks) open
 * after the transfer, widening the race window deterministically.
 */
class TreasuryConcurrencyTestTransfer extends Command
{
    protected $signature = 'treasury:concurrency-test-transfer {project_id} {actor_id} {source_wallet_id} {destination_wallet_id} {amount} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryPostingService $posting): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        DB::beginTransaction();
        try {
            $document = $posting->transfer($project, $actor, [
                'source_wallet_id' => (string) $this->argument('source_wallet_id'),
                'destination_wallet_id' => (string) $this->argument('destination_wallet_id'),
                'amount' => (string) $this->argument('amount'),
                'transaction_date' => now()->toDateString(),
            ]);
            usleep((int) ((float) $this->option('hold') * 1_000_000));
            DB::commit();
            $this->line('OK ' . $document->id);

            return self::SUCCESS;
        } catch (TreasuryRuleViolation $e) {
            DB::rollBack();
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
