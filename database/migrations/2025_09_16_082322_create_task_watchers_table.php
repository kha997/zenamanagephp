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
        if (!Schema::hasTable('task_watchers')) {
            Schema::create('task_watchers', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('task_id');
                $table->ulid('user_id');
                $table->timestamps();

                // Foreign keys
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes
                $table->index(['task_id', 'user_id']);
                $table->index(['user_id', 'task_id']);
                
                // Unique constraint to prevent duplicates
                $table->unique(['task_id', 'user_id'], 'unique_task_watcher');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_watchers');
    }
};