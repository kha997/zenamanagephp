<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Crm\OpportunityStageTransitionService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * GAP-048 CONCURRENCY-1 test-support command: invokes the REAL
 * OpportunityStageTransitionService::transition() from a genuinely separate
 * OS process, so the concurrency test can prove real MySQL row-locking
 * behavior instead of simulating it with sequential in-process calls.
 */
class OpportunityConcurrencyTestTransition extends Command
{
    protected $signature = 'opportunity:concurrency-test-transition {opportunity_id} {actor_id} {to_stage}';

    protected $hidden = true;

    public function handle(OpportunityStageTransitionService $service): int
    {
        $opportunity = Opportunity::on('mysql')->findOrFail($this->argument('opportunity_id'));
        $actor = User::on('mysql')->findOrFail($this->argument('actor_id'));

        try {
            $service->transition($actor, $opportunity, $this->argument('to_stage'), null);
            $this->line('OK transitioned to ' . $this->argument('to_stage'));

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->line('CONFLICT ' . json_encode($e->errors()));

            return self::FAILURE;
        }
    }
}
