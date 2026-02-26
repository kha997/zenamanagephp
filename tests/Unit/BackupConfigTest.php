<?php

namespace Tests\Unit;

use Tests\TestCase;

class BackupConfigTest extends TestCase
{
    public function test_backup_config_defaults_are_present(): void
    {
        $this->assertSame(10, config('backup.max_backups'));
        $this->assertSame(30, config('backup.max_age_days'));
        $this->assertNotEmpty(config('backup.disk'));
        $this->assertNotEmpty(config('backup.path'));
    }
}
