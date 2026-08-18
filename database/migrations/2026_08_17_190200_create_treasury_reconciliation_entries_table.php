<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliation_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('reconciliation_id');
            $table->ulid('ledger_entry_id');
            $table->string('direction', 8);
            $table->ulid('reverses_reconciliation_entry_id')->nullable();
            $table->ulid('actor_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'trce_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'reconciliation_id'],
                'trce_reconciliation_fk'
            )->references(['tenant_id', 'id'])->on('treasury_reconciliations');
            $table->foreign(
                ['tenant_id', 'ledger_entry_id'],
                'trce_ledger_entry_fk'
            )->references(['tenant_id', 'id'])->on('treasury_ledger_entries');
            $table->unique(['tenant_id', 'id'], 'trce_tenant_id_id_unique');

            $table->foreign(
                ['tenant_id', 'reverses_reconciliation_entry_id'],
                'trce_reverses_entry_fk'
            )->references(['tenant_id', 'id'])->on('treasury_reconciliation_entries');
            $table->foreign('actor_id', 'trce_actor_id_fk')
                ->references('id')->on('users');

            $table->unique('reverses_reconciliation_entry_id', 'trce_reverses_entry_unique');
            $table->index(['reconciliation_id'], 'trce_reconciliation_idx');
            $table->index(['ledger_entry_id'], 'trce_ledger_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliation_entries');
    }
};
