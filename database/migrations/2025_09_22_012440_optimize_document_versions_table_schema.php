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
        Schema::table('document_versions', function (Blueprint $table) {
            // Add composite indexes for version history queries
            $table->index(['document_id', 'created_at'], 'document_versions_document_created_index');
            $table->index(['created_by', 'created_at'], 'document_versions_created_by_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('document_versions_document_created_index');
            $table->dropIndex('document_versions_created_by_created_index');
        });
    }
};