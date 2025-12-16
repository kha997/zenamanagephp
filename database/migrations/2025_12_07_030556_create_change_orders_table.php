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
        if (!Schema::hasTable('change_orders')) {
            Schema::create('change_orders', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('code'); // CO number (e.g. CO-001)
                $table->string('title'); // Short description/title
                $table->string('reason')->nullable(); // e.g. 'design_change', 'site_condition', 'client_request'
                $table->string('status'); // 'draft', 'proposed', 'approved', 'rejected', 'cancelled'
                $table->decimal('amount_delta', 18, 2); // Total increase (+) or decrease (-) of contract value
                $table->date('effective_date')->nullable();
                $table->json('metadata')->nullable();
                $table->ulid('created_by')->nullable();
                $table->ulid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'contract_id', 'status']); // Removed project_id to avoid key length issue
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_orders');
    }
};
