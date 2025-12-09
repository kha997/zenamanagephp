<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
     */
    public function up(): void
    {
        Schema::create('cost_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable(false);
            $table->decimal('co_dual_threshold_amount', 18, 2)->nullable();
            $table->decimal('certificate_dual_threshold_amount', 18, 2)->nullable();
            $table->decimal('payment_dual_threshold_amount', 18, 2)->nullable();
            $table->decimal('over_budget_threshold_percent', 5, 2)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            
            // Unique constraint: one policy per tenant
            $table->unique('tenant_id');
            
            // Foreign key
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_approval_policies');
    }
};
