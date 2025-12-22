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
        Schema::create('template_task_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('set_id');
            $table->string('task_id');
            $table->string('depends_on_task_id');

            // Foreign keys
            $table->foreign('set_id')->references('id')->on('template_sets')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('template_tasks')->onDelete('cascade');
            $table->foreign('depends_on_task_id')->references('id')->on('template_tasks')->onDelete('cascade');

            // Indexes
            $table->index('set_id');
            $table->index('task_id');
            $table->index('depends_on_task_id');
            
            // Unique constraint to prevent duplicate dependencies
            $table->unique(['task_id', 'depends_on_task_id'], 'unique_template_task_dependency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_task_dependencies');
    }
};

