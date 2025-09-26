<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;

/**
 * Database Backup and Recovery Service
 * 
 * Provides comprehensive database backup and recovery functionality
 */
class DatabaseBackupService
{
    private string $backupPath;
    private string $backupDisk;
    private int $maxBackups = 30;
    private bool $compressBackups = true;
    private array $excludedTables = ['query_logs', 'cache', 'sessions'];

    public function __construct()
    {
        $this->backupPath = config('database.backup.path', 'backups/database');
        $this->backupDisk = config('database.backup.disk', 'local');
        $this->maxBackups = config('database.backup.max_backups', 30);
        $this->compressBackups = config('database.backup.compress', true);
    }

    /**
     * Create full database backup
     */
    public function createFullBackup(string $description = null): array
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "full_backup_{$timestamp}.sql";
            
            if ($description) {
                $filename = "full_backup_{$timestamp}_{$this->sanitizeDescription($description)}.sql";
            }

            $filepath = $this->getBackupPath($filename);
            
            // Create backup directory if it doesn't exist
            $this->ensureBackupDirectory();

            // Get database configuration
            $config = config('database.connections.mysql');
            
            // Build mysqldump command
            $command = $this->buildMysqldumpCommand($config, $filepath);
            
            // Execute backup
            $result = Process::run($command);
            
            if (!$result->successful()) {
                throw new \Exception('Backup failed: ' . $result->errorOutput());
            }

            // Compress backup if enabled
            if ($this->compressBackups) {
                $this->compressBackup($filepath);
                $filepath .= '.gz';
            }

            // Store backup metadata
            $metadata = $this->storeBackupMetadata($filename, $filepath, 'full', $description);
            
            // Cleanup old backups
            $this->cleanupOldBackups();

            Log::info('Database backup created successfully', [
                'filename' => $filename,
                'size' => $this->getFileSize($filepath),
                'type' => 'full'
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $this->getFileSize($filepath),
                'metadata' => $metadata
            ];

        } catch (\Exception $e) {
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'type' => 'full'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create incremental backup (only changed data)
     */
    public function createIncrementalBackup(string $description = null): array
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "incremental_backup_{$timestamp}.sql";
            
            if ($description) {
                $filename = "incremental_backup_{$timestamp}_{$this->sanitizeDescription($description)}.sql";
            }

            $filepath = $this->getBackupPath($filename);
            
            // Create backup directory if it doesn't exist
            $this->ensureBackupDirectory();

            // Get database configuration
            $config = config('database.connections.mysql');
            
            // Build mysqldump command for incremental backup
            $command = $this->buildIncrementalMysqldumpCommand($config, $filepath);
            
            // Execute backup
            $result = Process::run($command);
            
            if (!$result->successful()) {
                throw new \Exception('Incremental backup failed: ' . $result->errorOutput());
            }

            // Compress backup if enabled
            if ($this->compressBackups) {
                $this->compressBackup($filepath);
                $filepath .= '.gz';
            }

            // Store backup metadata
            $metadata = $this->storeBackupMetadata($filename, $filepath, 'incremental', $description);
            
            // Cleanup old backups
            $this->cleanupOldBackups();

            Log::info('Incremental database backup created successfully', [
                'filename' => $filename,
                'size' => $this->getFileSize($filepath),
                'type' => 'incremental'
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $this->getFileSize($filepath),
                'metadata' => $metadata
            ];

        } catch (\Exception $e) {
            Log::error('Incremental database backup failed', [
                'error' => $e->getMessage(),
                'type' => 'incremental'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreFromBackup(string $filename): array
    {
        try {
            $filepath = $this->getBackupPath($filename);
            
            // Check if backup file exists
            if (!file_exists($filepath)) {
                throw new \Exception("Backup file not found: {$filename}");
            }

            // Decompress if needed
            if (str_ends_with($filepath, '.gz')) {
                $this->decompressBackup($filepath);
                $filepath = str_replace('.gz', '', $filepath);
            }

            // Get database configuration
            $config = config('database.connections.mysql');
            
            // Build mysql restore command
            $command = $this->buildMysqlRestoreCommand($config, $filepath);
            
            // Execute restore
            $result = Process::run($command);
            
            if (!$result->successful()) {
                throw new \Exception('Restore failed: ' . $result->errorOutput());
            }

            Log::info('Database restored successfully', [
                'filename' => $filename,
                'filepath' => $filepath
            ]);

            return [
                'success' => true,
                'message' => 'Database restored successfully',
                'filename' => $filename
            ];

        } catch (\Exception $e) {
            Log::error('Database restore failed', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List available backups
     */
    public function listBackups(): array
    {
        try {
            $backupPath = $this->getBackupPath('');
            $files = glob($backupPath . '*.sql*');
            
            $backups = [];
            foreach ($files as $file) {
                $filename = basename($file);
                $backups[] = [
                    'filename' => $filename,
                    'size' => $this->getFileSize($file),
                    'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => $this->getBackupType($filename)
                ];
            }

            // Sort by creation time (newest first)
            usort($backups, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return $backups;

        } catch (\Exception $e) {
            Log::error('Failed to list backups', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(string $filename): bool
    {
        try {
            $filepath = $this->getBackupPath($filename);
            
            if (file_exists($filepath)) {
                unlink($filepath);
                
                Log::info('Backup deleted successfully', [
                    'filename' => $filename
                ]);
                
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to delete backup', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return false;
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats(): array
    {
        $backups = $this->listBackups();
        
        $totalSize = 0;
        $fullBackups = 0;
        $incrementalBackups = 0;
        
        foreach ($backups as $backup) {
            $totalSize += $backup['size'];
            
            if ($backup['type'] === 'full') {
                $fullBackups++;
            } elseif ($backup['type'] === 'incremental') {
                $incrementalBackups++;
            }
        }

        return [
            'total_backups' => count($backups),
            'full_backups' => $fullBackups,
            'incremental_backups' => $incrementalBackups,
            'total_size' => $this->formatBytes($totalSize),
            'total_size_bytes' => $totalSize,
            'oldest_backup' => !empty($backups) ? end($backups)['created_at'] : null,
            'newest_backup' => !empty($backups) ? $backups[0]['created_at'] : null
        ];
    }

    /**
     * Build mysqldump command
     */
    private function buildMysqldumpCommand(array $config, string $filepath): string
    {
        $command = "mysqldump";
        $command .= " --host={$config['host']}";
        $command .= " --port={$config['port']}";
        $command .= " --user={$config['username']}";
        
        if (!empty($config['password'])) {
            $command .= " --password={$config['password']}";
        }
        
        $command .= " --single-transaction";
        $command .= " --routines";
        $command .= " --triggers";
        $command .= " --events";
        $command .= " --add-drop-database";
        $command .= " --add-drop-table";
        $command .= " --create-options";
        $command .= " --disable-keys";
        $command .= " --extended-insert";
        $command .= " --quick";
        $command .= " --lock-tables=false";
        
        // Exclude tables
        foreach ($this->excludedTables as $table) {
            $command .= " --ignore-table={$config['database']}.{$table}";
        }
        
        $command .= " {$config['database']} > {$filepath}";
        
        return $command;
    }

    /**
     * Build incremental mysqldump command
     */
    private function buildIncrementalMysqldumpCommand(array $config, string $filepath): string
    {
        $command = "mysqldump";
        $command .= " --host={$config['host']}";
        $command .= " --port={$config['port']}";
        $command .= " --user={$config['username']}";
        
        if (!empty($config['password'])) {
            $command .= " --password={$config['password']}";
        }
        
        $command .= " --single-transaction";
        $command .= " --where='updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'";
        $command .= " --no-create-info";
        $command .= " --complete-insert";
        
        // Only backup tables with updated_at column
        $tables = $this->getTablesWithUpdatedAt();
        if (!empty($tables)) {
            $command .= " " . implode(' ', $tables);
        }
        
        $command .= " {$config['database']} > {$filepath}";
        
        return $command;
    }

    /**
     * Build mysql restore command
     */
    private function buildMysqlRestoreCommand(array $config, string $filepath): string
    {
        $command = "mysql";
        $command .= " --host={$config['host']}";
        $command .= " --port={$config['port']}";
        $command .= " --user={$config['username']}";
        
        if (!empty($config['password'])) {
            $command .= " --password={$config['password']}";
        }
        
        $command .= " {$config['database']} < {$filepath}";
        
        return $command;
    }

    /**
     * Get backup path
     */
    private function getBackupPath(string $filename): string
    {
        return storage_path("app/{$this->backupPath}/{$filename}");
    }

    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory(): void
    {
        $backupPath = $this->getBackupPath('');
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
    }

    /**
     * Compress backup file
     */
    private function compressBackup(string $filepath): void
    {
        $command = "gzip {$filepath}";
        Process::run($command);
    }

    /**
     * Decompress backup file
     */
    private function decompressBackup(string $filepath): void
    {
        $command = "gunzip {$filepath}";
        Process::run($command);
    }

    /**
     * Get file size
     */
    private function getFileSize(string $filepath): int
    {
        return file_exists($filepath) ? filesize($filepath) : 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get backup type from filename
     */
    private function getBackupType(string $filename): string
    {
        if (str_contains($filename, 'full_backup')) {
            return 'full';
        } elseif (str_contains($filename, 'incremental_backup')) {
            return 'incremental';
        }
        
        return 'unknown';
    }

    /**
     * Sanitize description for filename
     */
    private function sanitizeDescription(string $description): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $description);
    }

    /**
     * Store backup metadata
     */
    private function storeBackupMetadata(string $filename, string $filepath, string $type, string $description = null): array
    {
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'type' => $type,
            'description' => $description,
            'created_at' => now(),
            'size' => $this->getFileSize($filepath)
        ];
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups(): void
    {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            $backupsToDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['filename']);
            }
        }
    }

    /**
     * Get tables with updated_at column
     */
    private function getTablesWithUpdatedAt(): array
    {
        try {
            $tables = DB::select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = 'updated_at'
            ");
            
            return array_map(function ($table) {
                return $table->TABLE_NAME;
            }, $tables);
            
        } catch (\Exception $e) {
            Log::warning('Failed to get tables with updated_at column', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
}
