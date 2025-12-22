<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('task_assignments', 'team_id')) {
                $table->ulid('team_id')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('task_assignments', 'assignment_type')) {
                $table->string('assignment_type')->default('user')->after('team_id'); // user, team
            }
            
            if (!Schema::hasColumn('task_assignments', 'created_by')) {
                $table->ulid('created_by')->nullable()->after('notes');
            }
            
            if (!Schema::hasColumn('task_assignments', 'updated_by')) {
                $table->ulid('updated_by')->nullable()->after('created_by');
            }

            // Add foreign keys
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_team_id_foreign')) {
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_created_by_foreign')) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_updated_by_foreign')) {
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }

            // Add indexes
            if (!$this->indexExists('task_assignments', 'task_assignments_team_id_index')) {
                $table->index('team_id');
            }
            
            if (!$this->indexExists('task_assignments', 'task_assignments_assignment_type_index')) {
                $table->index('assignment_type');
            }
            
            if (!$this->indexExists('task_assignments', 'task_assignments_created_by_index')) {
                $table->index('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            // Drop foreign keys
            if (DBDriver::isMysql()) {
                $table->dropForeign(['team_id']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['created_by']);
            }
            if (DBDriver::isMysql()) {
                $table->dropForeign(['updated_by']);
            }
            
            // Drop indexes
            $table->dropIndex(['team_id']);
            $table->dropIndex(['assignment_type']);
            $table->dropIndex(['created_by']);
            
            // Drop columns
            $table->dropColumn(['team_id', 'assignment_type', 'created_by', 'updated_by']);
        });
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list({$table})");
            return collect($foreignKeys)->contains('id', $foreignKey);
        } elseif ($driver === 'mysql') {
            $result = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}' 
                AND CONSTRAINT_NAME = '{$foreignKey}'
            ");
            return $result[0]->count > 0;
        }
        
        return false;
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");
            return collect($indexes)->contains('name', $index);
        } elseif ($driver === 'mysql') {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
            return count($result) > 0;
        }
        
        return false;
    }
};