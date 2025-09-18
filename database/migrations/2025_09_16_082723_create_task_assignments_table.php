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
        if (!Schema::hasTable('task_assignments')) {
            Schema::create('task_assignments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('task_id');
                $table->string('user_id');
                $table->string('role')->default('assignee'); // assignee, reviewer, watcher
                $table->decimal('assigned_hours', 8, 2)->nullable();
                $table->decimal('actual_hours', 8, 2)->default(0);
                $table->string('status')->default('assigned'); // assigned, in_progress, completed, cancelled
                $table->timestamp('assigned_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes
                $table->index(['task_id', 'user_id']);
                $table->index(['user_id', 'status']);
                $table->index(['task_id', 'status']);
                $table->index('role');
                $table->index('status');
                
                // Unique constraint to prevent duplicate assignments
                $table->unique(['task_id', 'user_id', 'role'], 'unique_task_user_role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};