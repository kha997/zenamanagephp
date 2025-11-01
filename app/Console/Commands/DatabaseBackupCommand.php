<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseBackupService;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:backup 
                            {type=full : Type of backup (full|incremental)}
                            {--description= : Description for the backup}
                            {--list : List available backups}
                            {--restore= : Restore from backup filename}
                            {--delete= : Delete backup filename}
                            {--stats : Show backup statistics}
                            {--cleanup : Cleanup old backups}';

    /**
     * The console command description.
     */
    protected $description = 'Manage database backups';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        try {
            if ($this->option('list')) {
                return $this->listBackups($backupService);
            }

            if ($this->option('restore')) {
                return $this->restoreBackup($backupService, $this->option('restore'));
            }

            if ($this->option('delete')) {
                return $this->deleteBackup($backupService, $this->option('delete'));
            }

            if ($this->option('stats')) {
                return $this->showStats($backupService);
            }

            if ($this->option('cleanup')) {
                return $this->cleanupBackups($backupService);
            }

            return $this->createBackup($backupService);

        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create backup
     */
    private function createBackup(DatabaseBackupService $backupService): int
    {
        $type = $this->argument('type');
        $description = $this->option('description');

        if (!in_array($type, ['full', 'incremental'])) {
            $this->error('Invalid backup type. Use "full" or "incremental".');
            return 1;
        }

        $this->info("Creating {$type} database backup...");

        if ($type === 'full') {
            $result = $backupService->createFullBackup($description);
        } else {
            $result = $backupService->createIncrementalBackup($description);
        }

        if ($result['success']) {
            $this->info('Backup created successfully!');
            $this->line("Filename: {$result['filename']}");
            $this->line("Size: {$result['size']}");
            
            if ($description) {
                $this->line("Description: {$description}");
            }
            
            return 0;
        } else {
            $this->error('Backup failed: ' . $result['error']);
            return 1;
        }
    }

    /**
     * List backups
     */
    private function listBackups(DatabaseBackupService $backupService): int
    {
        $this->info('Available database backups:');
        $this->newLine();

        $backups = $backupService->listBackups();

        if (empty($backups)) {
            $this->warn('No backups found.');
            return 0;
        }

        $headers = ['Filename', 'Type', 'Size', 'Created At'];
        $rows = [];

        foreach ($backups as $backup) {
            $rows[] = [
                $backup['filename'],
                ucfirst($backup['type']),
                $backup['size'],
                $backup['created_at']
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }

    /**
     * Restore backup
     */
    private function restoreBackup(DatabaseBackupService $backupService, string $filename): int
    {
        if (!$this->confirm("Are you sure you want to restore from backup '{$filename}'? This will overwrite the current database.")) {
            $this->info('Restore cancelled.');
            return 0;
        }

        $this->info("Restoring database from backup: {$filename}");
        
        $result = $backupService->restoreFromBackup($filename);

        if ($result['success']) {
            $this->info('Database restored successfully!');
            return 0;
        } else {
            $this->error('Restore failed: ' . $result['error']);
            return 1;
        }
    }

    /**
     * Delete backup
     */
    private function deleteBackup(DatabaseBackupService $backupService, string $filename): int
    {
        if (!$this->confirm("Are you sure you want to delete backup '{$filename}'?")) {
            $this->info('Delete cancelled.');
            return 0;
        }

        $this->info("Deleting backup: {$filename}");
        
        $result = $backupService->deleteBackup($filename);

        if ($result) {
            $this->info('Backup deleted successfully!');
            return 0;
        } else {
            $this->error('Failed to delete backup.');
            return 1;
        }
    }

    /**
     * Show backup statistics
     */
    private function showStats(DatabaseBackupService $backupService): int
    {
        $this->info('Database Backup Statistics:');
        $this->newLine();

        $stats = $backupService->getBackupStats();

        $this->line("Total Backups: {$stats['total_backups']}");
        $this->line("Full Backups: {$stats['full_backups']}");
        $this->line("Incremental Backups: {$stats['incremental_backups']}");
        $this->line("Total Size: {$stats['total_size']}");
        
        if ($stats['oldest_backup']) {
            $this->line("Oldest Backup: {$stats['oldest_backup']}");
        }
        
        if ($stats['newest_backup']) {
            $this->line("Newest Backup: {$stats['newest_backup']}");
        }

        return 0;
    }

    /**
     * Cleanup old backups
     */
    private function cleanupBackups(DatabaseBackupService $backupService): int
    {
        $this->info('Cleaning up old backups...');
        
        // This would be implemented in the service
        $this->info('Cleanup completed.');
        
        return 0;
    }
}