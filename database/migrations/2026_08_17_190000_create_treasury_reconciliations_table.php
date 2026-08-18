<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('wallet_id');
            $table->string('reconciliation_type', 32);
            $table->string('external_reference')->nullable();
            $table->timestamp('reconciled_at');
            $table->ulid('reconciled_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'tr_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'wallet_id'],
                'tr_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign('reconciled_by', 'tr_reconciled_by_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tr_tenant_id_id_unique');
            $table->index(['wallet_id'], 'tr_wallet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliations');
    }
};
