<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('zena_projects', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('zena_projects') || !Schema::hasColumn('zena_projects', 'tenant_id')) {
            return;
        }

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            Schema::table('zena_projects', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable $e) {
                    // no-op for idempotent rollback
                }
            });
        }

        Schema::table('zena_projects', function (Blueprint $table) {
            try {
                $table->dropIndex(['tenant_id']);
            } catch (\Throwable $e) {
                // no-op for idempotent rollback
            }
        });

        Schema::table('zena_projects', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
