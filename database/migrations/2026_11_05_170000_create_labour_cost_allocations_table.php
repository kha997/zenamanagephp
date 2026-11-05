<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-113 (Owner Gate 2 S-1, R-1, O-1): labour cost of a closed payroll month split per person and project; a row
 * without project holds the cost not charged to any project. Written at close, removed on reopen. New table only.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('labour_cost_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('payroll_period_id');
            $table->ulid('staff_member_id');
            $table->ulid('project_id')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'lca_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_period_id', 'lca_period_id_fk')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('staff_member_id', 'lca_staff_id_fk')->references('id')->on('staff_members')->cascadeOnDelete();
            $table->foreign('project_id', 'lca_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['payroll_period_id', 'staff_member_id', 'project_id'], 'lca_period_staff_project_unique');
            $table->index(['tenant_id', 'project_id'], 'lca_tenant_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_cost_allocations');
    }
};
