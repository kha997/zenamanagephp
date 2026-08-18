<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        TreasuryCheckConstraint::createTableWithChecks('treasury_fund_chain_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('fund_chain_id');
            $table->ulid('member_financial_document_id')->nullable();
            $table->ulid('member_payment_route_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfcm_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'fund_chain_id'],
                'tfcm_fund_chain_fk'
            )->references(['tenant_id', 'id'])->on('treasury_fund_chains');
            $table->foreign(
                ['tenant_id', 'member_financial_document_id'],
                'tfcm_member_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'member_payment_route_id'],
                'tfcm_member_route_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_routes');

            $table->unique(['tenant_id', 'id'], 'tfcm_tenant_id_id_unique');
            $table->unique('member_financial_document_id', 'tfcm_member_doc_unique');
            $table->unique('member_payment_route_id', 'tfcm_member_route_unique');
            $table->index(['fund_chain_id'], 'tfcm_fund_chain_idx');
        }, [
            'tfcm_member_exactly_one_chk' => '(member_financial_document_id IS NULL) != (member_payment_route_id IS NULL)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_fund_chain_members');
    }
};
