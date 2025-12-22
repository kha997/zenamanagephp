<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 219: Core Contracts & Budget (Backend-first)
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_lines')) {
            Schema::create('contract_lines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('contract_id');
                $table->string('project_id'); // Denormalized for queries by project
                $table->string('budget_line_id')->nullable(); // FK to project_budget_lines
                $table->string('item_code')->nullable();
                $table->string('description');
                $table->string('unit')->nullable();
                $table->decimal('quantity', 18, 2);
                $table->decimal('unit_price', 18, 2);
                $table->decimal('amount', 18, 2);
                $table->json('metadata')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('budget_line_id')->references('id')->on('project_budget_lines')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'project_id']);
                $table->index('budget_line_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_lines');
    }
};
