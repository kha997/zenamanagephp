<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design Item slice — spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
// NOTE: the spec listed an optional `phase_id` FK to `project_phases`, but that table has no
// backing migration anywhere in this codebase (ProjectPhase model is unused/unbacked — verified
// during planning). Deliberately omitted; project_id alone is enough scoping for now.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('work_instance_step_id')->nullable();
            $table->string('name');
            $table->string('item_type')->default('other');
            $table->string('review_status')->default('draft');
            $table->ulid('assigned_to')->nullable();
            $table->date('due_to_client_at')->nullable();
            $table->text('client_feedback_notes')->nullable();
            $table->string('approval_evidence')->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'design_items_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'design_items_project_id_foreign')
                ->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_instance_step_id', 'design_items_wi_step_id_foreign')
                ->references('id')->on('work_instance_steps')->nullOnDelete();
            $table->foreign('assigned_to', 'design_items_assigned_to_foreign')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'design_items_created_by_foreign')
                ->references('id')->on('users');

            $table->index(['tenant_id', 'project_id'], 'design_items_tenant_project_index');
            $table->index(['tenant_id', 'review_status'], 'design_items_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_items');
    }
};
