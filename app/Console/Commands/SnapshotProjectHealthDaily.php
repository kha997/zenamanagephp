<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Reports\ProjectHealthSnapshotService;
use Illuminate\Console\Command;

/**
 * SnapshotProjectHealthDaily Command
 * 
 * Round 88: Daily Project Health Snapshots (command + schedule)
 * 
 * Creates daily project health snapshots for all (or a specific) tenant.
 * Can be run manually or scheduled via Laravel's task scheduler.
 */
class SnapshotProjectHealthDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project-health:snapshot-daily
                            {--tenant= : Limit to a specific tenant ID}
                            {--dry-run : Show what would be done without writing snapshots}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create daily project health snapshots for all (or a specific) tenant';

    /**
     * Execute the console command.
     */
    public function handle(ProjectHealthSnapshotService $snapshotService): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $this->info('Starting daily project health snapshot process...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No snapshots will be created');
        }

        // Determine which tenants to process
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found");
                return Command::FAILURE;
            }
            if (!$tenant->isActive()) {
                $this->warn("Tenant {$tenantId} is not active (status: {$tenant->status})");
            }
            $tenants = collect([$tenant]);
        } else {
            // Get all active tenants
            $tenants = Tenant::where('status', 'active')->get();
            $this->info("Found {$tenants->count()} active tenant(s)");
        }

        $totalProjects = 0;
        $totalSnapshots = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name} ({$tenant->id})");

            // Count projects for this tenant
            $projectCount = \App\Models\Project::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->count();

            $totalProjects += $projectCount;

            if ($dryRun) {
                $this->info("  Would create snapshots for {$projectCount} project(s)");
                $totalSnapshots += $projectCount;
            } else {
                try {
                    $count = $snapshotService->snapshotAllProjectsForTenant($tenant->id);
                    $this->info("  Created/updated health snapshots for {$count} project(s)");
                    $totalSnapshots += $count;
                } catch (\Exception $e) {
                    $this->error("  Failed to process tenant {$tenant->id}: {$e->getMessage()}");
                    \Log::error('Failed to create snapshots for tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with other tenants
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run complete: Would process {$totalProjects} project(s) across {$tenants->count()} tenant(s)");
        } else {
            $this->info("Successfully processed {$totalSnapshots} project snapshot(s) across {$tenants->count()} tenant(s)");
        }

        return Command::SUCCESS;
    }
}

