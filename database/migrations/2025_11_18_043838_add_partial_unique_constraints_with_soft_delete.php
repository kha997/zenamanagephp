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
     * Adds partial unique constraints with soft delete condition.
     * For MySQL 8.0+, uses functional indexes for WHERE deleted_at IS NULL.
     * For older MySQL, uses a workaround with generated column.
     * 
     * Partial unique constraints ensure uniqueness only for active (non-deleted) records:
     * - projects: (tenant_id, code) WHERE deleted_at IS NULL
     * - clients: (tenant_id, name) WHERE deleted_at IS NULL
     * - template_sets: (tenant_id, code) WHERE deleted_at IS NULL
     */
    public function up(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        $mysqlVersion = $this->getMysqlVersion();
        $supportsFunctionalIndexes = version_compare($mysqlVersion, '8.0.13', '>=');

        // Projects: unique (tenant_id, code) WHERE deleted_at IS NULL
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'code')) {
            $this->addPartialUniqueConstraint(
                'projects',
                'projects_tenant_code_unique_partial',
                ['tenant_id', 'code'],
                $supportsFunctionalIndexes
            );
        }

        // Clients: unique (tenant_id, name) WHERE deleted_at IS NULL
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'name')) {
            $this->addPartialUniqueConstraint(
                'clients',
                'clients_tenant_name_unique_partial',
                ['tenant_id', 'name'],
                $supportsFunctionalIndexes
            );
        }

        // Template Sets: unique (tenant_id, code) WHERE deleted_at IS NULL
        if (Schema::hasTable('template_sets') && Schema::hasColumn('template_sets', 'code')) {
            $this->addPartialUniqueConstraint(
                'template_sets',
                'template_sets_tenant_code_unique_partial',
                ['tenant_id', 'code'],
                $supportsFunctionalIndexes
            );
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
        // Drop partial unique constraints
        $this->dropPartialUniqueConstraint('projects', 'projects_tenant_code_unique_partial');
        $this->dropPartialUniqueConstraint('clients', 'clients_tenant_name_unique_partial');
        $this->dropPartialUniqueConstraint('template_sets', 'template_sets_tenant_code_unique_partial');
    }

    /**
     * Add partial unique constraint with WHERE deleted_at IS NULL condition
     * 
     * @param string $table Table name
     * @param string $indexName Index name
     * @param array $columns Columns for unique constraint
     * @param bool $supportsFunctionalIndexes Whether MySQL supports functional indexes
     */
    private function addPartialUniqueConstraint(
        string $table,
        string $indexName,
        array $columns,
        bool $supportsFunctionalIndexes
    ): void {
        // Drop existing index if it exists
        $this->dropIndexIfExists($table, $indexName);

        if ($supportsFunctionalIndexes) {
            // MySQL 8.0.13+ supports functional indexes
            // However, MySQL doesn't support WHERE clause in CREATE INDEX
            // Use workaround: include deleted_at in index and use generated column
            $columnList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
            
            // Create generated column that is NULL when deleted_at IS NOT NULL
            $generatedColumn = "{$indexName}_check";
            
            if (!$this->hasColumn($table, $generatedColumn)) {
                Schema::table($table, function (Blueprint $table) use ($generatedColumn, $columns) {
                    // Generated column: NULL when deleted_at IS NOT NULL, otherwise concatenated values
                    $concatExpr = implode(', ', array_map(fn($col) => "COALESCE(`{$col}`, '')", $columns));
                    $table->string($generatedColumn, 255)->nullable()
                        ->stored()
                        ->virtualAs("CASE WHEN `deleted_at` IS NULL THEN CONCAT({$concatExpr}) ELSE NULL END");
                });
            }
            
            // Create unique index on generated column (only non-NULL values are indexed)
            Schema::table($table, function (Blueprint $table) use ($indexName, $generatedColumn) {
                $table->unique([$generatedColumn], $indexName);
            });
        } else {
            // For older MySQL, use workaround with generated column
            // Create a generated column that is NULL when deleted_at IS NOT NULL
            $generatedColumn = "{$indexName}_check";
            
            // Check if generated column already exists
            if (!$this->hasColumn($table, $generatedColumn)) {
                Schema::table($table, function (Blueprint $table) use ($generatedColumn, $columns) {
                    // Create generated column that is NULL when deleted_at IS NOT NULL
                    // This allows unique index to work only for active records
                    $table->string($generatedColumn)->nullable()
                        ->stored()
                        ->virtualAs("CASE WHEN deleted_at IS NULL THEN CONCAT(" . 
                            implode(', ', array_map(fn($col) => "COALESCE({$col}, '')", $columns)) . 
                            ") ELSE NULL END");
                });
            }
            
            // Create unique index on generated column
            Schema::table($table, function (Blueprint $table) use ($indexName, $generatedColumn) {
                $table->unique([$generatedColumn], $indexName);
            });
        }
    }

    /**
     * Drop partial unique constraint
     */
    private function dropPartialUniqueConstraint(string $table, string $indexName): void
    {
        $this->dropIndexIfExists($table, $indexName);
        
        // Also drop generated column if it exists (for older MySQL workaround)
        $generatedColumn = "{$indexName}_check";
        if ($this->hasColumn($table, $generatedColumn)) {
            Schema::table($table, function (Blueprint $table) use ($generatedColumn) {
                $table->dropColumn($generatedColumn);
            });
        }
    }

    /**
     * Get MySQL version
     */
    private function getMysqlVersion(): string
    {
        try {
            $result = DB::selectOne("SELECT VERSION() as version");
            return $result->version ?? '5.7.0';
        } catch (\Exception $e) {
            // Default to older version if can't determine
            return '5.7.0';
        }
    }

    /**
     * Check if table has column
     */
    private function hasColumn(string $table, string $column): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return Schema::hasColumn($table, $column);
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->hasIndex($table, $indexName)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Exception $e) {
                // Index might not exist or might be a unique constraint
                try {
                    DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
                } catch (\Exception $e2) {
                    // Ignore if index doesn't exist
                }
            }
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
