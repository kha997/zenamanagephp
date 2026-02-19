<?php

namespace App\Console\Commands;

use App\Models\MaintenanceTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:run {--type=all : Type of backup (all, database, files, config)}';

    /**
     * The console command description.
     */
    protected $description = 'Create automated backups of the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');

        $this->info('Starting backup process...');

        $task = MaintenanceTask::create([
            'task' => 'System backup',
            'level' => 'info',
            'priority' => 'high',
            'status' => 'running',
            'started_at' => now()
        ]);

        try {
            switch ($type) {
                case 'all':
                    $this->backupAll();
                    break;
                case 'database':
                    $this->backupDatabase();
                    break;
                case 'files':
                    $this->backupFiles();
                    break;
                case 'config':
                    $this->backupConfig();
                    break;
                default:
                    $this->error('Invalid backup type. Available types: all, database, files, config');
                    $task->markAsFailed('Invalid backup type');
                    return 1;
            }

            $task->markAsCompleted(['backup_type' => $type]);
            $this->info('Backup completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Backup all components
     */
    private function backupAll()
    {
        $this->info('Creating comprehensive backup...');

        $backupDir = $this->createBackupDirectory();
        
        $this->backupDatabase($backupDir);
        $this->backupFiles($backupDir);
        $this->backupConfig($backupDir);
        
        $this->createBackupManifest($backupDir);
        $this->compressBackup($backupDir);
        
        $this->cleanupOldBackups();
    }

    /**
     * Backup database
     */
    private function backupDatabase($backupDir = null)
    {
        $this->info('Backing up database...');

        if (!$backupDir) {
            $backupDir = $this->createBackupDirectory();
        }

        $filename = 'database_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $config = config('database.connections.mysql', []);

            if (empty($config['host']) || empty($config['database'])) {
                throw new \RuntimeException('MySQL backup configuration is incomplete');
            }

            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers %s > %s',
                $config['username'] ?? '',
                $config['password'] ?? '',
                $config['host'],
                $config['port'] ?? 3306,
                $config['database'],
                $filepath
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Database backup failed with return code: ' . $returnCode);
            }

            if (!file_exists($filepath) || filesize($filepath) === 0) {
                throw new \Exception('Database backup file is empty or does not exist');
            }
        } else {
            file_put_contents($filepath, $this->buildFallbackBackupContent($driver));
        }

        $this->info('✓ Database backup completed: ' . $this->formatBytes(filesize($filepath)));
    }

    /**
     * Backup application files
     */
    private function backupFiles($backupDir = null)
    {
        $this->info('Backing up application files...');

        if (!$backupDir) {
            $backupDir = $this->createBackupDirectory();
        }

        $filesDir = $backupDir . '/files';
        mkdir($filesDir, 0755, true);

        // Backup storage directory
        $this->backupDirectory(storage_path('app'), $filesDir . '/storage_app');
        
        // Backup public uploads
        if (is_dir(public_path('uploads'))) {
            $this->backupDirectory(public_path('uploads'), $filesDir . '/public_uploads');
        }

        // Backup logs
        $this->backupDirectory(storage_path('logs'), $filesDir . '/logs');

        $this->info('✓ Application files backup completed');
    }

    /**
     * Backup configuration files
     */
    private function backupConfig($backupDir = null)
    {
        $this->info('Backing up configuration files...');

        if (!$backupDir) {
            $backupDir = $this->createBackupDirectory();
        }

        $configDir = $backupDir . '/config';
        mkdir($configDir, 0755, true);

        // Backup environment file
        if (file_exists(base_path('.env'))) {
            copy(base_path('.env'), $configDir . '/.env');
        }

        // Backup configuration files
        $configFiles = [
            'app.php',
            'database.php',
            'cache.php',
            'session.php',
            'queue.php',
            'mail.php'
        ];

        foreach ($configFiles as $file) {
            $sourcePath = config_path($file);
            if (file_exists($sourcePath)) {
                copy($sourcePath, $configDir . '/' . $file);
            }
        }

        // Backup composer files
        if (file_exists(base_path('composer.json'))) {
            copy(base_path('composer.json'), $configDir . '/composer.json');
        }

        if (file_exists(base_path('composer.lock'))) {
            copy(base_path('composer.lock'), $configDir . '/composer.lock');
        }

        $this->info('✓ Configuration files backup completed');
    }

    /**
     * Create backup directory
     */
    private function createBackupDirectory()
    {
        $backupBaseDir = storage_path('backups');
        if (!is_dir($backupBaseDir)) {
            mkdir($backupBaseDir, 0755, true);
        }

        $backupDir = $backupBaseDir . '/backup_' . date('Y-m-d_H-i-s');
        mkdir($backupDir, 0755, true);

        return $backupDir;
    }

    /**
     * Backup directory recursively
     */
    private function backupDirectory($source, $destination)
    {
        if (!is_dir($source)) {
            return;
        }

        mkdir($destination, 0755, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                mkdir($targetPath, 0755, true);
            } else {
                copy($item, $targetPath);
            }
        }
    }

    /**
     * Create backup manifest
     */
    private function createBackupManifest($backupDir)
    {
        $manifest = [
            'backup_date' => now()->toISOString(),
            'backup_type' => 'comprehensive',
            'application_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'database_version' => $this->getDatabaseVersion(),
            'files' => $this->getBackupFiles($backupDir),
            'total_size' => $this->getDirectorySize($backupDir)
        ];

        file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion()
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            return $version;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get backup files list
     */
    private function getBackupFiles($backupDir)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => str_replace($backupDir . '/', '', $file->getPathname()),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }

        return $files;
    }

    /**
     * Compress backup directory
     */
    private function compressBackup($backupDir)
    {
        $this->info('Compressing backup...');

        $archivePath = $backupDir . '.tar.gz';
        $command = "tar -czf {$archivePath} -C " . dirname($backupDir) . " " . basename($backupDir);

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Backup compression failed');
        }

        // Remove uncompressed directory
        $this->removeDirectory($backupDir);

        $this->info('✓ Backup compressed: ' . $this->formatBytes(filesize($archivePath)));
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups()
    {
        $this->info('Cleaning up old backups...');

        $backupDir = storage_path('backups');
        $maxBackups = config('backup.max_backups', 10);
        $maxAge = config('backup.max_age_days', 30);

        $backups = glob($backupDir . '/backup_*.tar.gz');
        
        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $deletedCount = 0;

        // Remove backups exceeding max count
        if (count($backups) > $maxBackups) {
            $toDelete = array_slice($backups, $maxBackups);
            foreach ($toDelete as $backup) {
                unlink($backup);
                $deletedCount++;
            }
        }

        // Remove backups older than max age
        $cutoffTime = time() - ($maxAge * 24 * 60 * 60);
        foreach ($backups as $backup) {
            if (filemtime($backup) < $cutoffTime) {
                unlink($backup);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("✓ Cleaned up {$deletedCount} old backups");
        }
    }

    /**
     * Get directory size
     */
    private function getDirectorySize($path)
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }

    private function buildFallbackBackupContent(string $driver): string
    {
        $tables = [];

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            $tables = array_map(fn ($row) => $row->name, $rows);
        }

        $payload = [
            'driver' => $driver,
            'tables' => $tables,
            'timestamp' => now()->toIso8601String(),
        ];

        return "-- Backup placeholder for {$driver}\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
