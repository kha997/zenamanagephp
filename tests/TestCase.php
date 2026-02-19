<?php

namespace Tests;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Automatically append CSRF tokens to HTTP form requests unless disabled.
     *
     * Allows tests to opt out when asserting CSRF failure scenarios.
     *
     * @var bool
     */
    protected bool $autoAppendCsrfToken = true;

    /**
     * Override the base call to inject CSRF tokens into supported requests.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->shouldAutoAppendCsrfToken() && $this->shouldAppendCsrfToken($method, $server)) {
            $parameters = $this->ensureCsrfToken($parameters);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function shouldAutoAppendCsrfToken(): bool
    {
        return $this->autoAppendCsrfToken === true;
    }

    protected function shouldAppendCsrfToken(string $method, array $server): bool
    {
        $method = strtoupper($method);

        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        $acceptHeader = strtolower($server['HTTP_ACCEPT'] ?? '');
        $contentType = strtolower($server['CONTENT_TYPE'] ?? '');

        if (str_contains($acceptHeader, 'application/json') || str_contains($contentType, 'application/json')) {
            return false;
        }

        return true;
    }

    protected function ensureCsrfToken(array $parameters): array
    {
        if (array_key_exists('_token', $parameters)) {
            return $parameters;
        }

        $token = csrf_token();

        if (!$token) {
            return $parameters;
        }

        return ['_token' => $token] + $parameters;
    }

    protected function setUp(): void
    {
        $this->prepareSqliteDatabaseFile();
        parent::setUp();
        $this->withoutVite();
        $this->ensureTestingSchema();
        $this->registerArrayBindingWatch();
        $this->ensureSqliteSubmittalsTable();
        $this->ensureSqliteDocumentsBackupTable();
        $this->ensureInteractionLogsTable();
        $this->ensureProjectPhasesTable();
        $this->ensureProjectTasksTable();
    }

    private function ensureTestingSchema(): void
    {
        if (Schema::hasTable('tenants')) {
            return;
        }

        RefreshDatabaseState::$migrated = false;
        Artisan::call('migrate:fresh', ['--env' => 'testing']);
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

    private function ensureSqliteDocumentsBackupTable(): void
    {
        if (env('DB_CONNECTION') !== 'sqlite') {
            return;
        }

        if (Schema::hasTable('documents_backup')) {
            return;
        }

        Schema::create('documents_backup', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id')->nullable();
            $table->ulid('uploaded_by');
            $table->string('name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('file_hash');
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('active');
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->ulid('parent_document_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('deprecated_notice')->nullable();
            $table->ulid('tenant_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
        });
    }

    private function ensureInteractionLogsTable(): void
    {
        if (Schema::hasTable('interaction_logs')) {
            return;
        }

        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('project_id');
            $table->string('linked_task_id')->nullable();
            $table->string('type');
            $table->text('description');
            $table->string('tag_path')->nullable();
            $table->string('visibility');
            $table->boolean('client_approved')->default(false);
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function ensureProjectPhasesTable(): void
    {
        if (Schema::hasTable('project_phases')) {
            return;
        }

        Schema::create('project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_phase_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function ensureProjectTasksTable(): void
    {
        if (Schema::hasTable('project_tasks')) {
            return;
        }

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('phase_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days')->default(0);
            $table->float('progress_percent')->default(0.0);
            $table->string('status')->default('pending');
            $table->string('conditional_tag')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_task_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function apiTenantHeaders(string $token, string $tenantId, array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $extra);
    }

    protected function apiPostTenant(User $user, string $tenantId, string $uri, array $data, string $token, array $headers = []): TestResponse
    {
        return $this->postJson($uri, $data, $this->apiTenantHeaders($token, $tenantId, $headers));
    }

    protected function apiGetTenant(User $user, string $tenantId, string $uri, string $token, array $headers = []): TestResponse
    {
        return $this->getJson($uri, $this->apiTenantHeaders($token, $tenantId, $headers));
    }

    protected function apiPutTenant(User $user, string $tenantId, string $uri, array $data, string $token, array $headers = []): TestResponse
    {
        return $this->putJson($uri, $data, $this->apiTenantHeaders($token, $tenantId, $headers));
    }

    protected function apiDeleteTenant(User $user, string $tenantId, string $uri, string $token, array $headers = []): TestResponse
    {
        return $this->deleteJson($uri, [], $this->apiTenantHeaders($token, $tenantId, $headers));
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

            $usesFixedSqliteFile = str_ends_with($sqliteDatabase, 'zenamanage_test.sqlite');
            $usesBootstrapSqliteFile = (bool) preg_match(
                '#/storage/framework/testing/phpunit_[^/]+\.sqlite$#',
                str_replace('\\', '/', $sqliteDatabase)
            );

            $this->assertTrue(
                $usesFixedSqliteFile || $usesBootstrapSqliteFile,
                "Zena invariants must point to a sqlite test file ($diag)."
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
