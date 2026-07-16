<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_certificates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('contract_id');
            $table->unsignedInteger('period_no');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->default('draft');
            $table->decimal('total_this_period', 15, 2)->default(0);
            $table->ulid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->unique(['contract_id', 'period_no']);
            $table->index(['tenant_id', 'contract_id']);
        });

        Schema::create('payment_certificate_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('payment_certificate_id');
            $table->ulid('boq_line_item_id');
            $table->decimal('qty_this_period', 14, 3);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('amount_this_period', 15, 2);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payment_certificate_id')->references('id')->on('payment_certificates')->cascadeOnDelete();
            $table->foreign('boq_line_item_id')->references('id')->on('boq_line_items')->cascadeOnDelete();
            // Tên tường minh: tên tự sinh dài 72 ký tự, vượt giới hạn 64 của MySQL (lỗi 1059).
            $table->unique(['payment_certificate_id', 'boq_line_item_id'], 'pc_lines_certificate_boq_line_unique');
            $table->index(['tenant_id', 'payment_certificate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_certificate_lines');
        Schema::dropIfExists('payment_certificates');
    }
};
