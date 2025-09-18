<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class StartProductionWorkers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workers:start-production 
                            {--daemon : Run as daemon process}
                            {--workers=4 : Number of workers per queue}
                            {--timeout=60 : Job timeout in seconds}
                            {--tries=3 : Number of retry attempts}
                            {--max-jobs=1000 : Max jobs before restart}
                            {--max-time=3600 : Max time before restart}
                            {--sleep=3 : Sleep time when no jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start production queue workers with optimal settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Production Queue Workers');
        $this->newLine();

        $daemon = $this->option('daemon');
        $workers = $this->option('workers');
        $timeout = $this->option('timeout');
        $tries = $this->option('tries');
        $maxJobs = $this->option('max-jobs');
        $maxTime = $this->option('max-time');
        $sleep = $this->option('sleep');

        $this->info("Configuration:");
        $this->table(['Setting', 'Value'], [
            ['Mode', $daemon ? 'Daemon' : 'Foreground'],
            ['Workers per Queue', $workers],
            ['Timeout', $timeout . 's'],
            ['Max Retries', $tries],
            ['Max Jobs', $maxJobs],
            ['Max Time', $maxTime . 's'],
            ['Sleep Time', $sleep . 's'],
        ]);

        if (!$this->confirm('Start workers with these settings?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            $this->startWorkers($daemon, $workers, $timeout, $tries, $maxJobs, $maxTime, $sleep);
            $this->info('✅ Production workers started successfully!');
            
            if ($daemon) {
                $this->info('Workers are running in the background.');
                $this->info('Use "php artisan workers:status" to check status.');
                $this->info('Use "php artisan workers:stop" to stop workers.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to start workers: ' . $e->getMessage());
            Log::error('Failed to start production workers', [
                'error' => $e->getMessage(),
                'settings' => compact('daemon', 'workers', 'timeout', 'tries', 'maxJobs', 'maxTime', 'sleep'),
            ]);
            return 1;
        }
    }

    /**
     * Start queue workers
     */
    private function startWorkers(bool $daemon, int $workers, int $timeout, int $tries, int $maxJobs, int $maxTime, int $sleep): void
    {
        $queues = [
            'emails-high' => 'High Priority Emails',
            'emails-medium' => 'Medium Priority Emails', 
            'emails-low' => 'Low Priority Emails',
            'emails-welcome' => 'Welcome Emails',
        ];

        $processes = [];

        foreach ($queues as $queue => $description) {
            $this->info("Starting {$workers} workers for {$description}...");
            
            for ($i = 1; $i <= $workers; $i++) {
                $command = [
                    'php', 'artisan', 'queue:work',
                    'redis',
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

                $process = Process::start($command);
                $processes[] = [
                    'process' => $process,
                    'queue' => $queue,
                    'worker' => $i,
                    'pid' => $process->id(),
                ];

                $this->line("  Worker {$i}: PID {$process->id()}");
            }
        }

        // Save process information
        $this->saveProcessInfo($processes);

        Log::info('Production workers started', [
            'queues' => array_keys($queues),
            'workers_per_queue' => $workers,
            'total_workers' => count($processes),
            'daemon' => $daemon,
        ]);
    }

    /**
     * Save process information
     */
    private function saveProcessInfo(array $processes): void
    {
        $processFile = storage_path('app/workers.json');
        $data = [
            'started_at' => now()->toISOString(),
            'processes' => $processes,
        ];

        file_put_contents($processFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}
