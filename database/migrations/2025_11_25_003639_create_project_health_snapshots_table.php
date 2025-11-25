<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 86: Project Health History (snapshots + history API, backend-only)
     * 
     * Creates table to store persistent snapshots of project health over time.
     */
    public function up(): void
    {
        Schema::create('project_health_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('project_id')->nullable();
            $table->date('snapshot_date');
            $table->string('schedule_status', 32);
            $table->string('cost_status', 32);
            $table->string('overall_status', 32);
            $table->decimal('tasks_completion_rate', 5, 4)->nullable();
            $table->decimal('blocked_tasks_ratio', 5, 4)->nullable();
            $table->integer('overdue_tasks')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'project_id', 'snapshot_date']);
            
            // Unique constraint: at most one snapshot per project per day
            $table->unique(['tenant_id', 'project_id', 'snapshot_date'], 'unique_project_snapshot_date');
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_health_snapshots');
    }
};
