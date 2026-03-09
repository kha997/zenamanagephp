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
        Schema::table('zena_notifications', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('user_id');
            
            // Add foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Add index
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('zena_notifications')) {
            return;
        }

        if (!Schema::hasColumn('zena_notifications', 'tenant_id')) {
            return;
        }

        try {
            Schema::table('zena_notifications', function (Blueprint $table) {
                // Drop foreign key first
                $table->dropForeign(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('zena_notifications', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'type']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('zena_notifications', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('zena_notifications', function (Blueprint $table) {
                // Drop column
                $table->dropColumn('tenant_id');
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }
};
