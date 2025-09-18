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
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->string('priority')->nullable()->after('assignment_type');
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('priority');
            $table->string('assigned_by')->nullable()->after('estimated_hours');
            $table->timestamp('due_date')->nullable()->after('assigned_by');
            
            // Add foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['priority']);
            $table->index(['due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['assigned_by']);
            
            // Drop indexes
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['due_date']);
            
            // Drop columns
            $table->dropColumn([
                'tenant_id', 'priority', 
                'estimated_hours', 'assigned_by', 'due_date'
            ]);
        });
    }
};