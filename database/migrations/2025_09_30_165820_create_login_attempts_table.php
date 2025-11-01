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
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id')->nullable(); // nullable if failed attempt
            $table->string('tenant_id')->nullable();
            $table->string('email'); // Email used in attempt
            $table->string('status'); // success, failed, locked, blocked
            $table->string('reason')->nullable(); // wrong_password, account_locked, mfa_failed, etc.
            $table->string('ip_address');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('isp')->nullable();
            $table->string('user_agent');
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->boolean('is_suspicious')->default(false);
            $table->float('risk_score')->default(0); // 0-100
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index('is_suspicious');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
