<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 255: Create user_notification_preferences table
 * 
 * Stores per-user, per-tenant notification preferences for in-app notifications.
 * Default behavior: if no preference row exists, the type is considered enabled.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('user_id');
            $table->string('type', 100); // e.g., 'task.assigned', 'co.approved'
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id', 'user_notification_preferences_tenant_id_foreign')
                  ->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id', 'user_notification_preferences_user_id_foreign')
                  ->references('id')->on('users')->onDelete('cascade');

            // Unique composite index to prevent duplicates
            $table->unique(['tenant_id', 'user_id', 'type'], 'user_notification_preferences_unique');

            // Indexes for efficient queries
            $table->index(['tenant_id', 'user_id'], 'user_notification_preferences_tenant_user_idx');
            $table->index(['type'], 'user_notification_preferences_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
