<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackupJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 1; // Only try once for backup jobs
    public $uniqueId = 'backup-job';

    protected $backupType;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(string $backupType = 'full', array $options = [])
    {
        $this->backupType = $backupType;
        $this->options = $options;
        $this->onQueue('backup');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting backup job', [
            'backup_type' => $this->backupType,
            'options' => $this->options
        ]);

        try {
            switch ($this->backupType) {
                case 'database':
                    $this->backupDatabase();
                    break;
                case 'files':
                    $this->backupFiles();
                    break;
                case 'full':
                    $this->backupDatabase();
                    $this->backupFiles();
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown backup type: {$this->backupType}");
            }

            Log::info('Backup job completed successfully', [
                'backup_type' => $this->backupType
            ]);

        } catch (\Exception $e) {
            Log::error('Backup job failed', [
                'backup_type' => $this->backupType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Backup database
     */
    protected function backupDatabase(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "database_backup_{$timestamp}.sql";
        $backupPath = "backups/database/{$filename}";

        Log::info('Starting database backup', ['filename' => $filename]);

        // Get database configuration
        $database = config('database.connections.mysql');
        $host = $database['host'];
        $username = $database['username'];
        $password = $database['password'];
        $databaseName = $database['database'];

        // Create mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($databaseName),
            escapeshellarg(storage_path("app/{$backupPath}"))
        );

        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Database backup failed with return code: {$returnCode}");
        }

        // Store backup metadata
        $this->storeBackupMetadata('database', $backupPath, [
            'type' => 'database',
            'filename' => $filename,
            'size' => Storage::size($backupPath),
            'created_at' => now()->toISOString()
        ]);

        Log::info('Database backup completed', [
            'filename' => $filename,
            'size' => Storage::size($backupPath)
        ]);
    }

    /**
     * Backup files
     */
    protected function backupFiles(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "files_backup_{$timestamp}.tar.gz";
        $backupPath = "backups/files/{$filename}";

        Log::info('Starting files backup', ['filename' => $filename]);

        // Define directories to backup
        $directoriesToBackup = [
            'storage/app/uploads',
            'storage/app/documents',
            'storage/app/exports',
            'public/uploads'
        ];

        // Create tar command
        $command = sprintf(
            'tar -czf %s -C %s %s',
            escapeshellarg(storage_path("app/{$backupPath}")),
            escapeshellarg(base_path()),
            implode(' ', array_map('escapeshellarg', $directoriesToBackup))
        );

        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Files backup failed with return code: {$returnCode}");
        }

        // Store backup metadata
        $this->storeBackupMetadata('files', $backupPath, [
            'type' => 'files',
            'filename' => $filename,
            'size' => Storage::size($backupPath),
            'directories' => $directoriesToBackup,
            'created_at' => now()->toISOString()
        ]);

        Log::info('Files backup completed', [
            'filename' => $filename,
            'size' => Storage::size($backupPath)
        ]);
    }

    /**
     * Store backup metadata
     */
    protected function storeBackupMetadata(string $type, string $path, array $metadata): void
    {
        // Store in database
        DB::table('backup_logs')->insert([
            'type' => $type,
            'path' => $path,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Store in config file for easy access
        $configPath = storage_path("app/backups/{$type}_latest.json");
        file_put_contents($configPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up old backups
     */
    protected function cleanupOldBackups(): void
    {
        $retentionDays = config('backup.retention_days', 30);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        // Clean up database backups
        $oldDatabaseBackups = DB::table('backup_logs')
            ->where('type', 'database')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        foreach ($oldDatabaseBackups as $backup) {
            if (Storage::exists($backup->path)) {
                Storage::delete($backup->path);
            }
            DB::table('backup_logs')->where('id', $backup->id)->delete();
        }

        // Clean up files backups
        $oldFilesBackups = DB::table('backup_logs')
            ->where('type', 'files')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        foreach ($oldFilesBackups as $backup) {
            if (Storage::exists($backup->path)) {
                Storage::delete($backup->path);
            }
            DB::table('backup_logs')->where('id', $backup->id)->delete();
        }

        Log::info('Old backups cleaned up', [
            'database_backups_deleted' => $oldDatabaseBackups->count(),
            'files_backups_deleted' => $oldFilesBackups->count()
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Backup job failed permanently', [
            'error' => $exception->getMessage(),
            'backup_type' => $this->backupType,
            'trace' => $exception->getTraceAsString()
        ]);

        // Store failure in database
        DB::table('backup_logs')->insert([
            'type' => $this->backupType,
            'path' => null,
            'metadata' => json_encode([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString()
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->uniqueId . '-' . $this->backupType;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['backup', 'maintenance', 'type:' . $this->backupType];
    }
}
