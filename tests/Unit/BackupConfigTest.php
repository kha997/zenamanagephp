<?php

namespace Tests\Unit;

use Tests\TestCase;

class BackupConfigTest extends TestCase
{
    public function test_backup_config_defaults_are_present(): void
    {
        $this->assertSame(10, config('backup.max_backups'));
        $this->assertSame(30, config('backup.max_age_days'));
        $this->assertNull(config('backup.disk'));
        $this->assertSame('backups', config('backup.path'));
    }

    public function test_backup_retention_defaults_match_approved_design(): void
    {
        $this->assertSame(
            ['max_backups' => 30, 'max_age_days' => 30],
            config('backup.retention.full')
        );
        $this->assertSame(
            ['max_backups' => 28, 'max_age_days' => 7],
            config('backup.retention.database')
        );
    }
}
