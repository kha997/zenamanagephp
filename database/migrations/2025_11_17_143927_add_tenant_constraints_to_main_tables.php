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
     * Adds tenant_id NOT NULL constraints to main tables for multi-tenant isolation.
     * This ensures all records belong to a tenant and prevents data leakage.
     */
    public function up(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        // Main tables that require tenant_id NOT NULL
        $tables = [
            'projects',
            'tasks',
            'documents',
            'clients',
            'quotes',
            'change_requests',
            'components',
            'rfis',
            'ncrs',
            'qc_plans',
            'qc_inspections',
            'teams',
            'notifications',
            'templates',
            'template_sets',
            'invitations',
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
            // Skip if table doesn't have owner_id column
            if (Schema::hasColumn($tableName, 'owner_id')) {
                try {
                    DB::statement("UPDATE {$tableName} SET tenant_id = (SELECT tenant_id FROM users WHERE users.id = {$tableName}.owner_id LIMIT 1) WHERE tenant_id IS NULL AND owner_id IS NOT NULL");
                } catch (\Exception $e) {
                    \Log::warning("Failed to update tenant_id from owner_id for {$tableName}: " . $e->getMessage());
                }
            }
            
            // For tasks, try to get tenant_id from project
            if ($tableName === 'tasks' && Schema::hasColumn('tasks', 'project_id')) {
                try {
                    DB::statement("UPDATE tasks SET tenant_id = (SELECT tenant_id FROM projects WHERE projects.id = tasks.project_id LIMIT 1) WHERE tenant_id IS NULL AND project_id IS NOT NULL");
                } catch (\Exception $e) {
                    \Log::warning("Failed to update tenant_id from project_id for tasks: " . $e->getMessage());
                }
            }
            
            // For records that still don't have tenant_id, we need to handle them
            // For now, we'll skip making it NOT NULL if there are NULL values
            $nullCount = DB::table($tableName)->whereNull('tenant_id')->count();
            
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
            $indexName = "{$tableName}_tenant_id_index";
            if (!$this->hasIndex($tableName, $indexName)) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->index('tenant_id', $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning("Failed to add index {$indexName} for {$tableName}: " . $e->getMessage());
                }
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
            'projects',
            'tasks',
            'documents',
            'clients',
            'quotes',
            'change_requests',
            'components',
            'rfis',
            'ncrs',
            'qc_plans',
            'qc_inspections',
            'teams',
            'notifications',
            'templates',
            'template_sets',
            'invitations',
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
        if (MigrationDriver::isSqlite()) {
            return;
        }

        if (!Schema::hasTable('tenants')) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
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
