<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaTestingDatabaseInvariantTest extends TestCase
{
    public function test_testing_environment_uses_sqlite_test_database(): void
    {
        $environment = app()->environment();
        $defaultConnection = config('database.default');
        $sqliteDatabase = config('database.connections.sqlite.database') ?? '';
        $mysqlHost = config('database.connections.mysql.host') ?? 'unset';
        $mysqlDatabase = config('database.connections.mysql.database') ?? 'unset';

        $diagnostics = sprintf(
            'env=%s default=%s sqlite=%s mysql_host=%s mysql_db=%s',
            $environment,
            $defaultConnection,
            $sqliteDatabase,
            $mysqlHost,
            $mysqlDatabase
        );

        $this->assertSame(
            'testing',
            $environment,
            "Zena invariants must run in the testing environment ($diagnostics)."
        );

        $this->assertSame(
            'sqlite',
            $defaultConnection,
            "Zena invariants must use the sqlite connection ($diagnostics)."
        );

        $this->assertTrue(
            str_ends_with($sqliteDatabase, 'zenamanage_test.sqlite'),
            "Zena invariants must point to the sqlite test file ($diagnostics)."
        );
    }
}
