<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupContentsSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = 'GAP054_SECRET_SENTINEL_5f1c';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.disk' => null]);
        $this->clean();
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }
        $this->clean();
        exec('rm -rf ' . escapeshellarg(storage_path('app/backups/gap054-probe')));
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach (glob(storage_path('backups') . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
    }

    /** @return list<string> */
    private function runAndList(string $type): array
    {
        $this->artisan('backup:run', ['--type' => $type])->assertExitCode(0);
        $archives = glob(storage_path('backups') . '/backup_*.tar.gz') ?: [];
        $this->assertCount(1, $archives);
        exec('tar -tzf ' . escapeshellarg($archives[0]), $entries);

        return $entries;
    }

    private function writeSentinel(string $path): void
    {
        if (!file_exists($path)) {
            file_put_contents($path, 'APP_KEY=' . self::SENTINEL . "\n");
            $this->created[] = $path;
        }
    }

    public function test_full_backup_contains_no_env_files_or_secret_values(): void
    {
        $this->writeSentinel(base_path('.env'));
        $this->writeSentinel(base_path('.env.gap054probe'));

        $entries = $this->runAndList('all');

        foreach ($entries as $entry) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)\.env(\.|$)#', $entry, "archive must not contain {$entry}");
        }
        $archive = (glob(storage_path('backups') . '/backup_*.tar.gz') ?: [])[0];
        exec('tar -xzOf ' . escapeshellarg($archive) . ' | grep -c ' . escapeshellarg(self::SENTINEL), $count);
        $this->assertSame('0', trim($count[0] ?? '0'));
    }

    public function test_files_backup_on_the_local_disk_never_contains_earlier_backups(): void
    {
        // The real nesting risk: BACKUP_DISK=local stores archives under
        // storage/app (the local disk root), which backupFiles() copies.
        $dir = storage_path('app/backups/gap054-probe');
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/backup_full_2026-01-01_00-00-00.tar.gz', 'old');
        config(['backup.disk' => 'local', 'backup.path' => 'backups/gap054-probe']);

        $this->artisan('backup:run', ['--type' => 'files'])->assertExitCode(0);

        $archives = glob($dir . '/backup_files_*.tar.gz') ?: [];
        $this->assertCount(1, $archives);
        exec('tar -tzf ' . escapeshellarg($archives[0]), $entries);
        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('gap054-probe', $entry, "archive must not contain {$entry}");
        }
    }
}
