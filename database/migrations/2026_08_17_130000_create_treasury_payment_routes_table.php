<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_payment_routes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->decimal('total_allocated_amount', 15, 2);
            $table->string('status', 16);
            $table->ulid('linked_financial_document_id')->nullable();
            $table->ulid('linked_contract_payment_id')->nullable();
            $table->ulid('expected_destination_wallet_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tpr_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tpr_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign('linked_contract_payment_id', 'tpr_linked_cp_fk')
                ->references('id')->on('contract_payments');
            $table->foreign(
                ['tenant_id', 'linked_financial_document_id'],
                'tpr_linked_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'expected_destination_wallet_id'],
                'tpr_expected_dst_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');

            $table->unique(['tenant_id', 'id'], 'tpr_tenant_id_id_unique');
            $table->unique('linked_financial_document_id', 'tpr_linked_doc_id_unique');
            $table->index(['tenant_id'], 'tpr_tenant_id_idx');
        });

        TreasuryCheckConstraint::add(
            'treasury_payment_routes',
            'tpr_amount_positive_chk',
            'total_allocated_amount > 0',
            'NEW.total_allocated_amount > 0'
        );
        TreasuryCheckConstraint::add(
            'treasury_payment_routes',
            'tpr_link_exactly_one_chk',
            '(linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL)',
            '(NEW.linked_financial_document_id IS NULL) != (NEW.linked_contract_payment_id IS NULL)'
        );
        TreasuryCheckConstraint::add(
            'treasury_payment_routes',
            'tpr_contract_wallet_conullable_chk',
            '(linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL)',
            '(NEW.linked_contract_payment_id IS NOT NULL) = (NEW.expected_destination_wallet_id IS NOT NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_payment_routes');
    }
};
