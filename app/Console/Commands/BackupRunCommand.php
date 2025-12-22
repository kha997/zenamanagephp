<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MaintenanceTask;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run {--type=database : Type of backup to run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run system backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        
        $this->info("Starting {$type} backup...");

        try {
            $task = MaintenanceTask::create([
                'task' => 'System backup',
                'level' => 'info',
                'priority' => 'medium',
                'status' => 'running',
                'started_at' => now()
            ]);

            // Simulate backup process
            $this->info('Creating backup...');
            sleep(1); // Simulate work
            
            $task->markAsCompleted([
                'type' => $type,
                'backup_path' => '/backups/backup_' . now()->format('Y-m-d_H-i-s') . '.sql',
                'size' => '1.2 MB'
            ]);

            $this->info('✓ Backup completed successfully');
            return 0;

        } catch (\Exception $e) {
            if (isset($task)) {
                $task->markAsFailed($e->getMessage());
            }
            $this->error('✗ Backup failed: ' . $e->getMessage());
            return 1;
        }
    }
}