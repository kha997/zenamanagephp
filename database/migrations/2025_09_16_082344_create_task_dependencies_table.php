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
        if (!Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->string('task_id');
                $table->string('dependency_id');
                $table->timestamps();

                // Foreign keys
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
                $table->foreign('dependency_id')->references('id')->on('tasks')->onDelete('cascade');
                
                // Indexes
                $table->index(['task_id', 'dependency_id']);
                $table->index(['dependency_id', 'task_id']);
                
                // Unique constraint to prevent duplicates
                $table->unique(['task_id', 'dependency_id'], 'unique_task_dependency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};