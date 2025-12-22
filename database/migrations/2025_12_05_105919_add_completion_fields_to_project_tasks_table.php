<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 206: Add completion fields to project_tasks table
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('status');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            
            // Composite index for efficient queries
            $table->index(['tenant_id', 'project_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'project_id', 'is_completed']);
            $table->dropColumn(['is_completed', 'completed_at']);
        });
    }
};
