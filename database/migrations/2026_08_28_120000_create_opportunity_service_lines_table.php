<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-046 — Canonical Service-Line Foundation (Gate 2 §3 Option B).
 *
 * Additive-only: creates a new, explicit, non-polymorphic membership
 * table for the Opportunity side. No existing table/column is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_service_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('opportunity_id');
            $table->string('service_line');
            $table->string('provenance');
            $table->string('source')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'opp_service_lines_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('opportunity_id', 'opp_service_lines_opportunity_id_foreign')
                ->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('created_by', 'opp_service_lines_created_by_foreign')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['tenant_id', 'opportunity_id', 'service_line'], 'opp_service_lines_unique');
            $table->index(['tenant_id', 'opportunity_id'], 'opp_service_lines_tenant_opp_index');
            // Gate 3 Correction Round 1, item 6: the unique index above
            // orders parent_id before service_line, so it does not
            // efficiently serve "all X-tenant subjects with Service-Line
            // Y" set-membership queries. Dedicated index for that access
            // pattern.
            $table->index(['tenant_id', 'service_line'], 'opp_service_lines_tenant_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_service_lines');
    }
};
