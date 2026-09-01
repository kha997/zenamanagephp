<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Http\Controllers\Api\OpportunityController;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-048 CONCURRENCY-3 test-support command: invokes the REAL
 * Api\OpportunityController::update() from a genuinely separate OS process,
 * optionally with the --fail-mapper-write test-only failure-injection seam
 * active (GAP048_SIMULATE_MAPPER_FAILURE), to prove the legacy scalar write
 * rolls back together with a failed canonical reconciliation step.
 */
class OpportunityConcurrencyTestUpdateCategory extends Command
{
    protected $signature = 'opportunity:concurrency-test-update-category
        {opportunity_id} {category} {actor_id}
        {--fail-mapper-write : Activate the CONCURRENCY-3 failure-injection seam for this call only}';

    protected $hidden = true;

    public function handle(OpportunityController $controller): int
    {
        $opportunity = Opportunity::on('mysql')->findOrFail($this->argument('opportunity_id'));
        $actor = User::on('mysql')->findOrFail($this->argument('actor_id'));
        Auth::login($actor);

        if ($this->option('fail-mapper-write')) {
            putenv('GAP048_SIMULATE_MAPPER_FAILURE=1');
            $_SERVER['GAP048_SIMULATE_MAPPER_FAILURE'] = '1';
        }

        $request = Request::create('/api/opportunities/' . $opportunity->id, 'PUT', [
            'service_category' => $this->argument('category'),
        ]);
        $request->attributes->set('tenant_id', (string) $actor->tenant_id);

        try {
            $response = $controller->update($request, (string) $opportunity->id);
            $status = $response->getStatusCode();
        } catch (\Throwable $e) {
            $status = 500;
            $this->line('EXCEPTION ' . $e->getMessage());
        } finally {
            if ($this->option('fail-mapper-write')) {
                putenv('GAP048_SIMULATE_MAPPER_FAILURE');
                unset($_SERVER['GAP048_SIMULATE_MAPPER_FAILURE']);
            }
        }

        if ($status >= 500) {
            $this->line('CONFLICT status=' . $status);

            return self::FAILURE;
        }

        $this->line('OK status=' . $status);

        return self::SUCCESS;
    }
}
