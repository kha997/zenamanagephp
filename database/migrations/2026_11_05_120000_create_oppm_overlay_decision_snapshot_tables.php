<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-110 (Owner Gate 2 A-1, D-1, K-1): OPPM management overlay per root component and per project, management
 * decisions, and append-only period snapshots. New tables only; every row carries tenant_id and project_id because
 * components have no tenant column.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('oppm_workstream_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('component_id');
            $table->string('outcome', 500)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('next_action', 500)->nullable();
            $table->ulid('next_action_owner_id')->nullable();
            $table->date('next_action_due')->nullable();
            $table->string('note', 1000)->nullable();
            $table->integer('display_order')->nullable();
            $table->ulid('updated_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'own_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'own_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('component_id', 'own_component_id_fk')->references('id')->on('components')->cascadeOnDelete();
            $table->foreign('next_action_owner_id', 'own_owner_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['project_id', 'component_id'], 'own_project_component_unique');
        });

        Schema::create('oppm_project_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('summary', 2000)->nullable();
            $table->string('next_action', 500)->nullable();
            $table->ulid('next_action_owner_id')->nullable();
            $table->date('next_action_due')->nullable();
            $table->ulid('updated_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'opn_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'opn_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('next_action_owner_id', 'opn_owner_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique('project_id', 'opn_project_unique');
        });

        Schema::create('oppm_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('title', 255);
            $table->string('context', 2000)->nullable();
            $table->ulid('decision_maker_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('outcome', 2000)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'od_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'od_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('decision_maker_id', 'od_maker_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['project_id', 'status', 'due_date'], 'od_project_status_due_idx');
        });

        Schema::create('oppm_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('period_label', 100);
            $table->longText('payload'); // exact JSON text, so the stored bytes match payload_hash on every driver
            $table->char('payload_hash', 64);
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'os_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'os_project_id_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'created_at'], 'os_project_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oppm_snapshots');
        Schema::dropIfExists('oppm_decisions');
        Schema::dropIfExists('oppm_project_notes');
        Schema::dropIfExists('oppm_workstream_notes');
    }
};
