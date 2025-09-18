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
        Schema::create('template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('project_templates')->onDelete('cascade');
            $table->string('phase_key'); // architectural, structural, mep, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days');
            $table->enum('priority', ['low', 'medium', 'high', 'critical']);
            $table->json('dependencies')->nullable(); // Array of task IDs this task depends on
            $table->json('deliverables')->nullable(); // Array of deliverables
            $table->json('skills_required')->nullable(); // Array of required skills
            $table->json('tools_required')->nullable(); // Array of required tools
            $table->json('checklist')->nullable(); // Array of checklist items
            $table->json('approval_workflow')->nullable(); // Approval process
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->integer('team_size')->default(1);
            $table->boolean('is_milestone')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['template_id', 'phase_key']);
            $table->index(['priority', 'duration_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_tasks');
    }
};
