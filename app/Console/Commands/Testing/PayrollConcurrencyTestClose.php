<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\PayrollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-121 S-1): runs PayrollService::close() from a genuinely separate OS process so
 * tests/Feature/Concurrency/MonthCloseConcurrencyTest can race two real MySQL connections. --hold keeps the outer
 * transaction (and its locks) open after the call, widening the race window deterministically; without --hold the
 * call runs with no outer transaction, as in a web request. Prints "OK ..." or "REFUSED <reason>" (exit 1).
 */
class PayrollConcurrencyTestClose extends Command
{
    protected $signature = 'payroll:concurrency-test-close {period_id} {actor_id} {--hold=0}';

    protected $hidden = true;

    public function handle(PayrollService $payroll): int
    {
        /** @var PayrollPeriod $period */
        $period = PayrollPeriod::query()->withoutGlobalScopes()->findOrFail($this->argument('period_id'));
        /** @var User $actor */
        $actor = User::query()->findOrFail($this->argument('actor_id'));

        // Only the holding process (--hold > 0) wraps the call in an outer transaction to keep its locks open. The racing
        // process runs the service exactly as a web request does: an outer transaction would take its read snapshot
        // during the service's pre-checks, before the lock wait, and hide what the holder committed meanwhile.
        $hold = (float) $this->option('hold');
        if ($hold > 0) {
            DB::beginTransaction();
        }

        // Timestamps let the test prove B reached the contested call while A still held its locks.
        $this->line('START ' . sprintf('%.6f', microtime(true)));

        try {
            $result = (string) $payroll->close($actor, $period)->id;
            if ($hold > 0) {
                $this->line('LOCKED');
                usleep((int) ($hold * 1_000_000));
                $this->line('RELEASE ' . sprintf('%.6f', microtime(true)));
                DB::commit();
            }
            $this->line('OK ' . $result);

            return self::SUCCESS;
        } catch (PayrollRuleViolation $e) {
            if ($hold > 0) {
                DB::rollBack();
            }
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
