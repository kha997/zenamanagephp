<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Database Driver Detection Helper
 * 
 * Provides methods to detect the current database driver being used.
 * This is essential for maintaining compatibility between MySQL (production)
 * and SQLite (testing) environments.
 */
class DBDriver
{
    /**
     * Check if the current database driver is SQLite
     *
     * @return bool
     */
    public static function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    /**
     * Check if the current database driver is MySQL
     *
     * @return bool
     */
    public static function isMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    /**
     * Get the current database driver name
     *
     * @return string
     */
    public static function getDriverName(): string
    {
        return DB::getDriverName();
    }

    /**
     * Check if foreign key constraints are supported
     *
     * @return bool
     */
    public static function supportsForeignKeys(): bool
    {
        if (self::isSqlite()) {
            return config('database.connections.sqlite.foreign_key_constraints', true);
        }
        
        return true; // MySQL always supports foreign keys
    }

    /**
     * Check if JSON operations are natively supported
     *
     * @return bool
     */
    public static function supportsJsonOperations(): bool
    {
        return self::isMysql(); // MySQL has native JSON support, SQLite uses TEXT
    }

    /**
     * Check if full-text search is supported
     *
     * @return bool
     */
    public static function supportsFullTextSearch(): bool
    {
        return self::isMysql(); // MySQL has FTS, SQLite has limited FTS support
    }

    /**
     * Get database-specific query optimizations
     *
     * @return array
     */
    public static function getOptimizations(): array
    {
        if (self::isMysql()) {
            return [
                'use_json_functions' => true,
                'use_fulltext_search' => true,
                'use_for_update' => true,
                'use_window_functions' => true,
            ];
        }

        return [
            'use_json_functions' => false,
            'use_fulltext_search' => false,
            'use_for_update' => false,
            'use_window_functions' => false,
        ];
    }
}
