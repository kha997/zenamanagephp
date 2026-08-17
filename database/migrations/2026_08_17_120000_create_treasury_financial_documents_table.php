<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_financial_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('document_type', 32);
            $table->string('status', 32);
            $table->string('posting_path', 16)->nullable();
            $table->decimal('amount', 15, 2);
            $table->ulid('source_wallet_id')->nullable();
            $table->ulid('destination_wallet_id')->nullable();
            $table->ulid('source_party_id')->nullable();
            $table->ulid('destination_party_id')->nullable();
            $table->text('description')->nullable();
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->ulid('reversed_document_id')->nullable();
            $table->ulid('replacement_document_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfd_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tfd_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign('created_by', 'tfd_created_by_fk')
                ->references('id')->on('users');
            $table->foreign('approved_by', 'tfd_approved_by_fk')
                ->references('id')->on('users');

            $table->foreign(['tenant_id', 'source_wallet_id'], 'tfd_src_wallet_fk')
                ->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(['tenant_id', 'destination_wallet_id'], 'tfd_dst_wallet_fk')
                ->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(['tenant_id', 'source_party_id'], 'tfd_src_party_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->foreign(['tenant_id', 'destination_party_id'], 'tfd_dst_party_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->unique(['tenant_id', 'id'], 'tfd_tenant_id_id_unique');

            $table->foreign(['tenant_id', 'reversed_document_id'], 'tfd_reversed_doc_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(['tenant_id', 'replacement_document_id'], 'tfd_replacement_doc_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_documents');

            $table->unique('reversed_document_id', 'tfd_reversed_document_id_unique');
            $table->index(['tenant_id'], 'tfd_tenant_id_idx');
            $table->index(['project_id'], 'tfd_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_financial_documents');
    }
};
