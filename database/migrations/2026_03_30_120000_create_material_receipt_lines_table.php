<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('material_receipt_id');
            $table->ulid('material_id');
            $table->decimal('quantity_received', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('material_receipt_id')->references('id')->on('material_receipts')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
            $table->index(['tenant_id', 'material_receipt_id'], 'material_receipt_lines_tenant_receipt_index');
            $table->index(['tenant_id', 'material_id'], 'material_receipt_lines_tenant_material_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_receipt_lines');
    }
};
