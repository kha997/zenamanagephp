<?php

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
        Schema::table('task_dependencies', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            
            // Add foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Add index
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('task_dependencies') || !Schema::hasColumn('task_dependencies', 'tenant_id')) {
            return;
        }

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            Schema::table('task_dependencies', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable $e) {
                    // no-op for idempotent rollback
                }
            });
        }

        Schema::table('task_dependencies', function (Blueprint $table) {
            try {
                $table->dropIndex(['tenant_id']);
            } catch (\Throwable $e) {
                // no-op for idempotent rollback
            }
        });

        Schema::table('task_dependencies', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
