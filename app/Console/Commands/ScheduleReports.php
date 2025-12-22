<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledReports;
use Illuminate\Console\Command;

class ScheduleReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled reports and send them via email';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Processing scheduled reports...');
        
        try {
            ProcessScheduledReports::dispatch();
            $this->info('Scheduled reports job dispatched successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process scheduled reports: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
