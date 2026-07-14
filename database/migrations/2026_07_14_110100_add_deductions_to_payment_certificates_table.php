<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_certificates', function (Blueprint $table): void {
            $table->decimal('retention_amount', 15, 2)->default(0)->after('total_this_period');
            $table->decimal('advance_deduction', 15, 2)->default(0)->after('retention_amount');
            $table->decimal('net_payable', 15, 2)->default(0)->after('advance_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('payment_certificates', function (Blueprint $table): void {
            $table->dropColumn(['retention_amount', 'advance_deduction', 'net_payable']);
        });
    }
};
