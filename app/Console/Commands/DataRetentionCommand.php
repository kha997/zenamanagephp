<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use App\Services\StructuredLoggingService;
use Illuminate\Console\Command;

class DataRetentionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:retention {--execute : Execute retention policies} {--status : Show retention status} {--cleanup : Clean up orphaned records}';

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
        if ($this->option('execute')) {
            return $this->executeRetentionPolicies();
        }
        
        if ($this->option('status')) {
            return $this->showRetentionStatus();
        }
        
        if ($this->option('cleanup')) {
            return $this->cleanupOrphanedRecords();
        }
        
        // Default: show status
        return $this->showRetentionStatus();
    }

    /**
     * Execute retention policies
     */
    protected function executeRetentionPolicies(): int
    {
        $this->info('Executing data retention policies...');
        
        try {
            $results = DataRetentionService::executeRetentionPolicies();
            
            $this->line('');
            $this->line('Retention Policy Results:');
            $this->line('========================');
            
            foreach ($results as $table => $result) {
                if ($result['success']) {
                    $this->line("<fg=green>✓ {$table}</>");
                    $this->line("  Type: {$result['retention_type']}");
                    $this->line("  Records affected: {$result['records_affected']}");
                    $this->line("  Cutoff date: {$result['cutoff_date']}");
                } else {
                    $this->line("<fg=red>✗ {$table}</>");
                    $this->line("  Error: {$result['error']}");
                }
                $this->line('');
            }
            
            StructuredLoggingService::logEvent('data_retention_executed', [
                'tables_processed' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ]);
            
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