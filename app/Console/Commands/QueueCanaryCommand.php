<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\QueueCanaryJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GAP-049: proves a real async queue worker is alive and processing jobs.
 * Deliberately fails when QUEUE_CONNECTION=sync so synchronous execution
 * can never falsely satisfy this canary.
 */
class QueueCanaryCommand extends Command
{
    protected $signature = 'deploy:queue-canary {--timeout=30 : Seconds to wait for the probe job to complete}';

    protected $description = 'Dispatch a unique probe job and poll for a real worker to complete it (GAP-049 queue canary)';

    public function handle(): int
    {
        if (config('queue.default') === 'sync') {
            $this->error('QUEUE_CONNECTION=sync cannot produce a valid queue canary result — refusing to run.');
            return 2;
        }

        $probeId = (string) Str::uuid();
        $job = new QueueCanaryJob($probeId);
        QueueCanaryJob::dispatch($probeId);

        $timeout = (int) $this->option('timeout');
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            if (Cache::get($job->markerKey()) === true) {
                $this->info("Queue canary completed by a real worker (probe {$probeId}).");
                Cache::forget($job->markerKey());
                return 0;
            }
            usleep(200_000);
        }

        $this->error("Queue canary timed out after {$timeout}s — no worker processed probe {$probeId}.");
        return 1;
    }
}
