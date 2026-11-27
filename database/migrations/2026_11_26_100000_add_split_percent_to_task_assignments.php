<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OWN-2026-024 — SPLIT-PERCENT (expand only).
 *
 * `split_percent` is the canonical per-assignment workload share used by every assignment write path, the 100 % total
 * check, workload statistics and compensation (CompensationService), but no migration ever created the column: on MySQL every
 * write or SUM failed (SQLSTATE 42S22) and on SQLite SUM("split_percent") silently summed a string literal (0).
 *
 * The column is NULLABLE with NO default and NO backfill: existing (historical) rows keep NULL = "share not recorded". Their
 * business meaning is not inferred here (separate Owner decision); totals count only recorded shares. New rows get the value
 * the API supplies, or 100 when it is omitted (the existing API contract), written explicitly by the application.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('task_assignments', 'split_percent')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->decimal('split_percent', 5, 2)->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        // Production is roll-forward-only (OWN-2026-024). Rolling back discards recorded shares.
        if (Schema::hasColumn('task_assignments', 'split_percent')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->dropColumn('split_percent');
            });
        }
    }
};
