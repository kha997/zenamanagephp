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
        Schema::create('task_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('task_id');
            $table->ulid('user_id');
            $table->text('content');
            $table->string('type')->default('comment'); // comment, status_change, assignment, mention
            $table->json('metadata')->nullable(); // Additional data for different comment types
            $table->ulid('parent_id')->nullable(); // For threaded comments
            $table->boolean('is_internal')->default(false); // Internal vs client-visible comments
            $table->boolean('is_pinned')->default(false); // Pinned comments
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('task_comments')->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['task_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};