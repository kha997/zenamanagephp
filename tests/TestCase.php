<?php

namespace Tests;

use App\Models\Project;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    private static ?int $projectCodeHookDispatcherId = null;

    protected function setUp(): void
    {
        $this->prepareSqliteDatabaseFile();
        parent::setUp();
        $this->registerArrayBindingWatch();
        $this->registerProjectCodeHook();
        $this->ensureSqliteSubmittalsTable();
    }

    private function registerArrayBindingWatch(): void
    {
        if (!env('DEBUG_ARRAY_BINDINGS')) {
            return;
        }

        DB::listen(function ($query) {
            $bindings = $query->bindings ?? [];
            foreach ($bindings as $binding) {
                if (is_array($binding)) {
                    $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
                        ->filter(fn ($frame) => isset($frame['file']) && str_starts_with($frame['file'], base_path()))
                        ->reject(fn ($frame) => str_contains($frame['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR))
                        ->map(fn ($frame) => ($frame['file'] ?? 'unknown') . ':' . ($frame['line'] ?? '?'))
                        ->values()
                        ->all();

                    throw new \RuntimeException(sprintf(
                        'Array binding detected for SQL [%s] with bindings %s traces [%s]',
                        $query->sql ?? 'unknown',
                        json_encode($bindings),
                        implode(' >> ', $backtrace)
                    ));
                }
            }
        });
    }

    private function registerProjectCodeHook(): void
    {
        if (!app()->environment('testing')) {
            return;
        }

        $dispatcher = Project::getEventDispatcher();

        if ($dispatcher === null) {
            return;
        }

        $dispatcherId = spl_object_id($dispatcher);

        if (self::$projectCodeHookDispatcherId === $dispatcherId) {
            return;
        }

        Project::creating(function (Project $project) {
            if (empty($project->code)) {
                $project->code = 'PRJ-' . strtoupper(Str::random(8));
            }
        });

        self::$projectCodeHookDispatcherId = $dispatcherId;
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
                $table->string('package_no')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('submittal_type')->nullable();
                $table->string('specification_section')->nullable();
                $table->date('due_date')->nullable();
                $table->string('file_url')->nullable();
                $table->string('contractor')->nullable();
                $table->string('manufacturer')->nullable();
                $table->string('status')->default('draft');
                $table->string('submitted_by');
                $table->timestamp('submitted_at')->nullable();
                $table->string('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_comments')->nullable();
                $table->text('review_notes')->nullable();
                $table->string('submittal_number')->nullable();
                $table->string('created_by')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_comments')->nullable();
                $table->string('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('rejection_comments')->nullable();
                $table->json('attachments')->nullable();
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

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
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
