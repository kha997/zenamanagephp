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
        Schema::create('subtasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('task_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, canceled
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->ulid('assignee_id')->nullable();
            $table->ulid('created_by');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->float('estimated_hours')->default(0);
            $table->float('actual_hours')->default(0);
            $table->float('progress_percent')->default(0);
            $table->json('tags')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('assignee_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assignee_id']);
            $table->index(['task_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtasks');
    }
};