<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\SqliteCompatibleMigration;

return new class extends Migration
{
    use SqliteCompatibleMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account lockout fields
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $this->addColumnWithPositioning($table, 'failed_login_attempts', 'integer', [
                    'nullable' => false,
                    'default' => 0
                ], 'last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'locked_until')) {
                $this->addColumnWithPositioning($table, 'locked_until', 'timestamp', [
                    'nullable' => true
                ], 'failed_login_attempts');
            }
            
            // Index for lockout queries (tenant-aware)
            if (!Schema::hasIndex('users', 'idx_users_lockout')) {
                $table->index(['tenant_id', 'email', 'locked_until'], 'idx_users_lockout');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop index first
            if (Schema::hasIndex('users', 'idx_users_lockout')) {
                $table->dropIndex('idx_users_lockout');
            }
            
            // Drop columns
            if (Schema::hasColumn('users', 'locked_until')) {
                $table->dropColumn('locked_until');
            }
            
            if (Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->dropColumn('failed_login_attempts');
            }
        });
    }
};
