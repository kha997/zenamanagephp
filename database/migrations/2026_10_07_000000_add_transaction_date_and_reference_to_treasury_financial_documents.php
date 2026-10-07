<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-064 (Treasury S2, Owner Gate 1 answer 1): the business date the money
 * moved and the bank/receipt reference, for the duplicate warning and the
 * financial timeline. Additive only; transaction_date is required by the
 * application for every document S2 creates, nullable at the DB level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_financial_documents', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('amount');
            $table->string('reference', 100)->nullable()->after('transaction_date');
            $table->index(['project_id', 'transaction_date'], 'tfd_project_txn_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_financial_documents', function (Blueprint $table) {
            $table->dropIndex('tfd_project_txn_date_idx');
            $table->dropColumn(['transaction_date', 'reference']);
        });
    }
};
