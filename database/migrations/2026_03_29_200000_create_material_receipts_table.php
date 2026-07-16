<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('vendor_id')->nullable();
            $table->string('receipt_number', 100);
            $table->date('receipt_date');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'project_id', 'receipt_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_receipts');
    }
};
