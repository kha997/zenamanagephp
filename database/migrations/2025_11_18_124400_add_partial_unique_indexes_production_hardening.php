<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds partial unique indexes with soft-delete condition for production hardening.
     * Ensures uniqueness only for active (non-deleted) records per tenant.
     * 
     * Partial unique constraints:
     * - projects: (tenant_id, code) WHERE deleted_at IS NULL
     * - tasks: No unique constraint (tasks don't have unique codes)
     * - documents: No unique constraint (documents don't have unique codes)
     * 
     * Uses MySQL functional indexes or generated column workaround depending on version.
     */
    public function up(): void
    {
        $mysqlVersion = $this->getMysqlVersion();
        $supportsFunctionalIndexes = version_compare($mysqlVersion, '8.0.13', '>=');

        // Projects: unique (tenant_id, code) WHERE deleted_at IS NULL
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'code')) {
            $this->addPartialUniqueIndex(
                'projects',
                'projects_tenant_code_unique',
                ['tenant_id', 'code'],
                $supportsFunctionalIndexes
            );
        }

        // Note: Tasks and Documents don't typically have unique codes/slugs
        // If needed in the future, add similar constraints here
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropPartialUniqueIndex('projects', 'projects_tenant_code_unique');
    }

    /**
     * Add partial unique index with WHERE deleted_at IS NULL condition
     * 
     * @param string $table Table name
     * @param string $indexName Index name
     * @param array $columns Columns for unique constraint
     * @param bool $supportsFunctionalIndexes Whether MySQL supports functional indexes
     */
    private function addPartialUniqueIndex(
        string $table,
        string $indexName,
        array $columns,
        bool $supportsFunctionalIndexes
    ): void {
        // Drop existing index if it exists
        $this->dropIndexIfExists($table, $indexName);

        // For MySQL, we use a workaround with generated column
        // MySQL doesn't support WHERE clause in CREATE INDEX directly
        $generatedColumn = "{$indexName}_check";
        
        // Check if generated column already exists
        if (!$this->hasColumn($table, $generatedColumn)) {
            Schema::table($table, function (Blueprint $table) use ($generatedColumn, $columns) {
                // Create generated column that is NULL when deleted_at IS NOT NULL
                // This allows unique index to work only for active records
                $columnExprs = implode(', ', array_map(function($col) {
                    return "COALESCE(`{$col}`, '')";
                }, $columns));
                
                $table->string($generatedColumn, 500)->nullable()
                    ->stored()
                    ->virtualAs("CASE WHEN `deleted_at` IS NULL THEN CONCAT({$columnExprs}) ELSE NULL END");
            });
        }
        
        // Create unique index on generated column (only non-NULL values are indexed)
        // Check if index already exists
        if (!$this->hasIndex($table, $indexName)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($indexName, $generatedColumn) {
                    $table->unique([$generatedColumn], $indexName);
                });
            } catch (\Exception $e) {
                \Log::warning("Failed to add unique index {$indexName} for {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Drop partial unique index
     */
    private function dropPartialUniqueIndex(string $table, string $indexName): void
    {
        $this->dropIndexIfExists($table, $indexName);
        
        // Also drop generated column if it exists
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
};

