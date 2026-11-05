<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-114 (Owner Gate 2 N-1, C-1, A-1): monthly company overhead — one sheet per month, its cost lines, and the split
 * over projects stored at the overhead close (a row without project is overhead not allocated). New tables only.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('overhead_periods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->char('period', 7);
            $table->string('status', 20)->default('draft');
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'ohp_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'period'], 'ohp_tenant_period_unique');
        });

        Schema::create('overhead_cost_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('overhead_period_id');
            $table->string('category', 30);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'ohl_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('overhead_period_id', 'ohl_period_id_fk')->references('id')->on('overhead_periods')->cascadeOnDelete();
            $table->index(['overhead_period_id'], 'ohl_period_idx');
        });

        Schema::create('overhead_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('overhead_period_id');
            $table->ulid('project_id')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'oha_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('overhead_period_id', 'oha_period_id_fk')->references('id')->on('overhead_periods')->cascadeOnDelete();
            $table->foreign('project_id', 'oha_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['overhead_period_id', 'project_id'], 'oha_period_project_unique');
            $table->index(['tenant_id', 'project_id'], 'oha_tenant_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overhead_allocations');
        Schema::dropIfExists('overhead_cost_lines');
        Schema::dropIfExists('overhead_periods');
    }
};
