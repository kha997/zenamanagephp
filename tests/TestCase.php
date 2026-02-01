<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        $this->prepareSqliteDatabaseFile();
        parent::setUp();
        $this->ensureSqliteSubmittalsTable();
    }

    private function prepareSqliteDatabaseFile(): void
    {
        if (env('DB_CONNECTION') !== 'sqlite') {
            return;
        }

        $databasePath = env('DB_DATABASE') ?: config('database.connections.sqlite.database');

        if (!$databasePath || $databasePath === ':memory:') {
            return;
        }

        $directory = dirname($databasePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!file_exists($databasePath)) {
            touch($databasePath);
        }
    }

    private function ensureSqliteSubmittalsTable(): void
    {
        if (!Schema::hasTable('submittals')) {
            Schema::create('submittals', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('package_no');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('submittal_type')->nullable();
                $table->string('status')->default('draft');
                $table->string('submitted_by');
                $table->string('submittal_number');
                $table->timestamps();
            });
        }

        $this->ensureSqliteZenaRbacTables();
    }

    private function ensureSqliteZenaRbacTables(): void
    {
        Schema::dropIfExists('zena_role_permissions');
        Schema::dropIfExists('zena_user_roles');
        Schema::dropIfExists('zena_roles');
        Schema::dropIfExists('zena_permissions');

        Schema::create('zena_permissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('action');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zena_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->string('scope')->default('system');
            $table->boolean('allow_override')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zena_role_permissions', function (Blueprint $table) {
            $table->string('role_id');
            $table->string('permission_id');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();
        });

        Schema::create('zena_user_roles', function (Blueprint $table) {
            $table->string('user_id');
            $table->string('role_id');
            $table->timestamps();
        });
    }

    protected function assertTestingDatabaseMode(): void
    {
        $mode = $this->getInvariantsDatabaseMode();
        $environment = app()->environment();
        $defaultConnection = config('database.default');
        $sqliteDatabase = config('database.connections.sqlite.database') ?? 'null';

        $mysqlConfig = config('database.connections.mysql') ?? [];
        $mysqlHost = $mysqlConfig['host'] ?? null;
        $mysqlPort = $mysqlConfig['port'] ?? null;
        $mysqlDatabase = $mysqlConfig['database'] ?? null;
        $mysqlUsername = $mysqlConfig['username'] ?? null;

        $diag = sprintf(
            'env=%s mode=%s default=%s sqlite=%s mysql_host=%s mysql_port=%s mysql_db=%s mysql_user=%s',
            $environment,
            $mode,
            $defaultConnection,
            $sqliteDatabase,
            $mysqlHost ?? 'null',
            $mysqlPort ?? 'null',
            $mysqlDatabase ?? 'null',
            $mysqlUsername ?? 'null'
        );

        $this->assertSame(
            'testing',
            $environment,
            "Zena invariants must run in the testing environment ($diag)."
        );

        if ($mode === 'mysql') {
            $expectedHost = $this->resolveInvariantEnvValue('DB_HOST', 'mysql');
            $expectedPort = $this->resolveInvariantEnvValue('DB_PORT', '3306');
            $expectedDatabase = $this->resolveInvariantEnvValue('DB_DATABASE', 'zenamanage_test');
            $expectedUsername = $this->resolveInvariantEnvValue('DB_USERNAME', 'root');

            $this->assertSame(
                'mysql',
                $defaultConnection,
                "Zena invariants mysql mode must use the mysql connection ($diag)."
            );

            $this->assertSame(
                $expectedHost,
                (string) ($mysqlHost ?? ''),
                "Zena invariants mysql host must match the exported DB_HOST ($diag)."
            );

            $this->assertSame(
                $expectedPort,
                (string) ($mysqlPort ?? ''),
                "Zena invariants mysql port must match the exported DB_PORT ($diag)."
            );

            $this->assertSame(
                $expectedDatabase,
                (string) ($mysqlDatabase ?? ''),
                "Zena invariants mysql database must match the exported DB_DATABASE ($diag)."
            );

            $this->assertSame(
                $expectedUsername,
                (string) ($mysqlUsername ?? ''),
                "Zena invariants mysql username must match the exported DB_USERNAME ($diag)."
            );
        } else {
            $this->assertSame(
                'sqlite',
                $defaultConnection,
                "Zena invariants must use the sqlite connection ($diag)."
            );

            $this->assertTrue(
                str_ends_with($sqliteDatabase, 'zenamanage_test.sqlite'),
                "Zena invariants must point to the sqlite test file ($diag)."
            );
        }
    }

    protected function getInvariantsDatabaseMode(): string
    {
        $mode = getenv('ZENA_INVARIANTS_DB');
        if (!$mode) {
            return 'sqlite';
        }

        return strtolower($mode);
    }

    private function resolveInvariantEnvValue(string $name, string $default): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}
