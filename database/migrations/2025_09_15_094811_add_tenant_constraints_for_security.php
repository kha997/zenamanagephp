<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if constraint or index exists
     */
    private function constraintExists(string $table, string $constraintName): bool
    {
        try {
            // For SQLite, we'll use a simpler approach
            if (DB::getDriverName() === 'sqlite') {
                // Check if the constraint exists by trying to create it
                // This is a simplified check for SQLite
                return false; // Always return false to allow creation
            }
            
            // For MySQL/PostgreSQL, use information_schema
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            if (count($constraints) > 0) {
                return true;
            }
            
            // Check indexes
            $indexes = DB::select("
                SELECT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND INDEX_NAME = ?
            ", [$table, $constraintName]);
            
            return count($indexes) > 0;
        } catch (\Exception $e) {
            // If there's any error, assume constraint doesn't exist
            return false;
        }
    }
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add unique constraint for users email per tenant
        if (!$this->constraintExists('users', 'ux_users_email_tenant')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['email', 'tenant_id'], 'ux_users_email_tenant');
            });
        }

        // Add foreign key constraints for tenant isolation
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        // Update existing projects with default tenant
        $defaultTenant = DB::table('tenants')->first();
        if ($defaultTenant) {
            // Update projects with null or invalid tenant_id
            DB::table('projects')
                ->where(function($query) use ($defaultTenant) {
                    $query->whereNull('tenant_id')
                          ->orWhereNotIn('tenant_id', DB::table('tenants')->pluck('id'));
                })
                ->update(['tenant_id' => $defaultTenant->id]);
        }

        // Now add foreign key constraint
        if (!$this->constraintExists('projects', 'projects_tenant_id_foreign')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (!$this->constraintExists('projects', 'idx_projects_tenant_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['tenant_id', 'id'], 'idx_projects_tenant_id');
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        // Update existing tasks with default tenant
        if ($defaultTenant) {
            DB::table('tasks')
                ->where(function($query) use ($defaultTenant) {
                    $query->whereNull('tenant_id')
                          ->orWhereNotIn('tenant_id', DB::table('tenants')->pluck('id'));
                })
                ->update(['tenant_id' => $defaultTenant->id]);
        }

        if (!$this->constraintExists('tasks', 'tasks_tenant_id_foreign')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (!$this->constraintExists('tasks', 'idx_tasks_tenant_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['tenant_id', 'id'], 'idx_tasks_tenant_id');
            });
        }

        // Only add documents constraints if table exists
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'tenant_id')) {
                    $table->string('tenant_id')->nullable()->after('id');
                }
            });

            // Update existing documents with default tenant
            if ($defaultTenant) {
                DB::table('documents')
                    ->where(function($query) use ($defaultTenant) {
                        $query->whereNull('tenant_id')
                              ->orWhereNotIn('tenant_id', DB::table('tenants')->pluck('id'));
                    })
                    ->update(['tenant_id' => $defaultTenant->id]);
            }

            if (!$this->constraintExists('documents', 'documents_tenant_id_foreign')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            }
            
            if (!$this->constraintExists('documents', 'idx_documents_tenant_id')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->index(['tenant_id', 'id'], 'idx_documents_tenant_id');
                });
            }
        }

        // Add audit_logs table for security audit
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('action'); // create, update, delete, login, logout
            $table->string('entity_type'); // User, Project, Task, etc.
            $table->string('entity_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'created_at'], 'idx_audit_logs_tenant_created');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_created');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_logs_entity');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop audit_logs table
        Schema::dropIfExists('audit_logs');

        // Drop foreign keys and indexes
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_documents_tenant_id');
            $table->dropColumn('tenant_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_tasks_tenant_id');
            $table->dropColumn('tenant_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_projects_tenant_id');
            $table->dropColumn('tenant_id');
        });

        // Drop unique constraint
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('ux_users_email_tenant');
        });
    }
};
