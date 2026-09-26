<?php

namespace Tests\Feature\Console;

use App\Services\LaunchChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class BackupStorageLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('backups') . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
        parent::tearDown();
    }

    private function checklistSeesBackup(): bool
    {
        $m = new ReflectionMethod(LaunchChecklistService::class, 'checkBackupSystem');
        $m->setAccessible(true);

        return (bool) $m->invoke(new LaunchChecklistService());
    }

    public function test_unset_disk_keeps_the_legacy_location(): void
    {
        config(['backup.disk' => null]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertCount(1, glob(storage_path('backups') . '/backup_database_*.tar.gz') ?: []);
        $this->assertTrue($this->checklistSeesBackup());
    }

    public function test_configured_disk_and_path_receive_the_archive(): void
    {
        Storage::fake('gap054-offsite');
        config(['backup.disk' => 'gap054-offsite', 'backup.path' => 'zena/backups']);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $files = Storage::disk('gap054-offsite')->files('zena/backups');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/backup_database_.*\.tar\.gz$/', $files[0]);
        $this->assertSame([], glob(storage_path('backups') . '/backup_database_*.tar.gz') ?: [], 'no local copy left behind');
        $this->assertTrue($this->checklistSeesBackup());
    }

    public function test_unwritable_disk_fails_and_deletes_nothing(): void
    {
        $root = storage_path('framework/testing/gap054-readonly');
        @mkdir($root . '/zena/backups', 0755, true);
        $old = $root . '/zena/backups/backup_database_2000-01-01_00-00-00.tar.gz';
        file_put_contents($old, 'old');
        touch($old, time() - 400 * 86400);   // expired: would be pruned if prune ran
        chmod($root . '/zena/backups', 0555);
        config([
            'filesystems.disks.gap054-readonly' => ['driver' => 'local', 'root' => $root, 'throw' => false],
            'backup.disk' => 'gap054-readonly',
            'backup.path' => 'zena/backups',
        ]);

        try {
            $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(1);
            $this->assertFileExists($old, 'a failed store must not prune anything');
        } finally {
            chmod($root . '/zena/backups', 0755);
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
