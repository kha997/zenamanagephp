<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_reference_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('work_item_code', 50);
            $table->string('work_item_name', 255);
            $table->string('unit', 30);
            $table->decimal('unit_price', 15, 2);
            $table->string('benchmark_type', 30);
            $table->text('evidence_note')->nullable();
            $table->date('evidenced_at');
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'work_item_code', 'unit', 'evidenced_at'], 'pre_tenant_code_unit_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_reference_entries');
    }
};
