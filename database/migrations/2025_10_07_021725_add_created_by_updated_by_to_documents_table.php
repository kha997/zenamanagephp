<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\SqliteCompatibleMigration;
use App\Support\DBDriver;

return new class extends Migration
{
    use SqliteCompatibleMigration;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add created_by column if it doesn't exist
            if (!Schema::hasColumn('documents', 'created_by')) {
                $this->addColumnWithPositioning($table, 'created_by', 'ulid', ['nullable' => true], 'uploaded_by');
            }
            
            // Add updated_by column if it doesn't exist
            if (!Schema::hasColumn('documents', 'updated_by')) {
                $this->addColumnWithPositioning($table, 'updated_by', 'ulid', ['nullable' => true], 'created_by');
            }
        });

        // Add foreign key constraints
        Schema::table('documents', function (Blueprint $table) {
            // Add foreign key for created_by
            $this->addForeignKeyConstraint($table, 'created_by', 'id', 'users', 'set null');
            
            // Add foreign key for updated_by
            $this->addForeignKeyConstraint($table, 'updated_by', 'id', 'users', 'set null');
        });

        // Add indexes for performance
        Schema::table('documents', function (Blueprint $table) {
            $this->addIndex($table, ['created_by'], 'documents_created_by_index');
            $this->addIndex($table, ['updated_by'], 'documents_updated_by_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Drop foreign keys first (only for MySQL)
            if (DBDriver::isMysql()) {
                $this->dropForeignKeyConstraint($table, 'created_by');
                $this->dropForeignKeyConstraint($table, 'updated_by');
            }
            
            // Drop indexes
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            
            // Drop columns
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};