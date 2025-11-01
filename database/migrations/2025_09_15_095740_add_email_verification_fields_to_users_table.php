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
        Schema::table('users', function (Blueprint $table) {
            // Email verification fields
            if (!Schema::hasColumn('users', 'email_verified')) {
                $table->boolean('email_verified')->default(false)->after('email');
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email_verified');
            }
            if (!Schema::hasColumn('users', 'email_verification_token')) {
                $table->string('email_verification_token')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'email_verification_token_expires_at')) {
                $table->timestamp('email_verification_token_expires_at')->nullable()->after('email_verification_token');
            }
            
            // Email change tracking
            if (!Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email')->nullable()->after('email_verification_token_expires_at');
            }
            if (!Schema::hasColumn('users', 'email_change_token')) {
                $table->string('email_change_token')->nullable()->after('pending_email');
            }
            if (!Schema::hasColumn('users', 'email_change_token_expires_at')) {
                $table->timestamp('email_change_token_expires_at')->nullable()->after('email_change_token');
            }
            
            // Indexes
            if (!$this->constraintExists('users', 'users_email_verification_token_index')) {
                $table->index('email_verification_token');
            }
            if (!$this->constraintExists('users', 'users_email_change_token_index')) {
                $table->index('email_change_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_verification_token']);
            $table->dropIndex(['email_change_token']);
            
            $table->dropColumn([
                'email_verified',
                'email_verified_at',
                'email_verification_token',
                'email_verification_token_expires_at',
                'pending_email',
                'email_change_token',
                'email_change_token_expires_at'
            ]);
        });
    }
};
