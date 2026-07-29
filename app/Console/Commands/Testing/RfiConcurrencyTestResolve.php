<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Exceptions\RfiEscalationConflictException;
use App\Exceptions\RfiEscalationNotFoundException;
use App\Models\Rfi;
use App\Services\RfiEscalationService;
use Illuminate\Console\Command;

/**
 * Test-support command: invokes RfiEscalationService::resolveEscalation() from a
 * genuinely separate OS process, so concurrency tests can prove real row-locking
 * behavior instead of simulating it with sequential calls in one PHPUnit process.
 */
class RfiConcurrencyTestResolve extends Command
{
    protected $signature = 'rfi:concurrency-test-resolve {rfi_id} {resolver_id} {resolution}';

    protected $hidden = true;

    public function handle(RfiEscalationService $service): int
    {
        $rfi = Rfi::on('mysql')->findOrFail($this->argument('rfi_id'));

        try {
            $escalation = $service->resolveEscalation($rfi, $this->argument('resolver_id'), $this->argument('resolution'));
            $this->line('OK ' . $escalation->id);
            return self::SUCCESS;
        } catch (RfiEscalationConflictException|RfiEscalationNotFoundException $e) {
            $this->line('CONFLICT ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
