<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use App\Services\StructuredLoggingService;
use App\Services\TenancyService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DataRetentionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:retention {--execute : Execute retention policies} {--status : Show retention status} {--cleanup : Clean up orphaned records} {--tenant= : Run for a specific tenant ULID} {--all-tenants : Run across all active tenants} {--system : Include tables without tenant_id} {--dry-run : Show counts without deleting records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage data retention policies and cleanup old records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('status')) {
            return $this->showRetentionStatus();
        }

        if ($this->option('cleanup')) {
            return $this->cleanupOrphanedRecords();
        }

        $hasRetentionScope =
            $this->option('tenant') !== null ||
            $this->option('all-tenants') ||
            $this->option('system');

        if (!$hasRetentionScope) {
            if ($this->option('execute')) {
                $this->warn('Execution requires --tenant, --all-tenants, or --system scope.');
                return Command::SUCCESS;
            }

            return $this->showRetentionStatus();
        }

        return $this->executeRetentionPolicies();
    }

    /**
     * Execute retention policies
     */
    protected function executeRetentionPolicies(): int
    {
        $this->info('Executing data retention policies...');

        $tenantOption = $this->option('tenant');
        $allTenants = $this->option('all-tenants');
        $system = $this->option('system');
        $execute = $this->option('execute');
        $dryRun = !$execute || $this->option('dry-run');

        if ($tenantOption && $allTenants) {
            $this->error('Cannot combine --tenant and --all-tenants flags.');
            return Command::INVALID;
        }

        $contexts = [];

        if ($tenantOption) {
            $tenantQuery = Tenant::where('id', $tenantOption);

            if (Schema::hasColumn('tenants', 'deleted_at')) {
                $tenantQuery->whereNull('deleted_at');
            }

            $tenant = $tenantQuery->first();

            if (!$tenant) {
                $this->warn("Tenant {$tenantOption} not found or archived, skipping.");
            } else {
                $contexts[] = ['type' => 'tenant', 'tenant' => $tenant];
            }
        }

        if ($allTenants) {
            $tenantQuery = Tenant::query();

            if (Schema::hasColumn('tenants', 'deleted_at')) {
                $tenantQuery->whereNull('deleted_at');
            }

            $tenants = $tenantQuery->get();

            if ($tenants->isEmpty()) {
                $this->warn('No tenants found matching --all-tenants.');
            } else {
                foreach ($tenants as $tenant) {
                    $contexts[] = ['type' => 'tenant', 'tenant' => $tenant];
                }
            }
        }

        if ($system) {
            $contexts[] = ['type' => 'system'];
        }

        if (empty($contexts)) {
            $this->warn('Specify --tenant, --all-tenants, or --system to execute retention policies.');
            return Command::SUCCESS;
        }

        $tenancyService = app(TenancyService::class);
        $tenantSummaries = [];
        $failures = [];
        $exitCode = Command::SUCCESS;

        foreach ($contexts as $context) {
            $tenant = $context['tenant'] ?? null;
            $tenantId = $tenant?->id ?? ($context['tenant_id'] ?? null);
            $isSystem = $context['type'] === 'system';
            $contextLabel = $isSystem ? 'system tables' : "tenant {$tenantId}";

            try {
                if ($tenantId && $tenant) {
                    $tenancyService->setTenantContext($tenantId, $tenant);
                } else {
                    $tenancyService->clearTenantContext();
                }

                $results = DataRetentionService::executeRetentionPolicies($tenantId, $dryRun, $isSystem, $tenant);

                $this->line('');
                $this->line("Retention Policy Results ({$contextLabel}) [dry run: " . ($dryRun ? 'yes' : 'no') . "]");
                $this->line('========================');

                $successful = 0;
                $failed = 0;
                $skipped = 0;

                foreach ($results as $table => $result) {
                    if (!empty($result['skipped'])) {
                        $skipped++;
                        $this->line("<fg=yellow>• {$table} (skipped)</>");
                        $this->line("  Reason: {$result['reason']}");
                    } elseif (isset($result['success']) && $result['success']) {
                        $successful++;
                        $this->line("<fg=green>✓ {$table}</>");
                        $this->line("  Type: {$result['retention_type']}");
                        $this->line("  Records affected: {$result['records_affected']}");
                        $this->line("  Cutoff date: {$result['cutoff_date']}");
                    } else {
                        $failed++;
                        $errorMessage = isset($result['error']) ? $result['error'] : 'Unknown error';
                        $this->line("<fg=red>✗ {$table}</>");
                        $this->line("  Error: {$errorMessage}");
                    }
                    $this->line('');
                }

                $this->line("<fg=cyan>Summary:</> {$successful} succeeded, {$failed} failed, {$skipped} skipped.");
                $this->line("Dry run: " . ($dryRun ? 'yes' : 'no'));
                $this->line('');

                StructuredLoggingService::logEvent('data_retention_executed', [
                    'context' => $contextLabel,
                    'tenant_id' => $tenantId,
                    'tables_processed' => count($results),
                    'successful' => $successful,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'dry_run' => $dryRun,
                ]);

                if (!$isSystem) {
                    $tenantSummaries[] = [
                        'tenant_id' => $tenantId,
                        'tenant_name' => $tenant?->name,
                        'successful' => $successful,
                        'failed' => $failed,
                        'skipped' => $skipped,
                    ];
                }
            } catch (\Exception $e) {
                $exitCode = Command::FAILURE;
                $failures[] = [
                    'context' => $contextLabel,
                    'tenant_id' => $tenantId,
                    'message' => $e->getMessage(),
                ];

                StructuredLoggingService::logError('Data retention execution failed', $e, [
                    'context' => $contextLabel,
                    'tenant_id' => $tenantId,
                ]);

                $this->error("Retention failed for {$contextLabel}: " . $e->getMessage());
            } finally {
                $tenancyService->clearTenantContext();
            }
        }

        if ($allTenants && !empty($tenantSummaries)) {
            $this->line('');
            $this->line('Tenant execution summary:');
            $this->line('========================');

            foreach ($tenantSummaries as $summary) {
                $label = $summary['tenant_name'] ?
                    "{$summary['tenant_name']} ({$summary['tenant_id']})" :
                    ($summary['tenant_id'] ?? 'Unknown tenant');

                $this->line("• {$label}: {$summary['successful']} succeeded, {$summary['failed']} failed, {$summary['skipped']} skipped.");
            }

            $this->line('');
        }

        if (!empty($failures)) {
            $this->line('<fg=red>Failures detected:</>');

            foreach ($failures as $failure) {
                $this->line(" - {$failure['context']}: {$failure['message']}");
            }

            $this->line('');
        }

        return $exitCode;
    }

    /**
     * Show retention status
     */
    protected function showRetentionStatus(): int
    {
        $this->info('Data Retention Status:');
        
        try {
            $status = DataRetentionService::getRetentionStatus();
            
            $this->line('');
            $this->line('Retention Policies:');
            $this->line('==================');
            
            foreach ($status as $table => $policy) {
                $this->line("Table: <fg=cyan>{$table}</>");
                $this->line("  Retention Period: {$policy['retention_period']}");
                $this->line("  Retention Type: {$policy['retention_type']}");
                $this->line("  Cutoff Date: {$policy['cutoff_date']}");
                $this->line("  Records to Process: {$policy['records_to_process']}");
                $this->line('');
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Data retention status failed', $e);
            
            $this->error('Failed to get retention status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clean up orphaned records
     */
    protected function cleanupOrphanedRecords(): int
    {
        $this->info('Cleaning up orphaned records...');
        
        try {
            $results = DataRetentionService::cleanupOrphanedRecords();
            
            if (empty($results)) {
                $this->line('<fg=green>No orphaned records found</>');
                return Command::SUCCESS;
            }
            
            $this->line('');
            $this->line('Orphaned Records Cleanup:');
            $this->line('========================');
            
            foreach ($results as $table => $result) {
                $this->line("<fg=yellow>{$table}</>");
                $this->line("  Orphaned records: {$result['orphaned_records']}");
                $this->line("  Action: {$result['action']}");
                $this->line('');
            }
            
            StructuredLoggingService::logEvent('orphaned_records_cleaned', [
                'tables_cleaned' => count($results),
                'total_orphaned' => array_sum(array_column($results, 'orphaned_records')),
            ]);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Orphaned records cleanup failed', $e);
            
            $this->error('Orphaned records cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
