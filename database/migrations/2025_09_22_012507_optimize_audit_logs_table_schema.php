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
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Add composite indexes for entity audit queries
                if (Schema::hasColumn('audit_logs', 'entity_type') && Schema::hasColumn('audit_logs', 'entity_id')) {
                    $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_logs_entity_history_index');
                }
                if (Schema::hasColumn('audit_logs', 'action')) {
                    $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Drop indexes
                $table->dropIndex('audit_logs_entity_history_index');
                $table->dropIndex('audit_logs_action_created_index');
            });
        }
    }
};