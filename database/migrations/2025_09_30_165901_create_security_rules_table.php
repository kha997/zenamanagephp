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
        Schema::create('security_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable(); // null = system-wide rule
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // login, session, mfa, api_key, etc.
            $table->string('type'); // rate_limit, geo_restriction, impossible_travel, etc.
            $table->boolean('is_enabled')->default(true);
            $table->string('severity'); // info, warning, critical
            $table->json('conditions'); // Rule logic: {threshold: 5, window: '5m', ip_based: true}
            $table->json('actions'); // Actions on trigger: {alert: true, block: false, notify: ['email']}
            $table->json('destinations')->nullable(); // Email, Slack, Webhook URLs
            $table->integer('trigger_count')->default(0); // How many times triggered
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_enabled']);
            $table->index(['category', 'is_enabled']);
            $table->index(['type', 'is_enabled']);
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_rules');
    }
};
