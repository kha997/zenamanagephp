<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_advance_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('advance_id');
            $table->string('settlement_type', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 15, 2);
            $table->ulid('financial_document_id')->nullable();
            $table->ulid('reverses_settlement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tas_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'advance_id'],
                'tas_advance_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advances');
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tas_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->unique(['tenant_id', 'id'], 'tas_tenant_id_id_unique');

            $table->foreign(
                ['tenant_id', 'reverses_settlement_id'],
                'tas_reverses_settlement_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advance_settlements');

            $table->unique('reverses_settlement_id', 'tas_reverses_settlement_unique');
            $table->unique('financial_document_id', 'tas_financial_document_unique');
            $table->index(['advance_id'], 'tas_advance_idx');
        });

        TreasuryCheckConstraint::add(
            'treasury_advance_settlements',
            'tas_amount_positive_chk',
            'amount > 0',
            'NEW.amount > 0'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_advance_settlements');
    }
};
