<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable foreign key constraints for production
        $this->enableForeignKeys();
        
        // Add missing foreign key constraints
        $this->addMissingForeignKeys();
        
        // Clean up orphaned records
        $this->cleanupOrphanedRecords();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key constraints
        $this->disableForeignKeys();
    }

    /**
     * Enable foreign key constraints
     */
    private function enableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON;');
        }
    }

    /**
     * Disable foreign key constraints
     */
    private function disableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF;');
        }
    }

    /**
     * Add missing foreign key constraints
     */
    private function addMissingForeignKeys(): void
    {
        // Users table foreign keys
        if (Schema::hasTable('users') && Schema::hasTable('tenants')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    if (!$this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                    }
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }

        // Projects table foreign keys
        if (Schema::hasTable('projects') && Schema::hasTable('tenants')) {
            try {
                Schema::table('projects', function (Blueprint $table) {
                    if (!$this->foreignKeyExists('projects', 'projects_tenant_id_foreign')) {
                        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('projects', 'projects_client_id_foreign') && Schema::hasTable('clients')) {
                        $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
                    }
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }

        // Tasks table foreign keys
        if (Schema::hasTable('tasks') && Schema::hasTable('projects')) {
            try {
                Schema::table('tasks', function (Blueprint $table) {
                    if (!$this->foreignKeyExists('tasks', 'tasks_project_id_foreign')) {
                        $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('tasks', 'tasks_assignee_id_foreign') && Schema::hasTable('users')) {
                        $table->foreign('assignee_id')->references('id')->on('users')->onDelete('set null');
                    }
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }

        // Documents table foreign keys
        if (Schema::hasTable('documents') && Schema::hasTable('projects')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    if (!$this->foreignKeyExists('documents', 'documents_project_id_foreign')) {
                        $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('documents', 'documents_uploaded_by_foreign') && Schema::hasTable('users')) {
                        $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('documents', 'documents_tenant_id_foreign') && Schema::hasTable('tenants')) {
                        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                    }
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }

        // Calendar events table foreign keys
        if (Schema::hasTable('calendar_events')) {
            try {
                Schema::table('calendar_events', function (Blueprint $table) {
                    if (!$this->foreignKeyExists('calendar_events', 'calendar_events_project_id_foreign') && Schema::hasTable('projects')) {
                        $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('calendar_events', 'calendar_events_user_id_foreign') && Schema::hasTable('users')) {
                        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                    }
                    if (!$this->foreignKeyExists('calendar_events', 'calendar_events_tenant_id_foreign') && Schema::hasTable('tenants')) {
                        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                    }
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }
    }

    /**
     * Clean up orphaned records
     */
    private function cleanupOrphanedRecords(): void
    {
        // Clean up orphaned projects
        if (Schema::hasTable('projects') && Schema::hasTable('tenants')) {
            DB::statement('DELETE FROM projects WHERE tenant_id NOT IN (SELECT id FROM tenants)');
        }

        // Clean up orphaned tasks
        if (Schema::hasTable('tasks') && Schema::hasTable('projects')) {
            DB::statement('DELETE FROM tasks WHERE project_id NOT IN (SELECT id FROM projects)');
        }

        // Clean up orphaned documents
        if (Schema::hasTable('documents') && Schema::hasTable('projects')) {
            try {
                DB::statement('DELETE FROM documents WHERE project_id NOT IN (SELECT id FROM projects)');
            } catch (\Exception $e) {
                // Skip if table doesn't exist or constraint issues
            }
        }

        // Clean up orphaned users
        if (Schema::hasTable('users') && Schema::hasTable('tenants')) {
            DB::statement('UPDATE users SET tenant_id = NULL WHERE tenant_id NOT IN (SELECT id FROM tenants)');
        }
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                $result = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ? 
                    AND CONSTRAINT_NAME = ?
                ", [$table, $constraint]);
                
                return $result[0]->count > 0;
            } elseif ($driver === 'sqlite') {
                $result = DB::select("PRAGMA foreign_key_list({$table})");
                return collect($result)->contains('id', $constraint);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};