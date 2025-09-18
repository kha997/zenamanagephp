
<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait DatabaseTestTrait
 * 
 * Provides database utilities for testing
 * Handles database setup, cleanup, and assertions
 * 
 * @package Tests\Traits
 */
trait DatabaseTestTrait
{
    use RefreshDatabase;

    /**
     * Setup test database with fresh migrations and seeders
     * 
     * @return void
     */
    protected function setupTestDatabase(): void
    {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed', ['--class' => 'TestDatabaseSeeder']);
    }

    /**
     * Assert that a table exists in the database
     * 
     * @param string $table
     * @return void
     */
    protected function assertTableExists(string $table): void
    {
        $this->assertTrue(
            Schema::hasTable($table),
            "Table '{$table}' does not exist in the database."
        );
    }

    /**
     * Assert that a column exists in a table
     * 
     * @param string $table
     * @param string $column
     * @return void
     */
    protected function assertColumnExists(string $table, string $column): void
    {
        $this->assertTrue(
            Schema::hasColumn($table, $column),
            "Column '{$column}' does not exist in table '{$table}'."
        );
    }

    /**
     * Assert database has record matching conditions
     * 
     * @param string $table
     * @param array $conditions
     * @return void
     */
    protected function assertDatabaseHasRecord(string $table, array $conditions): void
    {
        $this->assertDatabaseHas($table, $conditions);
    }

    /**
     * Assert database missing record matching conditions
     * 
     * @param string $table
     * @param array $conditions
     * @return void
     */
    protected function assertDatabaseMissingRecord(string $table, array $conditions): void
    {
        $this->assertDatabaseMissing($table, $conditions);
    }

    /**
     * Get record count from table with optional conditions
     * 
     * @param string $table
     * @param array $conditions
     * @return int
     */
    protected function getRecordCount(string $table, array $conditions = []): int
    {
        $query = DB::table($table);
        
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->count();
    }

    /**
     * Truncate specified tables
     * 
     * @param array $tables
     * @return void
     */
    protected function truncateTables(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Execute raw SQL and return results
     * 
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    protected function executeRawSql(string $sql, array $bindings = []): array
    {
        return DB::select($sql, $bindings);
    }
}