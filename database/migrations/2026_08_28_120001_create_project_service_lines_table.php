<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-046 — Canonical Service-Line Foundation (Gate 2 §3 Option B).
 *
 * Additive-only: creates a new, explicit, non-polymorphic membership
 * table for the Project side. No existing table/column (including the
 * legacy `projects.tenant_id` shape) is touched — see Gate 2 §5's stated
 * portability limitation. This migration itself receives zero rows from
 * any GAP-046 backfill mechanism (Gate 2 §7, decided Option A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_service_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('service_line');
            $table->string('provenance');
            $table->string('source')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'proj_service_lines_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'proj_service_lines_project_id_foreign')
                ->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('created_by', 'proj_service_lines_created_by_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['tenant_id', 'project_id', 'service_line'], 'proj_service_lines_unique');
            $table->index(['tenant_id', 'project_id'], 'proj_service_lines_tenant_proj_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_service_lines');
    }
};
