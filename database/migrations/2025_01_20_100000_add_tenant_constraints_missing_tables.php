<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\MigrationDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds tenant_id NOT NULL constraints to tables that were missing from the original migration.
     * This ensures all records belong to a tenant and prevents data leakage.
     */
    public function up(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        // Additional tables that require tenant_id NOT NULL
        $tables = [
            'task_assignments',
            'subtasks',
            'task_comments',
            'task_attachments',
            'audit_logs',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Check if tenant_id column exists
            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            // First, ensure all existing records have tenant_id
            // For records without tenant_id, we'll try to infer from related models
            $nullCount = DB::table($tableName)->whereNull('tenant_id')->count();
            
            if ($nullCount > 0) {
                // Try to infer tenant_id from related models
                if ($tableName === 'task_assignments') {
                    DB::statement("
                        UPDATE {$tableName} ta
                        INNER JOIN tasks t ON ta.task_id = t.id
                        SET ta.tenant_id = t.tenant_id
                        WHERE ta.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                    ");
                } elseif ($tableName === 'subtasks') {
                    DB::statement("
                        UPDATE {$tableName} s
                        INNER JOIN tasks t ON s.task_id = t.id
                        SET s.tenant_id = t.tenant_id
                        WHERE s.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                    ");
                } elseif ($tableName === 'task_comments') {
                    DB::statement("
                        UPDATE {$tableName} tc
                        INNER JOIN tasks t ON tc.task_id = t.id
                        SET tc.tenant_id = t.tenant_id
                        WHERE tc.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                    ");
                } elseif ($tableName === 'task_attachments') {
                    DB::statement("
                        UPDATE {$tableName} ta
                        INNER JOIN tasks t ON ta.task_id = t.id
                        SET ta.tenant_id = t.tenant_id
                        WHERE ta.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                    ");
                } elseif ($tableName === 'audit_logs') {
                    // For audit_logs, try to get tenant_id from user or project
                    DB::statement("
                        UPDATE {$tableName} al
                        LEFT JOIN users u ON al.user_id = u.id
                        LEFT JOIN projects p ON al.project_id = p.id
                        SET al.tenant_id = COALESCE(u.tenant_id, p.tenant_id)
                        WHERE al.tenant_id IS NULL AND (u.tenant_id IS NOT NULL OR p.tenant_id IS NOT NULL)
                    ");
                }
                
                // Check again after update
                $nullCount = DB::table($tableName)->whereNull('tenant_id')->count();
            }
            
            if ($nullCount > 0) {
                \Log::warning("Table {$tableName} has {$nullCount} records without tenant_id. Skipping NOT NULL constraint.");
                continue;
            }

            // Drop foreign key constraints on tenant_id before changing column
            $this->dropForeignKeyConstraints($tableName);

            // Make tenant_id NOT NULL
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('tenant_id')->nullable(false)->change();
                });
            } catch (\Exception $e) {
                \Log::warning("Failed to set tenant_id NOT NULL for {$tableName}: " . $e->getMessage());
                // Re-add foreign key constraints if change failed
                $this->addForeignKeyConstraints($tableName);
                continue;
            }

            // Re-add foreign key constraints after changing column
            $this->addForeignKeyConstraints($tableName);

            // Add index if not exists
            if (!$this->hasIndex($tableName, "{$tableName}_tenant_id_index")) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->index('tenant_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        $tables = [
            'task_assignments',
            'subtasks',
            'task_comments',
            'task_attachments',
            'audit_logs',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            // Make tenant_id nullable again
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->change();
            });
        }
    }

    /**
     * Drop foreign key constraints on tenant_id
     */
    private function dropForeignKeyConstraints(string $tableName): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        try {
            // Get all foreign key constraints on tenant_id
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = 'tenant_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$databaseName, $tableName]);
            
            foreach ($foreignKeys as $fk) {
                Schema::table($tableName, function (Blueprint $table) use ($fk) {
                    $table->dropForeign([$fk->CONSTRAINT_NAME]);
                });
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to drop foreign keys for {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Add foreign key constraints on tenant_id
     */
    private function addForeignKeyConstraints(string $tableName): void
    {
        if ($this->isSqlite()) {
            return;
        }

        if (!Schema::hasTable('tenants')) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) {
                // Check if foreign key already exists
                $connection = Schema::getConnection();
                $databaseName = $connection->getDatabaseName();
                
                $exists = DB::select("
                    SELECT COUNT(*) as count
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = 'tenant_id'
                    AND REFERENCED_TABLE_NAME = 'tenants'
                ", [$databaseName, $tableName]);
                
                if ($exists[0]->count == 0) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            \Log::warning("Failed to add foreign key for {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Check if table has index
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        if (MigrationDriver::isSqlite()) {
            return false;
        }

        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        try {
            $result = DB::select(
                "SELECT COUNT(*) as count 
                 FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = ? 
                 AND index_name = ?",
                [$databaseName, $table, $indexName]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

};
