<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use App\Services\StructuredLoggingService;
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
                $contexts[] = ['type' => 'tenant', 'tenant_id' => $tenant->id];
            }
        }

        if ($allTenants) {
            $tenantQuery = Tenant::query();

            if (Schema::hasColumn('tenants', 'deleted_at')) {
                $tenantQuery->whereNull('deleted_at');
            }

            $tenantIds = $tenantQuery->pluck('id');

            if ($tenantIds->isEmpty()) {
                $this->warn('No tenants found matching --all-tenants.');
            } else {
                foreach ($tenantIds as $tenantId) {
                    $contexts[] = ['type' => 'tenant', 'tenant_id' => $tenantId];
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

        try {
            foreach ($contexts as $context) {
                $tenantId = $context['tenant_id'] ?? null;
                $isSystem = $context['type'] === 'system';
                $contextLabel = $isSystem ? 'system tables' : "tenant {$tenantId}";

                $results = DataRetentionService::executeRetentionPolicies($tenantId, $dryRun, $isSystem);

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
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Data retention execution failed', $e);

            $this->error('Data retention execution failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
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
