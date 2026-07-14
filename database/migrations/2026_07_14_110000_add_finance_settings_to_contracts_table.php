<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->decimal('retention_percent', 5, 2)->default(0)->after('notes');
            $table->decimal('advance_amount', 15, 2)->default(0)->after('retention_percent');
            $table->decimal('advance_recovery_percent', 5, 2)->default(0)->after('advance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['retention_percent', 'advance_amount', 'advance_recovery_percent']);
        });
    }
};
