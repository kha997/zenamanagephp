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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add composite indexes for entity audit queries
            $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_logs_entity_history_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('audit_logs_entity_history_index');
            $table->dropIndex('audit_logs_action_created_index');
        });
    }
};