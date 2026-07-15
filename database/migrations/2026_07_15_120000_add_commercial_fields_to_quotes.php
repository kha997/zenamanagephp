<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('vat_percent', 5, 2)->default(0)->after('discount_percent');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('vat_percent');
            $table->decimal('vat_amount', 15, 2)->default(0)->after('discount_amount');
            $table->decimal('total', 15, 2)->default(0)->after('vat_amount');
            $table->text('payment_terms')->nullable()->after('total');
        });

        DB::table('quotes')->update(['total' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percent',
                'vat_percent',
                'discount_amount',
                'vat_amount',
                'total',
                'payment_terms',
            ]);
        });
    }
};
