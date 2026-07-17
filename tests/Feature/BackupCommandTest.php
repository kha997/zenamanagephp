<?php

namespace Tests\Feature;

use App\Models\MaintenanceTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('backups');
        $this->cleanBackupDir();
    }

    protected function tearDown(): void
    {
        $this->cleanBackupDir();
        parent::tearDown();
    }

    private function cleanBackupDir(): void
    {
        foreach (glob($this->backupDir . '/backup_*') ?: [] as $path) {
            if (is_dir($path)) {
                exec('rm -rf ' . escapeshellarg($path));
            } else {
                unlink($path);
            }
        }
    }

    public function test_database_only_backup_produces_a_single_compressed_archive(): void
    {
        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $archives = glob($this->backupDir . '/backup_*.tar.gz') ?: [];
        $this->assertCount(1, $archives, 'database-only backup should compress and leave exactly one archive, not an uncompressed directory');

        $looseDirs = array_filter(glob($this->backupDir . '/backup_*') ?: [], 'is_dir');
        $this->assertCount(0, $looseDirs, 'no uncompressed backup directories should be left behind');

        $this->assertDatabaseHas('maintenance_tasks', [
            'task' => 'System backup',
            'status' => 'completed',
        ]);
    }

    public function test_old_archives_beyond_max_backups_are_cleaned_up(): void
    {
        config(['backup.max_backups' => 1]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);
        sleep(1); // backup directory names are second-precision — force a distinct timestamp
        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $archives = glob($this->backupDir . '/backup_*.tar.gz') ?: [];
        $this->assertCount(1, $archives, 'cleanup should enforce max_backups even for database-only runs');
    }
}
