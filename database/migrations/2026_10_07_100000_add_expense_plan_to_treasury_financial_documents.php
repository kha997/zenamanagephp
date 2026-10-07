<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-066 (Treasury S3, approved Gate 2 Option A): an expense's planned cost
 * allocations while it is still a draft. v17 allocations are immutable facts
 * created only at posting, so the intent lives here until approval; it is
 * kept, read-only, for audit afterwards. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_financial_documents', function (Blueprint $table) {
            $table->json('expense_plan')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_financial_documents', function (Blueprint $table) {
            $table->dropColumn('expense_plan');
        });
    }
};
