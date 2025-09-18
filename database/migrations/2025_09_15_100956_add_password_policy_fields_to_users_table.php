<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Password policy fields
            $table->timestamp('password_changed_at')->nullable()->after('mfa_backup_codes_used');
            $table->timestamp('password_expires_at')->nullable()->after('password_changed_at');
            $table->integer('password_failed_attempts')->default(0)->after('password_expires_at');
            $table->timestamp('password_locked_until')->nullable()->after('password_failed_attempts');
            $table->json('password_history')->nullable()->after('password_locked_until');
            
            // Indexes
            $table->index('password_expires_at');
            $table->index('password_locked_until');
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
            $table->dropIndex(['password_expires_at']);
            $table->dropIndex(['password_locked_until']);
            
            $table->dropColumn([
                'password_changed_at',
                'password_expires_at',
                'password_failed_attempts',
                'password_locked_until',
                'password_history'
            ]);
        });
    }
};
