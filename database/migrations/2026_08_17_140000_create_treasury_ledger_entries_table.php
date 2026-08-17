<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('source_financial_document_id')->nullable();
            $table->ulid('source_payment_route_leg_id')->nullable();
            $table->ulid('wallet_id');
            $table->string('direction', 8);
            $table->decimal('amount', 15, 2);
            $table->string('entry_type', 64);
            $table->timestamp('posted_at');
            $table->ulid('reversal_of_entry_id')->nullable();
            $table->string('original_posting_key');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tle_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'source_financial_document_id'],
                'tle_src_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'source_payment_route_leg_id'],
                'tle_src_leg_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_route_legs');
            $table->foreign(
                ['tenant_id', 'wallet_id'],
                'tle_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(
                ['tenant_id', 'reversal_of_entry_id'],
                'tle_reversal_of_fk'
            )->references(['tenant_id', 'id'])->on('treasury_ledger_entries');

            $table->unique(['tenant_id', 'id'], 'tle_tenant_id_id_unique');
            $table->unique('reversal_of_entry_id', 'tle_reversal_of_entry_unique');
            $table->unique('original_posting_key', 'tle_original_posting_key_unique');
            $table->index(['source_financial_document_id'], 'tle_src_doc_idx');
            $table->index(['source_payment_route_leg_id'], 'tle_src_leg_idx');
            $table->index(['wallet_id', 'posted_at'], 'tle_wallet_posted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_ledger_entries');
    }
};
