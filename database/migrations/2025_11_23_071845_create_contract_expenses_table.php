<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create contract_expenses table
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Stores actual costs/expenses for contracts, tracking incurred costs against budget lines.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_expenses')) {
            Schema::create('contract_expenses', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id')->index();
                $table->ulid('contract_id')->index();
                $table->ulid('budget_line_id')->nullable()->index();

                $table->string('code', 64)->nullable();         // mã chi phí (nếu có)
                $table->string('name', 255);                     // tên khoản chi
                $table->string('category', 64)->nullable();      // labor, material, service, other
                $table->string('vendor_name', 255)->nullable();  // tên NCC/nhà thầu

                $table->decimal('quantity', 15, 2)->nullable();
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->decimal('amount', 15, 2)->nullable();    // tổng tiền, có thể auto = quantity * unit_cost

                $table->string('currency', 3)->default('VND');  // default giống repo
                $table->date('incurred_at')->nullable();         // ngày phát sinh chi phí

                $table->string('status', 32)->default('recorded'); // planned, recorded, approved, paid, cancelled
                $table->text('notes')->nullable();
                $table->integer('sort_order')->default(0);

                $table->ulid('created_by_id')->nullable()->index();
                $table->ulid('updated_by_id')->nullable()->index();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'status']);
                
                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('budget_line_id')->references('id')->on('contract_budget_lines')->onDelete('set null');
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
        Schema::dropIfExists('contract_expenses');
    }
};
