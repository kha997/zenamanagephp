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
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('rule_id')->nullable(); // Reference to security_rules
            $table->string('tenant_id')->nullable();
            $table->string('user_id')->nullable(); // Subject of alert
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity'); // info, warning, critical
            $table->string('status'); // open, acknowledged, resolved, false_positive
            $table->string('category'); // login, session, mfa, api_key, policy, etc.
            $table->json('details')->nullable(); // Alert-specific data
            $table->string('triggered_by')->nullable(); // User ID who triggered
            $table->string('assigned_to')->nullable(); // User ID assigned to resolve
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'severity', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index('rule_id');
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
