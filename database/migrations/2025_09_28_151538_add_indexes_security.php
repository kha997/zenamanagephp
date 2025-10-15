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
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_is_active_index')) {
                $table->index(['is_active']); // locked/disabled
            }
            if (!$this->indexExists('users', 'users_mfa_secret_index')) {
                $table->index(['mfa_secret']); // mfa report (whereNotNull)
            }
            if (!$this->indexExists('users', 'users_last_login_at_index')) {
                $table->index(['last_login_at']); // sort/filter
            }
            if (!$this->indexExists('users', 'users_role_index')) {
                $table->index(['role']); // rbac stats
            }
        });

        // Audit logs table indexes (if exists)
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!$this->indexExists('audit_logs', 'audit_logs_created_at_index')) {
                    $table->index(['created_at']); // range (ts)
                }
                if (Schema::hasColumn('audit_logs', 'action') && !$this->indexExists('audit_logs', 'audit_logs_action_index')) {
                    $table->index(['action']); // filter
                }
                if (Schema::hasColumn('audit_logs', 'user_id') && !$this->indexExists('audit_logs', 'audit_logs_user_id_index')) {
                    $table->index(['user_id']); // actor filter
                }
                if (Schema::hasColumn('audit_logs', 'tenant_id') && !$this->indexExists('audit_logs', 'audit_logs_tenant_id_index')) {
                    $table->index(['tenant_id']); // tenant scoping
                }
                if (Schema::hasColumn('audit_logs', 'action') && !$this->indexExists('audit_logs', 'audit_logs_created_at_action_index')) {
                    $table->index(['created_at', 'action']); // compound for login attempts
                }
            });
        }

        // Sessions table indexes (if exists)
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->index(['last_activity']); // active sessions
                $table->index(['user_id']); // end-all for user
            });
        }

        // API keys table indexes (if exists)
        if (Schema::hasTable('api_keys')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->index(['owner_id']);
                $table->index(['expires_at']);
                $table->index(['rotated_at']);
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    public function down(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['mfa_secret']);
            $table->dropIndex(['last_login_at']);
            $table->dropIndex(['role']);
        });

        // Audit logs table indexes (if exists)
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
                $table->dropIndex(['action']);
                $table->dropIndex(['user_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropIndex(['created_at', 'action']);
            });
        }

        // Sessions table indexes (if exists)
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropIndex(['last_activity']);
                $table->dropIndex(['user_id']);
            });
        }

        // API keys table indexes (if exists)
        if (Schema::hasTable('api_keys')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropIndex(['owner_id']);
                $table->dropIndex(['expires_at']);
                $table->dropIndex(['rotated_at']);
            });
        }
    }
};