<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_receipt_lines', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('quantity_received');
        });
    }

    public function down(): void
    {
        Schema::table('material_receipt_lines', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
