<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class StartEmailQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:queue-worker 
                            {--connection=redis : Queue connection to use}
                            {--queue=emails : Queue name to process}
                            {--timeout=60 : Timeout for job processing}
                            {--tries=3 : Number of retry attempts}
                            {--max-jobs=1000 : Maximum jobs to process before restarting}
                            {--max-time=3600 : Maximum time to run before restarting}
                            {--sleep=3 : Sleep time when no jobs available}
                            {--daemon : Run as daemon process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start email queue worker with production-optimized settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $queue = $this->option('queue');
        $timeout = $this->option('timeout');
        $tries = $this->option('tries');
        $maxJobs = $this->option('max-jobs');
        $maxTime = $this->option('max-time');
        $sleep = $this->option('sleep');
        $daemon = $this->option('daemon');

        $this->info('Starting email queue worker...');
        $this->info("Connection: {$connection}");
        $this->info("Queue: {$queue}");
        $this->info("Timeout: {$timeout}s");
        $this->info("Max Jobs: {$maxJobs}");
        $this->info("Max Time: {$maxTime}s");

        // Build queue worker command
        $command = [
            'php', 'artisan', 'queue:work',
            "--connection={$connection}",
            "--queue={$queue}",
            "--timeout={$timeout}",
            "--tries={$tries}",
            "--max-jobs={$maxJobs}",
            "--max-time={$maxTime}",
            "--sleep={$sleep}",
        ];

        if ($daemon) {
            $command[] = '--daemon';
        }

        // Add verbose output
        $command[] = '--verbose';

        $this->info('Command: ' . implode(' ', $command));

        try {
            // Start the queue worker
            $process = Process::start($command);

            $this->info('Email queue worker started successfully!');
            $this->info('Process ID: ' . $process->id());

            // Log the start
            Log::info('Email queue worker started', [
                'connection' => $connection,
                'queue' => $queue,
                'timeout' => $timeout,
                'tries' => $tries,
                'max_jobs' => $maxJobs,
                'max_time' => $maxTime,
                'pid' => $process->id(),
            ]);

            // Wait for process to complete (if not daemon)
            if (!$daemon) {
                $process->wait();
            }

        } catch (\Exception $e) {
            $this->error('Failed to start email queue worker: ' . $e->getMessage());
            Log::error('Failed to start email queue worker', [
                'error' => $e->getMessage(),
                'command' => implode(' ', $command),
            ]);
            return 1;
        }

        return 0;
    }
}