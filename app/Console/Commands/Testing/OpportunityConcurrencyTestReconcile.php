<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Crm\OpportunityServiceLineClassificationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * GAP-048 CONCURRENCY-1/2 test-support command: invokes the REAL
 * OpportunityServiceLineClassificationService::reconcile() (reconciling
 * toward the empty set by default) from a genuinely separate OS process.
 */
class OpportunityConcurrencyTestReconcile extends Command
{
    protected $signature = 'opportunity:concurrency-test-reconcile {opportunity_id} {actor_id} {lines?*}';

    protected $hidden = true;

    public function handle(OpportunityServiceLineClassificationService $service): int
    {
        $opportunity = Opportunity::on('mysql')->findOrFail($this->argument('opportunity_id'));
        $actor = User::on('mysql')->findOrFail($this->argument('actor_id'));

        try {
            $service->reconcile($actor, $opportunity, $this->argument('lines'));
            $this->line('OK reconciled');

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->line('CONFLICT ' . json_encode($e->errors()));

            return self::FAILURE;
        }
    }
}
