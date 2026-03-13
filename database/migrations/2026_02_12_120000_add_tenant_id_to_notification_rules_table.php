<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('project_id');

            $table->foreign('tenant_id', 'notification_rules_tenant_id_foreign')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id'], 'notification_rules_tenant_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            Schema::table('notification_rules', function (Blueprint $table) {
                try {
                    $table->dropForeign('notification_rules_tenant_id_foreign');
                } catch (\Throwable $e) {
                    // no-op for idempotent rollback
                }
            });
        }

        Schema::table('notification_rules', function (Blueprint $table) {
            try {
                $table->dropIndex('notification_rules_tenant_id_index');
            } catch (\Throwable $e) {
                // no-op for idempotent rollback
            }
        });

        if (Schema::hasColumn('notification_rules', 'tenant_id')) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
