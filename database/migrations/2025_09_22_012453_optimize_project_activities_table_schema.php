<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_activities', function (Blueprint $table) {
            // Add tenant_id column for proper tenant isolation
            // Round 216: Use ulid() to match tenants.id type for SQLite FK compatibility
            // Both columns must use the same type definition for foreign keys to work in SQLite
            $table->ulid('tenant_id')->nullable()->after('project_id');
            
            // Add composite indexes for entity history queries
            $table->index(['entity_type', 'entity_id', 'created_at'], 'project_activities_entity_history_index');
            $table->index(['action', 'created_at'], 'project_activities_action_created_index');
            $table->index(['tenant_id', 'created_at'], 'project_activities_tenant_created_index');
        });
        
        // Add foreign key constraint separately for better SQLite compatibility
        // Round 216: Create FK after column to ensure type matching
        if (DBDriver::supportsForeignKeys()) {
            Schema::table('project_activities', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_activities', function (Blueprint $table) {
            // Drop foreign key constraint
            if (DBDriver::isMysql()) {
                $table->dropForeign(['tenant_id']);
            }
            
            // Drop indexes
            $table->dropIndex('project_activities_entity_history_index');
            $table->dropIndex('project_activities_action_created_index');
            $table->dropIndex('project_activities_tenant_created_index');
            
            // Drop column
            $table->dropColumn('tenant_id');
        });
    }
};