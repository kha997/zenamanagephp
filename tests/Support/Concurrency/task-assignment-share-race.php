<?php

declare(strict_types=1);

/**
 * OWN-2026-024 SPLIT-PERCENT — test-only helper for UatRecovery08bSplitPercentConcurrencyTest (never routed, never loaded
 * by the application). One OS process = one allocation attempt through the production rule (TaskAssignmentShares), with a
 * deliberate pause between the 100 % check and the insert to widen the race window.
 *
 * Usage: php task-assignment-share-race.php <taskId> <userId> <tenantId> <percent> <pauseMs>
 * Prints "OK <id>" (exit 0) or "CONFLICT <reason>" (exit 1); any other exception propagates (stack trace, exit 255).
 */

use App\Services\TaskAssignmentShareException;
use App\Services\TaskAssignmentShares;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

[$self, $taskId, $userId, $tenantId, $percent, $pauseMs] = $argv + [null, null, null, null, '0', '0'];

require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $id = TaskAssignmentShares::withTaskLock((string) $taskId, static function () use ($taskId, $userId, $tenantId, $percent, $pauseMs): string {
        TaskAssignmentShares::assertFits((string) $taskId, (float) $percent);
        usleep(((int) $pauseMs) * 1000);
        $id = (string) Str::ulid();
        DB::table('task_assignments')->insert([
            'id' => $id, 'task_id' => $taskId, 'user_id' => $userId, 'tenant_id' => $tenantId, 'role' => 'assignee',
            'split_percent' => (float) $percent, 'status' => 'pending', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    });
    fwrite(STDOUT, "OK {$id}\n");
    exit(0);
} catch (TaskAssignmentShareException $e) {
    fwrite(STDOUT, "CONFLICT {$e->reason}\n");
    exit(1);
}
