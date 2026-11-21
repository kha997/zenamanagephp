<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UAT-RECOVERY-04d (O-15): READ-ONLY report of projects that have no PM, grouped by tenant.
 * It never writes and never assigns anyone — adoption of a legacy PM-less project is a per-project, audited action by a
 * project.assign_manager holder (Owner decision O-15).
 *
 *   php artisan projects:pm-less-report [--tenant=<id>]
 */
class ProjectsPmLessReport extends Command
{
    protected $signature = 'projects:pm-less-report {--tenant= : Limit to one tenant id}';

    protected $description = 'Read-only: list projects without a project manager (no automatic assignment)';

    public function handle(): int
    {
        $rows = DB::table('projects')
            ->whereNull('pm_id')
            ->whereNull('deleted_at')
            ->when($this->option('tenant'), fn ($q, $tenant) => $q->where('tenant_id', $tenant))
            ->orderBy('tenant_id')->orderBy('name')
            ->get(['tenant_id', 'id', 'code', 'name', 'status', 'created_at']);

        if ($rows->isEmpty()) {
            $this->info('No project without a project manager.');

            return self::SUCCESS;
        }

        foreach ($rows->groupBy('tenant_id') as $tenantId => $projects) {
            $this->line("tenant {$tenantId}: {$projects->count()} project(s) without a PM");
            foreach ($projects as $p) {
                $this->line("  {$p->id}  {$p->code}  {$p->name}  [{$p->status}]");
            }
        }
        $this->info("Total: {$rows->count()}. Read-only — nothing was changed.");

        return self::SUCCESS;
    }
}
