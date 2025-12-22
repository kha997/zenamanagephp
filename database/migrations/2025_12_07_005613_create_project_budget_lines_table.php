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
        if (!Schema::hasTable('project_budget_lines')) {
            Schema::create('project_budget_lines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('project_id');
                $table->string('cost_category')->nullable(); // 'structure', 'mep', 'interior', 'doors', 'ffe', etc.
                $table->string('cost_code')->nullable(); // Mã chi phí
                $table->string('description');
                $table->string('unit')->nullable();
                $table->decimal('quantity', 18, 2)->nullable();
                $table->decimal('unit_price_budget', 18, 2)->nullable();
                $table->decimal('amount_budget', 18, 2);
                $table->json('metadata')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'cost_category']);
                $table->index('cost_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_budget_lines');
    }
};
