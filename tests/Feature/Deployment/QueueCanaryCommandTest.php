<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Jobs\QueueCanaryJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueCanaryCommandTest extends TestCase
{
    public function test_rejects_sync_queue_connection_without_dispatching(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake();

        $exitCode = $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        $this->assertSame(2, $exitCode);
        Queue::assertNothingPushed();
    }

    public function test_dispatches_probe_job_on_non_sync_connection(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        Queue::assertPushed(QueueCanaryJob::class);
    }

    public function test_times_out_when_no_worker_processes_the_job(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake(); // fakes dispatch so nothing ever actually processes it — simulates "no worker running"

        $exitCode = $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        $this->assertSame(1, $exitCode);
    }

    public function test_succeeds_when_job_handle_writes_completion_marker(): void
    {
        $probeId = 'test-probe-marker-check';
        $job = new QueueCanaryJob($probeId);
        $job->handle();

        $this->assertTrue(Cache::get("gap049-queue-canary-{$probeId}") === true);
    }
}
