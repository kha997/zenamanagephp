<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use Illuminate\Console\Command;

/**
 * CleanupAuditLogs Command
 * 
 * Removes audit logs older than the retention period.
 * Should be run daily via scheduler.
 */
class CleanupAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup 
                            {--tenant= : Cleanup logs for specific tenant only}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup audit logs older than retention period';

    /**
     * Execute the console command.
     */
    public function handle(AuditService $auditService): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting audit log cleanup...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will be deleted');
        }
        
        if ($tenantId) {
            $this->info("Cleaning up logs for tenant: {$tenantId}");
        } else {
            $this->info('Cleaning up logs for all tenants');
        }
        
        if ($dryRun) {
            // Calculate what would be deleted
            $retentionYears = $auditService->getRetentionPeriod($tenantId);
            $cutoffDate = \Carbon\Carbon::now()->subYears($retentionYears);
            
            $query = \App\Models\AuditLog::where('created_at', '<', $cutoffDate);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $count = $query->count();
            $this->info("Would delete {$count} audit log records older than {$cutoffDate->toDateString()}");
            return 0;
        }
        
        $deleted = $auditService->cleanupOldLogs($tenantId);
        
        $this->info("Successfully deleted {$deleted} audit log records");
        
        return 0;
    }
}

