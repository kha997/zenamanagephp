<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_expense_approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('financial_document_id');
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->ulid('actor_id');
            $table->text('note')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tea_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tea_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign('actor_id', 'tea_actor_id_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tea_tenant_id_id_unique');
            $table->index(['financial_document_id'], 'tea_financial_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_expense_approvals');
    }
};
