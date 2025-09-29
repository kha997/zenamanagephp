<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_active']); // locked/disabled
            $table->index(['mfa_secret']); // mfa report (whereNotNull)
            $table->index(['last_login_at']); // sort/filter
            $table->index(['role']); // rbac stats
        });

        // Audit logs table indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['created_at']); // range (ts)
            $table->index(['action']); // filter
            $table->index(['user_id']); // actor filter
            $table->index(['tenant_id']); // tenant scoping
            $table->index(['created_at', 'action']); // compound for login attempts
        });

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
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['mfa_secret']);
            $table->dropIndex(['last_login_at']);
            $table->dropIndex(['role']);
        });

        // Audit logs table indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['action']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['created_at', 'action']);
        });

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