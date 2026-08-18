<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_advances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('financial_party_id');
            $table->ulid('originating_financial_document_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('tenant_id', 'ta_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'ta_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign(
                ['tenant_id', 'financial_party_id'],
                'ta_financial_party_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->foreign(
                ['tenant_id', 'originating_financial_document_id'],
                'ta_originating_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');

            $table->unique(['tenant_id', 'id'], 'ta_tenant_id_id_unique');
            $table->unique('originating_financial_document_id', 'ta_originating_doc_unique');
            $table->index(['tenant_id'], 'ta_tenant_id_idx');
        });

        TreasuryCheckConstraint::add(
            'treasury_advances',
            'ta_amount_positive_chk',
            'amount > 0',
            'NEW.amount > 0'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_advances');
    }
};
