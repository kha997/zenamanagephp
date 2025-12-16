<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('code'); // Số HĐ / mã HĐ - unique per tenant
            $table->string('name'); // Tên hợp đồng
            $table->string('status')->default('draft'); // draft, active, completed, cancelled
            $table->dateTime('signed_at')->nullable(); // Ngày ký
            $table->date('effective_from')->nullable(); // Hiệu lực từ
            $table->date('effective_to')->nullable(); // Hiệu lực đến
            $table->string('currency', 3)->default('USD'); // USD, VND, etc.
            $table->decimal('total_value', 15, 2)->default(0); // Giá trị tổng
            $table->text('notes')->nullable();
            $table->string('created_by_id')->nullable();
            $table->string('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index(['tenant_id', 'code']); // Unique code per tenant
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'project_id']);
            $table->index('signed_at');
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraint: code must be unique per tenant
            $table->unique(['tenant_id', 'code']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
