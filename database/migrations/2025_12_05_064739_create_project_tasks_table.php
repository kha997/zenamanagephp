<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 202: Create project_tasks table for auto-generating tasks from TaskTemplates
     */
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index(); // Required for multi-tenant isolation
            $table->string('project_id')->index(); // FK to projects
            $table->string('template_task_id')->nullable()->index(); // FK to task_templates (link to source template)
            $table->string('phase_id')->nullable(); // Optional phase reference
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0); // Mapping from TaskTemplate.order_index
            $table->boolean('is_milestone')->default(false); // Can be from TaskTemplate.metadata
            $table->string('status')->nullable(); // Can be from TaskTemplate.metadata.default_status
            $table->date('due_date')->nullable(); // Calculated from project.start_date + default_due_days_offset
            $table->integer('duration_days')->default(0);
            $table->float('progress_percent')->default(0);
            $table->string('conditional_tag')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('template_id')->nullable(); // FK to templates (project template)
            $table->json('metadata')->nullable(); // Additional metadata
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for efficient queries
            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'template_task_id']);
            $table->index(['project_id', 'sort_order']);
            $table->index(['project_id', 'status']);
            
            // Foreign key constraints (optional but recommended)
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('template_task_id')->references('id')->on('task_templates')->onDelete('set null');
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
