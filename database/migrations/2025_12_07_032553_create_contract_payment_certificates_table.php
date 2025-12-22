<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round 221: Payment Certificates & Payments (Actual Cost)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_payment_certificates')) {
            Schema::create('contract_payment_certificates', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('code'); // Certificate number (e.g. IPC-01, CC-05)
                $table->string('title')->nullable(); // e.g. "Interim Payment Certificate #01"
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('status'); // 'draft', 'submitted', 'approved', 'rejected', 'cancelled'
                $table->decimal('amount_before_retention', 18, 2); // Tổng giá trị nghiệm thu trước retention
                $table->decimal('retention_percent_override', 5, 2)->nullable(); // Nếu null thì dùng contract.retention_percent
                $table->decimal('retention_amount', 18, 2); // Số tiền bị giữ lại
                $table->decimal('amount_payable', 18, 2); // Số được đề nghị thanh toán
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
                $table->index(['tenant_id', 'contract_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_payment_certificates');
    }
};
