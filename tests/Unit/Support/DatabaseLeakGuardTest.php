<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DatabaseLeakGuard;

/**
 * GAP-100: no-app unit coverage for Tests\Support\DatabaseLeakGuard. Uses its own in-memory SQLite connection, never
 * the shared test database.
 */
final class DatabaseLeakGuardTest extends TestCase
{
    public function test_applies_only_to_classes_without_database_isolation(): void
    {
        $this->assertTrue(DatabaseLeakGuard::applies(DatabaseLeakGuardTestNoIsolation::class));
        $this->assertFalse(DatabaseLeakGuard::applies(DatabaseLeakGuardTestRefreshes::class));
        $this->assertFalse(DatabaseLeakGuard::applies(DatabaseLeakGuardTestTransacts::class));
    }

    public function test_snapshot_counts_rows_per_table(): void
    {
        $connection = $this->sqlite();
        $connection->statement('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->table('tenants')->insert([['id' => 1], ['id' => 2]]);

        $this->assertSame(['tenants' => 2, 'users' => 0], DatabaseLeakGuard::snapshot($connection));
    }

    public function test_snapshot_is_null_on_non_sqlite_connections(): void
    {
        $connection = new MySqlConnection(
            static fn () => throw new RuntimeException('must not connect'),
            '',
            '',
            ['driver' => 'mysql']
        );

        $this->assertNull(DatabaseLeakGuard::snapshot($connection));
    }

    public function test_leaks_lists_changed_tables_and_ignores_new_empty_tables(): void
    {
        $leaks = DatabaseLeakGuard::leaks(
            ['tenants' => 0, 'users' => 3, 'projects' => 1],
            ['tenants' => 1, 'users' => 3, 'projects' => 0, 'scratch_table' => 0, 'proof_table' => 2]
        );

        $this->assertSame(['tenants: 0 -> 1', 'projects: 1 -> 0', 'proof_table: 0 -> 2'], $leaks);
    }

    public function test_leaks_is_empty_when_nothing_changed(): void
    {
        $this->assertSame([], DatabaseLeakGuard::leaks(['tenants' => 1], ['tenants' => 1]));
    }

    public function test_detects_rows_written_between_snapshots(): void
    {
        $connection = $this->sqlite();
        $connection->statement('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $before = DatabaseLeakGuard::snapshot($connection);

        $connection->table('tenants')->insert(['id' => 1]);

        $this->assertSame(['tenants: 0 -> 1'], DatabaseLeakGuard::leaks($before, DatabaseLeakGuard::snapshot($connection)));
    }

    private function sqlite(): SQLiteConnection
    {
        return new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
    }
}

class DatabaseLeakGuardTestNoIsolation
{
}

class DatabaseLeakGuardTestRefreshes
{
    use RefreshDatabase;
}

class DatabaseLeakGuardTestTransacts
{
    use DatabaseTransactions;
}
