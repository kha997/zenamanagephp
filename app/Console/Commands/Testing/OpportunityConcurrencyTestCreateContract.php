<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Http\Controllers\Api\OpportunityController;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-048 CONCURRENCY-4 test-support command: invokes the REAL
 * OpportunityController::createContract() from a genuinely separate OS
 * process, so the concurrency test can prove real MySQL row-locking
 * behavior instead of simulating it with sequential in-process calls.
 */
class OpportunityConcurrencyTestCreateContract extends Command
{
    protected $signature = 'opportunity:concurrency-test-create-contract {opportunity_id} {actor_id} {tenant_id} {--start-marker=}';

    protected $hidden = true;

    public function handle(OpportunityController $controller): int
    {
        $actor = User::on('mysql')->findOrFail($this->argument('actor_id'));
        Auth::login($actor);

        $request = new Request();
        $request->attributes->set('tenant_id', (string) $this->argument('tenant_id'));

        // Test-only synchronization signal: this artisan command itself
        // (not production code) touches a marker file the instant Laravel
        // framework bootstrap is complete and it is about to invoke the
        // real controller method, so the racing PHPUnit process can start
        // its concurrent probe from (as close as possible to) the same
        // starting line as this process's own critical section, instead
        // of guessing a fixed head-start duration to skip over PHP/Laravel
        // bootstrap time.
        $marker = $this->option('start-marker');
        if (is_string($marker) && $marker !== '') {
            touch($marker);
        }

        $response = $controller->createContract($request, (string) $this->argument('opportunity_id'));
        $status = $response->getStatusCode();

        $exists = Contract::on('mysql')
            ->where('source_opportunity_id', (string) $this->argument('opportunity_id'))
            ->exists();

        if ($status === 201 && $exists) {
            $this->line('OK CONTRACT_CREATED');

            return self::SUCCESS;
        }

        $this->line('CONFLICT status=' . $status . ' body=' . $response->getContent());

        return self::FAILURE;
    }
}
