<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.disk' => null]);
        $this->dir = storage_path('backups');
        @mkdir($this->dir, 0755, true);
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach (glob($this->dir . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
    }

    private function archive(string $type, int $daysOld, int $seq = 0): string
    {
        $ts = time() - ($daysOld * 86400) - $seq;
        $path = sprintf('%s/backup_%s_%s.tar.gz', $this->dir, $type, date('Y-m-d_H-i-s', $ts));
        touch($path, $ts);

        return $path;
    }

    public function test_database_retention_is_independent_of_full_archives(): void
    {
        $fullOld = $this->archive('full', 29);
        $fullExpired = $this->archive('full', 31);
        $dbExpired = $this->archive('database', 8);
        $dbBeyondCount = $this->archive('database', 6);
        $legacy = $this->dir . '/backup_2020-01-01_01-00-00.tar.gz';
        touch($legacy, time() - 400 * 86400);

        for ($i = 1; $i <= 40; $i++) {           // 40 fresh database archives
            $this->archive('database', 0, $i * 60);
        }

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        // A database run prunes only database archives.
        $this->assertFileExists($fullOld);
        $this->assertFileExists($fullExpired, 'a database run must not prune full archives');
        $this->assertFileDoesNotExist($dbExpired, 'older than 7 days');
        $this->assertFileDoesNotExist($dbBeyondCount, 'within 7 days but outside the newest 28');
        $this->assertCount(28, glob($this->dir . '/backup_database_*.tar.gz') ?: []);
        $this->assertFileExists($legacy, 'legacy untyped archives are never deleted');
    }

    public function test_full_retention_keeps_30_days_of_full_archives(): void
    {
        $fullKept = $this->archive('full', 29);
        $fullExpired = $this->archive('full', 31);
        $dbFresh = $this->archive('database', 1);

        $this->artisan('backup:run', ['--type' => 'all'])->assertExitCode(0);

        $this->assertFileExists($fullKept);
        $this->assertFileDoesNotExist($fullExpired);
        $this->assertFileExists($dbFresh, 'a full run must not prune database archives');
    }

    public function test_ten_archive_legacy_cap_no_longer_evicts_full_backups(): void
    {
        $full = $this->archive('full', 1);
        for ($i = 1; $i <= 12; $i++) {           // > the old shared cap of 10
            $this->archive('database', 0, $i * 60);
        }

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertFileExists($full);
    }

    public function test_a_full_run_is_named_full(): void
    {
        $this->artisan('backup:run', ['--type' => 'all'])->assertExitCode(0);

        $this->assertCount(1, glob($this->dir . '/backup_full_*.tar.gz') ?: []);
    }

    public function test_zero_max_backups_does_not_delete_the_archive_just_stored(): void
    {
        config(['backup.retention.database.max_backups' => 0]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertCount(1, glob($this->dir . '/backup_database_*.tar.gz') ?: [], 'a max_backups of 0 must not wipe out the archive the run just produced');
    }

    public function test_zero_max_age_days_does_not_delete_the_archive_just_stored(): void
    {
        config(['backup.retention.database.max_age_days' => 0]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertCount(1, glob($this->dir . '/backup_database_*.tar.gz') ?: [], 'a max_age_days of 0 must not wipe out the archive the run just produced');
    }
}
