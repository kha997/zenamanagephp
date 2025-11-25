<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 36: Contract Payment Schedule Backend
     */
    public function up(): void
    {
        if (!Schema::hasTable('contract_payments')) {
            Schema::create('contract_payments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('contract_id'); // FK to contracts
                $table->string('code')->nullable(); // Mã đợt thanh toán
                $table->string('name'); // Mô tả đợt thanh toán
                $table->string('type')->nullable(); // deposit, milestone, progress, retention, final
                $table->date('due_date'); // Ngày đến hạn
                $table->decimal('amount', 15, 2); // Số tiền
                $table->string('currency', 3)->default('USD'); // Tiền tệ
                $table->string('status')->default('planned'); // planned, due, paid, overdue, cancelled
                $table->dateTime('paid_at')->nullable(); // Ngày thanh toán thực tế
                $table->text('notes')->nullable(); // Ghi chú
                $table->integer('sort_order')->default(0); // Thứ tự sắp xếp
                $table->string('created_by_id')->nullable(); // FK to users
                $table->string('updated_by_id')->nullable(); // FK to users
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index('tenant_id');
                $table->index(['tenant_id', 'contract_id']);
                $table->index(['tenant_id', 'status']);
                $table->index('due_date');
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
        Schema::dropIfExists('contract_payments');
    }
};
