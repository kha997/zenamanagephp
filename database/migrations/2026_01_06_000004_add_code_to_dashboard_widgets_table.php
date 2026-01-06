<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_widgets')) {
            return;
        }

        $hasCode = Schema::hasColumn('dashboard_widgets', 'code');
        $hasTenantId = Schema::hasColumn('dashboard_widgets', 'tenant_id');

        if (! $hasCode) {
            Schema::table('dashboard_widgets', function (Blueprint $table) {
                $table->string('code')->nullable()->after('name');
            });
        }

        if (! $hasTenantId) {
            Schema::table('dashboard_widgets', function (Blueprint $table) {
                $table->ulid('tenant_id')->nullable()->after('permissions');
            });
        }

        if (Schema::hasColumn('dashboard_widgets', 'code')
            && ! $this->hasUniqueIndexOnColumn('dashboard_widgets', 'code')
        ) {
            Schema::table('dashboard_widgets', function (Blueprint $table) {
                $table->unique('code');
            });
        }

        if (Schema::hasTable('tenants')
            && Schema::hasColumn('dashboard_widgets', 'tenant_id')
            && ! $this->hasForeignKey('dashboard_widgets', 'tenant_id')
        ) {
            Schema::table('dashboard_widgets', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashboard_widgets')) {
            return;
        }

        $hasCode = Schema::hasColumn('dashboard_widgets', 'code');
        $uniqueIndex = $hasCode && $this->hasUniqueIndexOnColumn('dashboard_widgets', 'code');

        if ($hasCode) {
            Schema::table('dashboard_widgets', function (Blueprint $table) use ($uniqueIndex) {
                if ($uniqueIndex) {
                    $table->dropUnique(['code']);
                }

                $table->dropColumn('code');
            });
        }

        $hasTenantId = Schema::hasColumn('dashboard_widgets', 'tenant_id');
        $hasForeignKey = $hasTenantId && $this->hasForeignKey('dashboard_widgets', 'tenant_id');

        if ($hasTenantId) {
            Schema::table('dashboard_widgets', function (Blueprint $table) use ($hasForeignKey) {
                if ($hasForeignKey) {
                    $table->dropForeign(['tenant_id']);
                }

                $table->dropColumn('tenant_id');
            });
        }
    }

    private function hasUniqueIndexOnColumn(string $table, string $column): bool
    {
        $indexes = DB::select(
            'SHOW INDEX FROM ' . $table . ' WHERE Column_name = ? AND Non_unique = 0',
            [$column]
        );

        return ! empty($indexes);
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        if (empty($database)) {
            return false;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column]
        );

        return ! empty($constraints);
    }
};
