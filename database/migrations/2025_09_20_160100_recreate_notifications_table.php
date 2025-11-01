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
        // Drop existing notifications table and recreate
        Schema::dropIfExists('notifications');
        
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('tenant_id');
            $table->string('type');
            $table->enum('priority', ['critical', 'normal', 'low'])->default('normal');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('channel', ['inapp', 'email', 'webhook'])->default('inapp');
            $table->timestamp('read_at')->nullable();
            $table->json('data')->nullable();
            $table->json('metadata')->nullable();
            $table->string('event_key')->nullable();
            $table->ulid('project_id')->nullable();
            $table->timestamps();

            // Foreign key constraints with explicit names
            $table->foreign('user_id', 'notifications_user_id_foreign')
                  ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_id', 'notifications_tenant_id_foreign')
                  ->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id', 'notifications_project_id_foreign')
                  ->references('id')->on('projects')->onDelete('cascade');

            // Indexes
            $table->index(['user_id', 'read_at']);
            $table->index(['tenant_id']);
            $table->index(['priority']);
            $table->index(['channel']);
            $table->index(['project_id']);
            $table->index(['event_key']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
