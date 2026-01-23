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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('project_id');
            $table->string('phase_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days')->default(0);
            $table->float('progress_percent')->default(0);
            $table->string('status');
            $table->string('conditional_tag')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('template_id')->nullable();
            $table->string('template_task_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('phase_id')->references('id')->on('project_phases')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('set null');
            $table->index(['project_id', 'status']);
            $table->index(['phase_id', 'is_hidden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
