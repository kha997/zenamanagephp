<?php

namespace Tests;

use BadMethodCallException;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;
use Src\Foundation\EventBus;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private static ?string $testingDatabaseDriver = null;
    private static ?string $testingDatabaseSocket = null;
    private static bool $testingDatabaseLogged = false;
    private static bool $sqliteArtifactsPurged = false;

    protected function getEnvironmentSetUp($app): void
    {
        $this->configureTestingDatabase($app);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $paths = [
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            File::ensureDirectoryExists($path);
        }

        $this->resetTestingEnvironment();
    }

    private function resetTestingEnvironment(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.disks.documents.driver' => 'local',
            'filesystems.disks.documents.root' => storage_path('framework/testing/documents'),
            'queue.default' => 'sync',
            'broadcasting.default' => 'null',
            'cache.default' => 'array',
        ]);

        File::ensureDirectoryExists(storage_path('framework/testing/documents'));

        EventBus::restoreCoreState();
        EventBus::setAuditEnabled(true);
    }

    private function configureTestingDatabase($app): void
    {
        $sqlitePath = $this->ensureTestingSqliteFile();
        $this->ensureTestingDatabaseDriver();
        $driver = self::$testingDatabaseDriver ?? 'sqlite';
        $socket = self::$testingDatabaseSocket;

        $app['db']->purge('mysql');
        $app['db']->purge('sqlite');

        $app['config']->set('database.default', $driver);

        if ($driver === 'mysql') {
            $app['config']->set('database.connections.mysql.host', 'localhost');
            $app['config']->set('database.connections.mysql.port', 3306);
            $app['config']->set('database.connections.mysql.database', 'zenamanage_test');
            $app['config']->set('database.connections.mysql.username', 'root');
            $app['config']->set('database.connections.mysql.password', '');
            $app['config']->set('database.connections.mysql.unix_socket', $socket);
        } else {
            $app['config']->set('database.connections.sqlite.database', $sqlitePath);
            $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

            $app->bind(BaseBlueprint::class, function ($app, array $parameters) {
                return new SqliteSchemaBlueprint(
                    $parameters['table'] ?? null,
                    $parameters['callback'] ?? null,
                    $parameters['prefix'] ?? ''
                );
            });

            $sqliteConnection = $app['db']->connection('sqlite');
            $sqliteConnection->getSchemaBuilder()->blueprintResolver(function ($table, $callback) {
                return new SqliteSchemaBlueprint($table, $callback);
            });
            $this->resetTestingSqliteFile();
            $app['db']->reconnect('sqlite');
            $this->applySqlitePragmas($app);
        }

        $app['db']->setDefaultConnection($driver);

        if (! self::$testingDatabaseLogged) {
            Log::info('Testing database mode selected', [
                'driver' => $driver,
                'socket' => $driver === 'mysql' ? $socket : null,
            ]);

            self::$testingDatabaseLogged = true;
        }
    }

    private function ensureTestingSqliteFile(): string
    {
        $sqlitePath = storage_path('framework/testing/database.sqlite');
        File::ensureDirectoryExists(dirname($sqlitePath));

        if (! file_exists($sqlitePath)) {
            touch($sqlitePath);
        }

        return $sqlitePath;
    }

    protected function resetTestingSqliteFile(): void
    {
        if (self::$sqliteArtifactsPurged) {
            return;
        }

        if (config('database.default') !== 'sqlite') {
            return;
        }

        $sqlitePath = config('database.connections.sqlite.database');

        if (! is_string($sqlitePath) || $sqlitePath === '' || $sqlitePath === ':memory:') {
            return;
        }

        DB::disconnect('sqlite');
        DB::purge('sqlite');

        File::ensureDirectoryExists(dirname($sqlitePath));

        foreach ([
            $sqlitePath,
            "{$sqlitePath}-wal",
            "{$sqlitePath}-shm",
            "{$sqlitePath}-journal",
        ] as $path) {
            if (file_exists($path)) {
                File::delete($path);
            }
        }

        if (! file_exists($sqlitePath)) {
            touch($sqlitePath);
        }

        if (class_exists(RefreshDatabaseState::class)) {
            RefreshDatabaseState::$migrated = false;
        }

        self::$sqliteArtifactsPurged = true;
    }

    private function ensureTestingDatabaseDriver(): void
    {
        if (self::$testingDatabaseDriver !== null) {
            return;
        }

        $mysqlSocket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
        $mysqlOptIn = getenv('ZENAMANAGE_TEST_USE_MYSQL') === '1';

        if ($mysqlOptIn && $this->canUseMysqlSocket($mysqlSocket)) {
            self::$testingDatabaseDriver = 'mysql';
            self::$testingDatabaseSocket = $mysqlSocket;
        } else {
            self::$testingDatabaseDriver = 'sqlite';
            self::$testingDatabaseSocket = null;
        }
    }

    private function canUseMysqlSocket(string $socketPath): bool
    {
        if (! file_exists($socketPath) || ! is_readable($socketPath)) {
            return false;
        }

        $dsn = sprintf('mysql:unix_socket=%s;dbname=zenamanage_test;charset=utf8mb4', $socketPath);
        $pdo = null;

        try {
            $pdo = new PDO($dsn, 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $pdo->query('SELECT 1')->fetch();
            $pdo->query("SHOW FULL TABLES WHERE table_type = 'BASE TABLE'")->fetch();

            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            if ($pdo !== null) {
                $pdo = null;
            }
        }
    }

    private function applySqlitePragmas($app): void
    {
        try {
            $connection = $app['db']->connection('sqlite');
            $connection->statement('PRAGMA journal_mode = WAL');
            $connection->statement('PRAGMA busy_timeout = 5000');
        } catch (\Throwable $e) {
            Log::warning('Unable to configure sqlite pragmas for testing', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

class SqliteSchemaBlueprint extends BaseBlueprint
{
    protected function ensureCommandsAreValid(Connection $connection)
    {
        if ($connection instanceof SQLiteConnection) {
            if ($this->commandsNamed(['dropColumn', 'renameColumn'])->count() > 1
                && ! $connection->usingNativeSchemaOperations()) {
                throw new BadMethodCallException(
                    "SQLite doesn't support multiple calls to dropColumn / renameColumn in a single modification."
                );
            }

            return;
        }

        parent::ensureCommandsAreValid($connection);
    }
}
