<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-support command (GAP-121 S-1): runs TimeEntryService::addManual() (a non-project meeting entry) from a genuinely separate OS process so
 * tests/Feature/Concurrency/MonthCloseConcurrencyTest can race two real MySQL connections. --hold keeps the outer
 * transaction (and its locks) open after the call, widening the race window deterministically; without --hold the
 * call runs with no outer transaction, as in a web request. Prints "OK ..." or "REFUSED <reason>" (exit 1).
 */
class TimeConcurrencyTestAdd extends Command
{
    protected $signature = 'time:concurrency-test-add {user_id} {work_date} {minutes} {--hold=0}';

    protected $hidden = true;

    public function handle(TimeEntryService $entries): int
    {
        /** @var User $user */
        $user = User::query()->findOrFail($this->argument('user_id'));

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
            $result = (string) $entries->addManual($user, [
                'category' => TimeEntry::CATEGORY_MEETING,
                'work_date' => (string) $this->argument('work_date'),
                'minutes' => (int) $this->argument('minutes'),
                'note' => 'GAP-121 concurrency test',
            ])->id;
            if ($hold > 0) {
                $this->line('LOCKED');
                usleep((int) ($hold * 1_000_000));
                $this->line('RELEASE ' . sprintf('%.6f', microtime(true)));
                DB::commit();
            }
            $this->line('OK ' . $result);

            return self::SUCCESS;
        } catch (TimeRuleViolation $e) {
            if ($hold > 0) {
                DB::rollBack();
            }
            $this->line('REFUSED ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
