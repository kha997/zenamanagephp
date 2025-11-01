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
            // MFA fields
            $table->boolean('mfa_enabled')->default(false)->after('email_change_token_expires_at');
            $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            $table->json('mfa_recovery_codes')->nullable()->after('mfa_secret');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_recovery_codes');
            
            // MFA backup codes
            $table->integer('mfa_backup_codes_used')->default(0)->after('mfa_enabled_at');
            
            // Indexes
            $table->index('mfa_enabled');
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
            $table->dropIndex(['mfa_enabled']);
            
            $table->dropColumn([
                'mfa_enabled',
                'mfa_secret',
                'mfa_recovery_codes',
                'mfa_enabled_at',
                'mfa_backup_codes_used'
            ]);
        });
    }
};
