<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        TreasuryCheckConstraint::createTableWithChecks('treasury_cost_settlement_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('financial_document_id')->nullable();
            $table->ulid('advance_settlement_id')->nullable();
            $table->ulid('cost_source_contract_expense_id')->nullable();
            $table->ulid('cost_source_material_receipt_line_id')->nullable();
            $table->string('direction', 8);
            $table->decimal('allocated_amount', 15, 2);
            $table->ulid('reverses_allocation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tcsa_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tcsa_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'advance_settlement_id'],
                'tcsa_advance_settlement_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advance_settlements');
            $table->foreign('cost_source_contract_expense_id', 'tcsa_cost_source_ce_fk')
                ->references('id')->on('contract_expenses');
            $table->foreign('cost_source_material_receipt_line_id', 'tcsa_cost_source_mrl_fk')
                ->references('id')->on('material_receipt_lines');
            $table->unique(['tenant_id', 'id'], 'tcsa_tenant_id_id_unique');

            $table->foreign(
                ['tenant_id', 'reverses_allocation_id'],
                'tcsa_reverses_allocation_fk'
            )->references(['tenant_id', 'id'])->on('treasury_cost_settlement_allocations');

            $table->unique('reverses_allocation_id', 'tcsa_reverses_allocation_unique');
            $table->index(['financial_document_id'], 'tcsa_financial_doc_idx');
            $table->index(['advance_settlement_id'], 'tcsa_advance_settlement_idx');
        }, [
            'tcsa_amount_positive_chk' => 'allocated_amount > 0',
            'tcsa_source_exactly_one_chk' => '(financial_document_id IS NULL) != (advance_settlement_id IS NULL)',
            'tcsa_cost_source_exactly_one_chk' => '(cost_source_contract_expense_id IS NULL) != (cost_source_material_receipt_line_id IS NULL)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_cost_settlement_allocations');
    }
};
