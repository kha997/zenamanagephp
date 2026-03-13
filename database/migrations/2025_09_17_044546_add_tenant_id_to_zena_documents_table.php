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
        Schema::table('zena_documents', function (Blueprint $table) {
            $table->ulid('tenant_id')->nullable()->after('project_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('zena_documents')) {
            return;
        }

        if (!Schema::hasColumn('zena_documents', 'tenant_id')) {
            return;
        }

        try {
            Schema::table('zena_documents', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('zena_documents', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }

        try {
            Schema::table('zena_documents', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }
};
