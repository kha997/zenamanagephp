<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates transactional outbox table for reliable event publishing.
     * Ensures events are published even if the main transaction succeeds but
     * event publishing fails (e.g., queue is down).
     */
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('event_type'); // e.g., 'ProjectUpdated', 'TaskMoved'
            $table->string('event_name'); // e.g., 'Project.Project.Updated'
            $table->json('payload'); // Event payload
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('correlation_id')->nullable()->index(); // X-Request-Id
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['status', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['event_type', 'status']);
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
