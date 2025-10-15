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
        // Check if audit_logs table already exists
        if (Schema::hasTable('audit_logs')) {
            return; // Skip if table already exists
        }
        
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event'); // e.g., 'project_create', 'project_update'
            $table->string('user_id');
            $table->string('tenant_id');
            $table->string('model_type')->nullable(); // e.g., 'App\Models\Project'
            $table->string('model_id')->nullable(); // ID of the affected model
            $table->json('data'); // Additional event data
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};