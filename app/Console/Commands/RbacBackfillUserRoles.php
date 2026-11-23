<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Src\RBAC\Services\SystemRolePivotBackfill;

/**
 * UAT-RECOVERY-01 S2. DRY-RUN BY DEFAULT. Never run against staging/production without Owner approval.
 *
 *   php artisan rbac:backfill-user-roles                       # report only
 *   php artisan rbac:backfill-user-roles --apply [--allow-privileged-role=<role_id> ...]
 *   php artisan rbac:backfill-user-roles --rollback=<manifest.json>
 */
class RbacBackfillUserRoles extends Command
{
    protected $signature = 'rbac:backfill-user-roles
        {--apply : Insert missing user_roles rows (default is a read-only report)}
        {--allow-privileged-role=* : Role id explicitly approved by the Owner for backfill (privileged roles are otherwise skipped)}
        {--rollback= : Path to a manifest written by --apply; deletes exactly those rows}';

    protected $description = 'Reconcile system_user_roles into user_roles (dry-run by default, fail-closed, reversible)';

    public function handle(SystemRolePivotBackfill $backfill): int
    {
        if ($manifest = $this->option('rollback')) {
            if (!is_file((string) $manifest)) {
                $this->error('Manifest not found.');

                return self::FAILURE;
            }
            $data = json_decode((string) file_get_contents((string) $manifest), true);
            $deleted = $backfill->rollback($data['inserted'] ?? []);
            $this->info("Rolled back {$deleted} user_roles row(s).");

            return self::SUCCESS;
        }

        $allow = array_values(array_filter((array) $this->option('allow-privileged-role')));

        if (!$this->option('apply')) {
            $plan = $backfill->plan($allow);
            $this->line(json_encode($plan['summary']));
            foreach ($plan['rows'] as $r) {
                if ($r['action'] === SystemRolePivotBackfill::CONFLICT) {
                    $this->warn("CONFLICT user={$r['user_id']} role={$r['role_id']} reason={$r['reason']}");
                }
            }
            $this->info('Dry run only. Nothing was written.');

            return self::SUCCESS;
        }

        $result = $backfill->apply($allow);
        $dir = storage_path('app/rbac-backfill');
        File::ensureDirectoryExists($dir);
        $path = $dir . '/backfill-' . now()->format('Ymd-His') . '.json';
        file_put_contents($path, json_encode(['inserted' => $result['inserted'], 'summary' => $result['plan']['summary']], JSON_PRETTY_PRINT));
        $this->info('Inserted ' . count($result['inserted']) . " row(s). Rollback manifest: {$path}");

        return self::SUCCESS;
    }
}
