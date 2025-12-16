<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 220: Change Orders for Contracts
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('change_order_lines')) {
            Schema::create('change_order_lines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('change_order_id');
                $table->ulid('contract_line_id')->nullable(); // If adjusting an existing contract line
                $table->string('budget_line_id')->nullable(); // If mapping to a budget line
                $table->string('item_code')->nullable();
                $table->string('description');
                $table->string('unit')->nullable();
                $table->decimal('quantity_delta', 18, 2)->nullable();
                $table->decimal('unit_price_delta', 18, 2)->nullable();
                $table->decimal('amount_delta', 18, 2); // Required
                $table->json('metadata')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('change_order_id')->references('id')->on('change_orders')->onDelete('cascade');
                $table->foreign('contract_line_id')->references('id')->on('contract_lines')->onDelete('set null');
                $table->foreign('budget_line_id')->references('id')->on('project_budget_lines')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'change_order_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_order_lines');
    }
};
