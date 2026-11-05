<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-112 (Owner Gate 2 T-1, M-1, C-1, R-1): per-person time entries on tasks or non-project categories, with review
 * state. New table only; task hour fields stay untouched (A-1).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('user_id');
            $table->ulid('project_id')->nullable();
            $table->string('task_id')->nullable();
            $table->string('category', 30)->nullable();
            $table->date('work_date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->string('source', 10);
            $table->boolean('auto_stopped')->default(false);
            $table->string('note', 500)->nullable();
            $table->string('status', 20);
            $table->ulid('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'te_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'te_user_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('project_id', 'te_project_id_fk')->references('id')->on('projects')->nullOnDelete();
            $table->index(['tenant_id', 'user_id', 'work_date'], 'te_user_date_idx');
            $table->index(['project_id', 'work_date', 'status'], 'te_project_date_status_idx');
            $table->index(['user_id', 'status'], 'te_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
