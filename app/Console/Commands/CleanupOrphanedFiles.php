<?php

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Illuminate\Console\Command;

/**
 * CleanupOrphanedFiles Command
 * 
 * Removes orphaned files from storage that are not referenced in the database.
 * Should be run daily via scheduler.
 */
class CleanupOrphanedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:cleanup-orphaned 
                            {--days=30 : Files not referenced in N days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned files from storage';

    /**
     * Execute the console command.
     */
    public function handle(FileStorageService $fileStorageService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("Starting orphaned files cleanup (unreferenced for {$days} days)...");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }
        
        $result = $fileStorageService->cleanupOrphanedFiles($days, $dryRun);
        
        $this->info("Found " . count($result['files']) . " orphaned files");
        
        if ($dryRun) {
            $this->warn("Would delete " . count($result['files']) . " files:");
            foreach ($result['files'] as $file) {
                $this->line("  - {$file}");
            }
        } else {
            $this->info("Successfully deleted {$result['deleted']} orphaned files");
        }
        
        return 0;
    }
}

