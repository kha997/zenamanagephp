<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

class CleanMockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mock:clean {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up mock data from database after testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Starting Mock Data Cleanup Process...');

        if (!$this->option('force')) {
            $this->warn('⚠️  This will delete all mock/test data from the database!');
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('❌ Operation cancelled.');
                return;
            }
        }

        try {
            $this->info('📊 Counting existing data...');
            
            $taskCount = Task::where('name', 'LIKE', '%Test%')
                ->orWhere('name', 'LIKE', '%Mock%')
                ->orWhere('description', 'LIKE', '%test%')
                ->count();
                
            $projectCount = Project::where('name', 'LIKE', '%Test%')
                ->orWhere('name', 'LIKE', '%Mock%')
                ->orWhere('description', 'LIKE', '%test%')
                ->count();

            $this->info("Found {$taskCount} test tasks and {$projectCount} test projects to delete.");

            if ($taskCount > 0 || $projectCount > 0) {
                $this->info('🗑️  Deleting test tasks...');
                Task::where('name', 'LIKE', '%Test%')
                    ->orWhere('name', 'LIKE', '%Mock%')
                    ->orWhere('description', 'LIKE', '%test%')
                    ->delete();

                $this->info('🗑️  Deleting test projects...');
                Project::where('name', 'LIKE', '%Test%')
                    ->orWhere('name', 'LIKE', '%Mock%')
                    ->orWhere('description', 'LIKE', '%test%')
                    ->delete();

                $this->info('✅ Mock data cleanup completed successfully!');
                $this->info('📊 Database is now clean and ready for production.');
            } else {
                $this->info('ℹ️  No mock data found to clean up.');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error cleaning mock data: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}