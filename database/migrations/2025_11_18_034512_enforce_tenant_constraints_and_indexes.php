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
     * Enforces tenant constraints and adds performance indexes:
     * - Ensures tenant_id NOT NULL (after backfill)
     * - Adds composite indexes for cursor-based pagination: (tenant_id, created_at), (tenant_id, id)
     * - Adds unique composite indexes where needed: (tenant_id, code) WHERE deleted_at IS NULL
     */
    public function up(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        // Main tables that require tenant constraints
        $tables = [
            'projects',
            'tasks',
            'documents',
            'clients',
            'quotes',
            'change_requests',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            // 1. Ensure tenant_id is NOT NULL (after backfill)
            $this->ensureTenantIdNotNull($tableName);

            // 2. Add composite indexes for cursor-based pagination
            $this->addCursorPaginationIndexes($tableName);

            // 3. Add unique composite indexes where applicable
            $this->addUniqueCompositeIndexes($tableName);
        }
    }

    /**
     * Ensure tenant_id is NOT NULL
     */
    private function ensureTenantIdNotNull(string $tableName): void
    {
        // Check if there are any NULL tenant_id records
        $nullCount = DB::table($tableName)->whereNull('tenant_id')->count();
        
        if ($nullCount > 0) {
            \Log::warning("Table {$tableName} has {$nullCount} records without tenant_id. Skipping NOT NULL constraint.");
            return;
        }

        // Check current column definition (MySQL/MariaDB only)
        // SQLite doesn't support information_schema, so skip for SQLite
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, skip
            return;
        }

        try {
            $columnInfo = DB::select("
                SELECT IS_NULLABLE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = 'tenant_id'
            ", [$tableName]);

            if (!empty($columnInfo) && $columnInfo[0]->IS_NULLABLE === 'YES') {
                try {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->string('tenant_id')->nullable(false)->change();
                    });
                } catch (\Exception $e) {
                    \Log::warning("Failed to set tenant_id NOT NULL for {$tableName}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to check column definition for {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Add composite indexes for cursor-based pagination
     */
    private function addCursorPaginationIndexes(string $tableName): void
    {
        // Index: (tenant_id, created_at) for cursor pagination by time
        $indexName1 = "idx_{$tableName}_tenant_created";
        if (!$this->hasIndex($tableName, $indexName1)) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName1) {
                    $table->index(['tenant_id', 'created_at'], $indexName1);
                });
            } catch (\Exception $e) {
                \Log::warning("Failed to add {$indexName1} for {$tableName}: " . $e->getMessage());
            }
        }

        // Index: (tenant_id, id) for cursor pagination by ID/ULID
        // Check if table uses ULID (string) or auto-increment (integer)
        $idColumn = 'id';
        $indexName2 = "idx_{$tableName}_tenant_id";
        // Also check for alternative naming (projects_tenant_id_index)
        $altIndexName = "{$tableName}_tenant_id_index";
        if (!$this->hasIndex($tableName, $indexName2) && !$this->hasIndex($tableName, $altIndexName)) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName2, $idColumn) {
                    $table->index(['tenant_id', $idColumn], $indexName2);
                });
            } catch (\Exception $e) {
                \Log::warning("Failed to add {$indexName2} for {$tableName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Add unique composite indexes where applicable
     */
    private function addUniqueCompositeIndexes(string $tableName): void
    {
        // Projects: unique (tenant_id, code) WHERE deleted_at IS NULL
        if ($tableName === 'projects' && Schema::hasColumn('projects', 'code')) {
            $indexName = 'projects_tenant_code_unique';
            if (!$this->hasIndex('projects', $indexName)) {
                try {
                    Schema::table('projects', function (Blueprint $table) use ($indexName) {
                        $table->unique(['tenant_id', 'code'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning("Failed to add {$indexName}: " . $e->getMessage());
                }
            }
        }

        // Tasks: Check if tasks have a slug or code field
        // For now, we'll skip tasks as they don't typically have unique codes
        
        // Documents: Check if documents have a slug or code field
        // For now, we'll skip documents as they don't typically have unique codes
        
        // Clients: unique (tenant_id, name) WHERE deleted_at IS NULL (if name should be unique)
        // Uncomment if needed:
        // if ($tableName === 'clients' && Schema::hasColumn('clients', 'name')) {
        //     $indexName = 'clients_tenant_name_unique';
        //     if (!$this->hasIndex('clients', $indexName)) {
        //         Schema::table('clients', function (Blueprint $table) use ($indexName) {
        //             $table->unique(['tenant_id', 'name'], $indexName);
        //         });
        //     }
        // }
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
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Drop cursor pagination indexes
            $indexName1 = "idx_{$tableName}_tenant_created";
            if ($this->hasIndex($tableName, $indexName1)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName1) {
                    $table->dropIndex($indexName1);
                });
            }

            $indexName2 = "idx_{$tableName}_tenant_id";
            if ($this->hasIndex($tableName, $indexName2)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName2) {
                    $table->dropIndex($indexName2);
                });
            }

            // Drop unique composite indexes
            if ($tableName === 'projects') {
                $indexName = 'projects_tenant_code_unique';
                if ($this->hasIndex('projects', $indexName)) {
                    Schema::table('projects', function (Blueprint $table) use ($indexName) {
                        $table->dropUnique($indexName);
                    });
                }
            }

            // Make tenant_id nullable again (optional - be careful in production)
            // Uncomment if you want to allow NULL tenant_id again:
            // if (Schema::hasColumn($tableName, 'tenant_id')) {
            //     Schema::table($tableName, function (Blueprint $table) {
            //         $table->string('tenant_id')->nullable()->change();
            //     });
            // }
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

        if (!Schema::hasTable($table)) {
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

    /**
     * Determine if the current connection is SQLite.
     */
};
