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
        // Create minimal tables for testing
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('domain')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('member');
            $table->timestamps();
            
            $table->index(['tenant_id', 'email']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->string('owner_id')->nullable();
            $table->json('tags')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->integer('progress_pct')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->string('user_id');
            $table->string('tenant_id');
            $table->string('model_type')->nullable();
            $table->string('model_id')->nullable();
            $table->json('data');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};