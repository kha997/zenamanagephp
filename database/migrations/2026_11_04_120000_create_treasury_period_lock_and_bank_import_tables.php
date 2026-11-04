<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-107 (Owner Gate 2 L-1, U-1, I-1): per-project period-lock history (the latest row is the current lock; a null
 * date means unlocked) and bank statement imports with their lines. New tables only; no existing table changes.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('treasury_period_locks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->date('locked_through')->nullable();
            $table->string('reason', 500)->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'tpl_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tpl_project_id_fk')->references('id')->on('projects');
            $table->index(['tenant_id', 'project_id', 'created_at'], 'tpl_project_created_idx');
        });

        Schema::create('treasury_bank_statement_imports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('wallet_id');
            $table->string('statement_reference', 100);
            $table->string('original_name', 255);
            $table->char('file_hash', 64);
            $table->json('column_map');
            $table->unsignedInteger('line_count');
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'tbsi_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tbsi_project_id_fk')->references('id')->on('projects');
            $table->foreign(['tenant_id', 'wallet_id'], 'tbsi_wallet_fk')->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->unique(['wallet_id', 'file_hash'], 'tbsi_wallet_hash_unique');
            $table->unique(['tenant_id', 'id'], 'tbsi_tenant_id_id_unique');
        });

        Schema::create('treasury_bank_statement_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('import_id');
            $table->unsignedInteger('line_no');
            $table->date('transaction_date');
            $table->string('direction', 10);
            $table->decimal('amount', 15, 2);
            $table->string('description', 1000)->nullable();
            $table->string('bank_reference', 255)->nullable();
            $table->char('fingerprint', 64);
            $table->string('status', 20);
            $table->ulid('ledger_entry_id')->nullable();
            $table->ulid('reconciliation_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tbsl_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'import_id'], 'tbsl_import_fk')->references(['tenant_id', 'id'])->on('treasury_bank_statement_imports')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'ledger_entry_id'], 'tbsl_ledger_entry_fk')->references(['tenant_id', 'id'])->on('treasury_ledger_entries');
            $table->foreign(['tenant_id', 'reconciliation_id'], 'tbsl_reconciliation_fk')->references(['tenant_id', 'id'])->on('treasury_reconciliations');
            $table->index(['import_id', 'status'], 'tbsl_import_status_idx');
            $table->index(['tenant_id', 'fingerprint'], 'tbsl_fingerprint_idx');
            $table->index(['ledger_entry_id'], 'tbsl_ledger_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_bank_statement_lines');
        Schema::dropIfExists('treasury_bank_statement_imports');
        Schema::dropIfExists('treasury_period_locks');
    }
};
