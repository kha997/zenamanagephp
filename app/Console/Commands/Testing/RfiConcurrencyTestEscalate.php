<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Rfi;
use App\Services\RfiEscalationService;
use Illuminate\Console\Command;

/**
 * Test-support command: invokes RfiEscalationService::escalate() from a genuinely
 * separate OS process, so concurrency tests can prove real row-locking behavior
 * instead of simulating it with sequential calls in one PHPUnit process.
 */
class RfiConcurrencyTestEscalate extends Command
{
    protected $signature = 'rfi:concurrency-test-escalate {rfi_id} {target_id} {escalator_id} {reason}';

    protected $hidden = true;

    public function handle(RfiEscalationService $service): int
    {
        $rfi = Rfi::on('mysql')->findOrFail($this->argument('rfi_id'));

        try {
            $escalation = $service->escalate($rfi, $this->argument('target_id'), $this->argument('escalator_id'), $this->argument('reason'));
            $this->line('OK ' . $escalation->id);
            return self::SUCCESS;
        } catch (RfiEscalationConflictException $e) {
            $this->line('CONFLICT ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
