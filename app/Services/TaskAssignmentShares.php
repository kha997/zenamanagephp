<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * OWN-2026-024 — SPLIT-PERCENT: the single rule for per-assignment workload shares (`task_assignments.split_percent`).
 *
 *  - A task's recorded shares may total at most 100 %.
 *  - NULL = "share not recorded" (rows written before the column existed). NULL rows are NOT counted and NOT rewritten here;
 *    how historical rows should count is a separate Owner decision.
 *  - Check-then-write must run inside {@see withTaskLock()}: the task row is locked FOR UPDATE, so two concurrent assignment
 *    writes on the same task serialise and cannot both pass the 100 % check (or both pass a duplicate check).
 */
final class TaskAssignmentShares
{
    public const DEFAULT_PERCENT = 100.0;

    public const MAX_TOTAL = 100.0;

    /**
     * Run $callback in a transaction holding an exclusive lock on the task row.
     *
     * @template T
     *
     * @param Closure(): T $callback
     * @return T
     */
    public static function withTaskLock(string $taskId, Closure $callback): mixed
    {
        return DB::transaction(static function () use ($taskId, $callback) {
            $locked = DB::table('tasks')->where('id', $taskId)->lockForUpdate()->first(['id']);
            if ($locked === null) {
                throw TaskAssignmentShareException::taskNotFound();
            }

            return $callback();
        });
    }

    /**
     * Sum of the recorded shares of the task, optionally excluding one assignment (the one being updated). A LOCKING read, so
     * the result is current even if this ever runs inside an outer transaction that already took a consistent snapshot.
     */
    public static function allocated(string $taskId, ?string $excludeAssignmentId = null): float
    {
        $query = DB::table('task_assignments')->where('task_id', $taskId)->whereNotNull('split_percent')->lockForUpdate();
        if ($excludeAssignmentId !== null) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return round((float) $query->sum('split_percent'), 2);
    }

    /** Normalise a requested share to the stored precision (DECIMAL(5,2)) before it is checked and written. */
    public static function normalise(float $percent): float
    {
        return round($percent, 2);
    }

    /** Is the user already assigned to the task (any role)? A locking read, for use inside {@see withTaskLock()}. */
    public static function isAssigned(string $taskId, string $userId): bool
    {
        return DB::table('task_assignments')->where('task_id', $taskId)->where('user_id', $userId)->lockForUpdate()->exists();
    }

    /** @throws TaskAssignmentShareException when $percent would push the task's recorded total above 100 % */
    public static function assertFits(string $taskId, float $percent, ?string $excludeAssignmentId = null): void
    {
        $current = self::allocated($taskId, $excludeAssignmentId);
        if (round($current + self::normalise($percent), 2) > self::MAX_TOTAL) {
            throw TaskAssignmentShareException::exceedsTotal($current);
        }
    }
}
