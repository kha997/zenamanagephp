<?php

namespace Tests\Unit;

use App\Services\LaunchChecklistService;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\TestCase;

class LaunchChecklistServiceBackupTest extends TestCase
{
    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('backups');
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        foreach (glob($this->backupDir . '/backup_*.tar.gz') ?: [] as $file) {
            unlink($file);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDir . '/backup_*.tar.gz') ?: [] as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function callPrivate(string $method): bool
    {
        $reflection = new ReflectionMethod(LaunchChecklistService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new LaunchChecklistService());
    }

    public function test_check_backup_system_is_false_when_no_backup_files_exist(): void
    {
        $this->assertFalse($this->callPrivate('checkBackupSystem'));
    }

    public function test_check_backup_system_is_true_when_recent_backup_exists(): void
    {
        touch($this->backupDir . '/backup_2026-07-17_01-00-00.tar.gz');

        $this->assertTrue($this->callPrivate('checkBackupSystem'));
    }

    public function test_check_backup_system_is_false_when_only_stale_backup_exists(): void
    {
        $file = $this->backupDir . '/backup_2020-01-01_01-00-00.tar.gz';
        touch($file);
        touch($file, time() - (60 * 60 * 72)); // 72h old, beyond the 48h freshness window

        $this->assertFalse($this->callPrivate('checkBackupSystem'));
    }

    public function test_check_ssl_certificate_ignores_scheme_outside_production(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.url', 'http://localhost');

        $this->assertTrue($this->callPrivate('checkSslCertificate'));
    }

    public function test_check_ssl_certificate_requires_https_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.url', 'http://insecure.example.com');

        $this->assertFalse($this->callPrivate('checkSslCertificate'));
    }

    public function test_check_ssl_certificate_passes_with_https_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.url', 'https://zena.example.com');

        $this->assertTrue($this->callPrivate('checkSslCertificate'));
    }

    public function test_check_backup_system_configured_requires_scheduler_enabled(): void
    {
        Config::set('app.enable_scheduler', false);

        $this->assertFalse($this->callPrivate('checkBackupSystemConfigured'));

        Config::set('app.enable_scheduler', true);

        $this->assertTrue($this->callPrivate('checkBackupSystemConfigured'));
    }
}
