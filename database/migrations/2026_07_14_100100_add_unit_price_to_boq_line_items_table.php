<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_line_items', function (Blueprint $table): void {
            $table->decimal('unit_price', 15, 2)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('boq_line_items', function (Blueprint $table): void {
            $table->dropColumn('unit_price');
        });
    }
};
