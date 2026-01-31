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
}
