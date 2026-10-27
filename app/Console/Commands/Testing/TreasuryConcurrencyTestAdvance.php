<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-096): approves the open settlement request of an
 * advance (`settle`) or records a cash return (`return`) from a separate OS
 * process, so the concurrency test can race two real MySQL connections on one
 * advance's outstanding amount or one cost's §6.3 cap. --hold keeps the outer
 * transaction (and its locks) open afterwards.
 */
class TreasuryConcurrencyTestAdvance extends Command
{
    protected $signature = 'treasury:concurrency-test-advance {action} {project_id} {actor_id} {advance_document_id} {wallet_id?} {amount?} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryAdvanceService $advances): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        DB::beginTransaction();

        try {
            /** @var TreasuryFinancialDocument $document */
            $document = TreasuryFinancialDocument::query()->withoutGlobalScopes()->findOrFail($this->argument('advance_document_id'));
            if ($this->argument('action') === 'return') {
                $advances->recordReturn($project, $actor, $document, [
                    'destination_wallet_id' => (string) $this->argument('wallet_id'),
                    'amount' => (string) $this->argument('amount'),
                    'transaction_date' => '2026-10-05',
                ]);
            } else {
                $advances->approveSettlementRequest($project, $actor, $document);
            }
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
