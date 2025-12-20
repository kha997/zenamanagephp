<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Helper for migration driver-specific behavior.
 */
final class MigrationDriver
{
    /**
     * Determine if the current database driver is SQLite.
     */
    public static function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
}
