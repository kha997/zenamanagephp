<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfi_escalations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('rfi_id');
            $table->ulid('tenant_id');
            $table->ulid('escalated_to');
            $table->ulid('escalated_by');
            $table->timestamp('escalated_at');
            $table->text('escalation_reason');
            $table->timestamp('resolved_at')->nullable();
            $table->ulid('resolved_by')->nullable();
            $table->text('resolution')->nullable();
            $table->string('resolution_type')->nullable();
            $table->timestamps();

            // RESTRICT (the default when no onDelete is specified in MySQL/InnoDB): hard-deleting an
            // RFI that has escalation history must fail at the DB level, never silently cascade away
            // the audit trail. Do NOT add ->cascadeOnDelete() here.
            $table->foreign('rfi_id')->references('id')->on('rfis');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('escalated_to')->references('id')->on('users');
            $table->foreign('escalated_by')->references('id')->on('users');
            $table->foreign('resolved_by')->references('id')->on('users');

            $table->index(['rfi_id', 'resolved_at']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_escalations');
    }
};
