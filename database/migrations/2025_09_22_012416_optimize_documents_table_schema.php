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
        Schema::table('documents', function (Blueprint $table) {
            // Add composite indexes for common query patterns
            $table->index(['tenant_id', 'status'], 'documents_tenant_status_index');
            $table->index(['project_id', 'category', 'status'], 'documents_project_category_status_index');
            $table->index(['created_at'], 'documents_created_at_index');
            
            // Add unique constraint for file hash to prevent duplicate storage
            $table->unique(['file_hash'], 'documents_file_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('documents_tenant_status_index');
            $table->dropIndex('documents_project_category_status_index');
            $table->dropIndex('documents_created_at_index');
            
            // Drop unique constraint
            $table->dropUnique('documents_file_hash_unique');
        });
    }
};