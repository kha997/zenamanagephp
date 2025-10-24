<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Migration Helper Trait for SQLite Compatibility
 * 
 * Provides methods to handle database-specific operations
 * that differ between MySQL and SQLite.
 */
trait SqliteCompatibleMigration
{
    /**
     * Add a column with conditional positioning for SQLite compatibility
     *
     * @param Blueprint $table
     * @param string $column
     * @param string $type
     * @param array $options
     * @param string|null $after
     * @return void
     */
    protected function addColumnWithPositioning(Blueprint $table, string $column, string $type, array $options = [], ?string $after = null): void
    {
        if (!DBDriver::isSqlite() && $after) {
            // MySQL supports ->after() positioning
            $columnBuilder = $table->$type($column);
            if (isset($options['nullable']) && $options['nullable']) {
                $columnBuilder = $columnBuilder->nullable();
            }
            $columnBuilder->after($after);
        } else {
            // SQLite doesn't support ->after(), just add the column
            $columnBuilder = $table->$type($column);
            if (isset($options['nullable']) && $options['nullable']) {
                $columnBuilder = $columnBuilder->nullable();
            }
        }
    }

    /**
     * Add a foreign key constraint with SQLite compatibility
     *
     * @param Blueprint $table
     * @param string $column
     * @param string $references
     * @param string $on
     * @param string $onDelete
     * @return void
     */
    protected function addForeignKeyConstraint(Blueprint $table, string $column, string $references, string $on, string $onDelete = 'cascade'): void
    {
        if (DBDriver::supportsForeignKeys()) {
            $table->foreign($column)->references($references)->on($on)->onDelete($onDelete);
        }
    }

    /**
     * Drop a foreign key constraint with SQLite compatibility
     *
     * @param Blueprint $table
     * @param string $column
     * @return void
     */
    protected function dropForeignKeyConstraint(Blueprint $table, string $column): void
    {
        if (DBDriver::supportsForeignKeys()) {
            $table->dropForeign([$column]);
        }
    }

    /**
     * Execute a database-specific statement
     *
     * @param string $mysqlStatement
     * @param string|null $sqliteStatement
     * @return void
     */
    protected function executeDatabaseSpecificStatement(string $mysqlStatement, ?string $sqliteStatement = null): void
    {
        if (DBDriver::isMysql()) {
            DB::statement($mysqlStatement);
        } elseif (DBDriver::isSqlite() && $sqliteStatement) {
            DB::statement($sqliteStatement);
        }
    }

    /**
     * Check if a constraint exists (MySQL only)
     *
     * @param string $table
     * @param string $constraintName
     * @return bool
     */
    protected function constraintExists(string $table, string $constraintName): bool
    {
        if (DBDriver::isSqlite()) {
            // SQLite doesn't have a reliable way to check constraints
            // For indexes, we can check using PRAGMA
            try {
                $indexes = DB::select("PRAGMA index_list($table)");
                foreach ($indexes as $index) {
                    if ($index->name === $constraintName) {
                        return true;
                    }
                }
                return false;
            } catch (\Exception $e) {
                return false;
            }
        }

        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            return count($constraints) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add a unique constraint with SQLite compatibility
     *
     * @param Blueprint $table
     * @param array $columns
     * @param string $name
     * @return void
     */
    protected function addUniqueConstraint(Blueprint $table, array $columns, string $name): void
    {
        if (!$this->constraintExists($table->getTable(), $name)) {
            $table->unique($columns, $name);
        }
    }

    /**
     * Add an index with SQLite compatibility
     *
     * @param Blueprint $table
     * @param array $columns
     * @param string $name
     * @return void
     */
    protected function addIndex(Blueprint $table, array $columns, string $name): void
    {
        if (!$this->constraintExists($table->getTable(), $name)) {
            $table->index($columns, $name);
        }
    }
}
