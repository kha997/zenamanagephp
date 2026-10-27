<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Project;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\User;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-094): runs TreasuryRouteService::recordLeg()
 * from a separate OS process so the concurrency test can race two real MySQL
 * connections on one route or one transit wallet (v17 §4.2 custody, §11
 * classes 0/5/6). A from-wallet of "-" means money entering from outside.
 * --hold keeps the outer transaction (and its locks) open after the leg.
 */
class TreasuryConcurrencyTestRouteLeg extends Command
{
    protected $signature = 'treasury:concurrency-test-route-leg {project_id} {actor_id} {route_id} {from_wallet_id} {to_wallet_id} {amount} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryRouteService $routes): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));
        $from = (string) $this->argument('from_wallet_id');

        DB::beginTransaction();

        try {
            /** @var TreasuryPaymentRoute $route */
            $route = TreasuryPaymentRoute::query()->withoutGlobalScopes()->findOrFail($this->argument('route_id'));
            $leg = $routes->recordLeg($project, $actor, $route, [
                'from_wallet_id' => $from === '-' ? null : $from,
                'to_wallet_id' => (string) $this->argument('to_wallet_id'),
                'amount' => (string) $this->argument('amount'),
                'occurred_at' => now()->toDateString(),
            ]);
            usleep((int) ((float) $this->option('hold') * 1_000_000));
            DB::commit();
            $this->line('OK ' . $leg->id);

            return self::SUCCESS;
        } catch (TreasuryRuleViolation $e) {
            DB::rollBack();
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
