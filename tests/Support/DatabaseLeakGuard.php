<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * GAP-100: detects a test class that commits rows to the shared test database and leaves them for later tests
 * (the GAP-065 / GAP-100 order-dependent failures). Tests\TestCase snapshots row counts at the end of setUp() and
 * compares them at the start of tearDown(), only for classes without database isolation. SQLite only: the MySQL
 * jobs are untouched. Test infrastructure only; never referenced from app code.
 */
final class DatabaseLeakGuard
{
    private const ISOLATION_TRAITS = [
        RefreshDatabase::class,
        DatabaseTransactions::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    public static function applies(string $testClass): bool
    {
        return array_intersect(self::ISOLATION_TRAITS, class_uses_recursive($testClass)) === [];
    }

    /**
     * @return array<string, int>|null row count per table, or null when the connection is not SQLite
     */
    public static function snapshot(Connection $connection): ?array
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return null;
        }

        $counts = [];
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $table) {
            $counts[$table->name] = $connection->table($table->name)->count();
        }

        return $counts;
    }

    /**
     * @param array<string, int> $before
     * @param array<string, int> $after
     * @return list<string> "table: before -> after" for every table whose row count changed; a new empty table is not a leak
     */
    public static function leaks(array $before, array $after): array
    {
        $leaks = [];
        foreach ($after as $table => $count) {
            $previous = $before[$table] ?? 0;
            if ($count !== $previous) {
                $leaks[] = "{$table}: {$previous} -> {$count}";
            }
        }

        return $leaks;
    }
}
