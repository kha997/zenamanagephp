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
            // Add created_by column if it doesn't exist
            if (!Schema::hasColumn('documents', 'created_by')) {
                $table->ulid('created_by')->nullable()->after('uploaded_by');
            }
            
            // Add updated_by column if it doesn't exist
            if (!Schema::hasColumn('documents', 'updated_by')) {
                $table->ulid('updated_by')->nullable()->after('created_by');
            }
        });

        // Add foreign key constraints
        Schema::table('documents', function (Blueprint $table) {
            // Add foreign key for created_by
            if (!$this->foreignKeyExists('documents', 'documents_created_by_foreign')) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            // Add foreign key for updated_by
            if (!$this->foreignKeyExists('documents', 'documents_updated_by_foreign')) {
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add indexes for performance
        Schema::table('documents', function (Blueprint $table) {
            if (!$this->indexExists('documents', 'documents_created_by_index')) {
                $table->index('created_by');
            }
            
            if (!$this->indexExists('documents', 'documents_updated_by_index')) {
                $table->index('updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            
            // Drop indexes
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            
            // Drop columns
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }

    /**
     * Check if a foreign key constraint exists
     */
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $foreignKeys = \DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
        ", [config('database.connections.mysql.database'), $table, $constraint]);

        return count($foreignKeys) > 0;
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        // Skip index checks for SQLite (E2E testing)
        if (config('database.default') === 'sqlite') {
            return false;
        }

        $indexes = \DB::select("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND INDEX_NAME = ?
        ", [config('database.connections.mysql.database'), $table, $index]);

        return count($indexes) > 0;
    }
};