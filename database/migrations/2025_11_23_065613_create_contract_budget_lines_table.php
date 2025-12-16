<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create contract_budget_lines table
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Stores budget lines (planned costs) for contracts, organized by category/cost type.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_budget_lines')) {
            Schema::create('contract_budget_lines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id')->index();
                $table->ulid('contract_id')->index();

                $table->string('code')->nullable();         // mã hạng mục (VD: CC-001)
                $table->string('name');                     // mô tả ngắn: "Bê tông móng", "Nhôm kính",...
                $table->string('category')->nullable();     // vật tư / nhân công / thầu phụ / khác
                $table->string('cost_type')->nullable();    // optional: direct, indirect, contingency,...

                $table->decimal('quantity', 18, 2)->nullable();
                $table->string('unit', 50)->nullable();     // m3, m2, bộ, công,...

                $table->decimal('unit_price', 18, 2)->nullable();
                $table->decimal('total_amount', 18, 2)->nullable(); // có thể auto = quantity * unit_price

                $table->string('currency', 3)->nullable();  // default = contract.currency nếu null

                $table->string('wbs_code')->nullable();     // link tới WBS nếu sau này cần
                $table->string('status')->default('planned'); // planned/approved/locked/cancelled

                $table->text('notes')->nullable();
                $table->integer('sort_order')->default(0);

                $table->ulid('created_by_id')->nullable()->index();
                $table->ulid('updated_by_id')->nullable()->index();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'status']);
                $table->index('sort_order');
                
                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_budget_lines');
    }
};
