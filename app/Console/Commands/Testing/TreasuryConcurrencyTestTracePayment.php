<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\User;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-095): runs
 * TreasuryRouteService::createContractPaymentRoute() from a separate OS
 * process so the concurrency test can race two real MySQL connections on one
 * contract payment (allocation cap under the class-1 lock, v17 §4.1 Case A).
 * --hold keeps the outer transaction (and its locks) open after the route.
 */
class TreasuryConcurrencyTestTracePayment extends Command
{
    protected $signature = 'treasury:concurrency-test-trace-payment {project_id} {actor_id} {contract_payment_id} {wallet_id} {amount} {--hold=0}';

    protected $hidden = true;

    public function handle(TreasuryRouteService $routes): int
    {
        /** @var Project $project */
        $project = Project::query()->withoutGlobalScopes()->findOrFail($this->argument('project_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        DB::beginTransaction();

        try {
            /** @var ContractPayment $payment */
            $payment = ContractPayment::query()->withoutGlobalScopes()->findOrFail($this->argument('contract_payment_id'));
            $route = $routes->createContractPaymentRoute($project, $actor, $payment, [
                'expected_destination_wallet_id' => (string) $this->argument('wallet_id'),
                'amount' => (string) $this->argument('amount'),
            ], confirmDuplicate: true);
            usleep((int) ((float) $this->option('hold') * 1_000_000));
            DB::commit();
            $this->line('OK ' . $route->id);

            return self::SUCCESS;
        } catch (TreasuryRuleViolation $e) {
            DB::rollBack();
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
