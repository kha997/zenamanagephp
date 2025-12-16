<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds composite indexes for efficient cursor-based pagination.
     * These indexes support queries like: WHERE tenant_id = ? AND created_at < ? ORDER BY created_at DESC
     */
    public function up(): void
    {
        // Users table - for cursor pagination
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'idx_users_tenant_created')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_users_tenant_created');
                }
                if (!$this->indexExists('users', 'idx_users_tenant_updated')) {
                    $table->index(['tenant_id', 'updated_at'], 'idx_users_tenant_updated');
                }
            });
        }

        // Subtasks table
        if (Schema::hasTable('subtasks')) {
            Schema::table('subtasks', function (Blueprint $table) {
                if (!$this->indexExists('subtasks', 'idx_subtasks_tenant_created')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_subtasks_tenant_created');
                }
            });
        }

        // Task comments table
        if (Schema::hasTable('task_comments')) {
            Schema::table('task_comments', function (Blueprint $table) {
                if (!$this->indexExists('task_comments', 'idx_task_comments_tenant_created')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_task_comments_tenant_created');
                }
            });
        }

        // Task attachments table
        if (Schema::hasTable('task_attachments')) {
            Schema::table('task_attachments', function (Blueprint $table) {
                if (!$this->indexExists('task_attachments', 'idx_task_attachments_tenant_created')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_task_attachments_tenant_created');
                }
            });
        }

        // Change requests table
        if (Schema::hasTable('change_requests')) {
            Schema::table('change_requests', function (Blueprint $table) {
                if (!$this->indexExists('change_requests', 'idx_change_requests_tenant_created')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_change_requests_tenant_created');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_created');
            $table->dropIndex('idx_users_tenant_updated');
        });

        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropIndex('idx_subtasks_tenant_created');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex('idx_task_comments_tenant_created');
        });

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropIndex('idx_task_attachments_tenant_created');
        });

        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropIndex('idx_change_requests_tenant_created');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};

