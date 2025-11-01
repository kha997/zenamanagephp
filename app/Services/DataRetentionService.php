<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DataRetentionService
{
    /**
     * Execute data retention policies
     */
    public static function executeRetentionPolicies(): array
    {
        $results = [];
        $policies = DB::table('data_retention_policies')
            ->where('is_active', true)
            ->get();
        
        foreach ($policies as $policy) {
            try {
                $result = self::executePolicy($policy);
                $results[$policy->table_name] = $result;
                
                Log::info("Data retention policy executed", [
                    'table' => $policy->table_name,
                    'retention_period' => $policy->retention_period,
                    'retention_type' => $policy->retention_type,
                    'records_affected' => $result['records_affected'],
                ]);
            } catch (\Exception $e) {
                $results[$policy->table_name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Data retention policy failed", [
                    'table' => $policy->table_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Execute a specific retention policy
     */
    protected static function executePolicy(object $policy): array
    {
        $cutoffDate = self::calculateCutoffDate($policy->retention_period);
        $tableName = $policy->table_name;
        
        switch ($policy->retention_type) {
            case 'soft_delete':
                return self::softDeleteRecords($tableName, $cutoffDate);
            case 'hard_delete':
                return self::hardDeleteRecords($tableName, $cutoffDate);
            case 'archive':
                return self::archiveRecords($tableName, $cutoffDate);
            default:
                throw new \InvalidArgumentException("Unknown retention type: {$policy->retention_type}");
        }
    }

    /**
     * Soft delete records older than cutoff date
     */
    protected static function softDeleteRecords(string $tableName, Carbon $cutoffDate): array
    {
        $query = DB::table($tableName)->where('created_at', '<', $cutoffDate);
        
        // Only add deleted_at condition if the column exists
        if (Schema::hasColumn($tableName, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        
        $count = $query->update(['deleted_at' => now()]);
        
        return [
            'success' => true,
            'retention_type' => 'soft_delete',
            'records_affected' => $count,
            'cutoff_date' => $cutoffDate->toISOString(),
        ];
    }

    /**
     * Hard delete records older than cutoff date
     */
    protected static function hardDeleteRecords(string $tableName, Carbon $cutoffDate): array
    {
        $count = DB::table($tableName)
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        return [
            'success' => true,
            'retention_type' => 'hard_delete',
            'records_affected' => $count,
            'cutoff_date' => $cutoffDate->toISOString(),
        ];
    }

    /**
     * Archive records older than cutoff date
     */
    protected static function archiveRecords(string $tableName, Carbon $cutoffDate): array
    {
        // Create archive table if it doesn't exist
        $archiveTableName = $tableName . '_archive';
        
        if (!Schema::hasTable($archiveTableName)) {
            self::createArchiveTable($tableName, $archiveTableName);
        }
        
        // Move records to archive table
        $records = DB::table($tableName)
            ->where('created_at', '<', $cutoffDate)
            ->get();
        
        if ($records->count() > 0) {
            DB::table($archiveTableName)->insert($records->toArray());
            
            DB::table($tableName)
                ->where('created_at', '<', $cutoffDate)
                ->delete();
        }
        
        return [
            'success' => true,
            'retention_type' => 'archive',
            'records_affected' => $records->count(),
            'cutoff_date' => $cutoffDate->toISOString(),
            'archive_table' => $archiveTableName,
        ];
    }

    /**
     * Create archive table
     */
    protected static function createArchiveTable(string $originalTable, string $archiveTable): void
    {
        $originalColumns = Schema::getColumnListing($originalTable);
        
        Schema::create($archiveTable, function (Blueprint $table) use ($originalColumns) {
            // Add all original columns
            foreach ($originalColumns as $column) {
                $columnType = Schema::getColumnType($originalTable, $column);
                $table->addColumn($columnType, $column);
            }
            
            // Add archive-specific columns
            $table->timestamp('archived_at')->default(now());
            $table->index(['archived_at']);
        });
    }

    /**
     * Calculate cutoff date based on retention period
     */
    protected static function calculateCutoffDate(string $retentionPeriod): Carbon
    {
        $period = strtolower(trim($retentionPeriod));
        
        if (str_contains($period, 'day')) {
            $days = (int) preg_replace('/[^0-9]/', '', $period);
            return now()->subDays($days);
        }
        
        if (str_contains($period, 'week')) {
            $weeks = (int) preg_replace('/[^0-9]/', '', $period);
            return now()->subWeeks($weeks);
        }
        
        if (str_contains($period, 'month')) {
            $months = (int) preg_replace('/[^0-9]/', '', $period);
            return now()->subMonths($months);
        }
        
        if (str_contains($period, 'year')) {
            $years = (int) preg_replace('/[^0-9]/', '', $period);
            return now()->subYears($years);
        }
        
        throw new \InvalidArgumentException("Invalid retention period format: {$retentionPeriod}");
    }

    /**
     * Get retention policy status
     */
    public static function getRetentionStatus(): array
    {
        $policies = DB::table('data_retention_policies')
            ->where('is_active', true)
            ->get();
        
        $status = [];
        
        foreach ($policies as $policy) {
            $cutoffDate = self::calculateCutoffDate($policy->retention_period);
            
            $status[$policy->table_name] = [
                'table_name' => $policy->table_name,
                'retention_period' => $policy->retention_period,
                'retention_type' => $policy->retention_type,
                'cutoff_date' => $cutoffDate->toISOString(),
                'records_to_process' => self::getRecordsToProcess($policy->table_name, $cutoffDate, $policy->retention_type),
            ];
        }
        
        return $status;
    }

    /**
     * Get count of records that would be processed
     */
    protected static function getRecordsToProcess(string $tableName, Carbon $cutoffDate, string $retentionType): int
    {
        $query = DB::table($tableName)->where('created_at', '<', $cutoffDate);
        
        if ($retentionType === 'soft_delete' && Schema::hasColumn($tableName, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        
        return $query->count();
    }

    /**
     * Clean up orphaned records
     */
    public static function cleanupOrphanedRecords(): array
    {
        $results = [];
        
        // Clean up orphaned document versions
        $orphanedVersions = DB::table('document_versions')
            ->leftJoin('documents', 'document_versions.document_id', '=', 'documents.id')
            ->whereNull('documents.id')
            ->count();
        
        if ($orphanedVersions > 0) {
            DB::table('document_versions')
                ->leftJoin('documents', 'document_versions.document_id', '=', 'documents.id')
                ->whereNull('documents.id')
                ->delete();
            
            $results['document_versions'] = [
                'orphaned_records' => $orphanedVersions,
                'action' => 'deleted',
            ];
        }
        
        // Clean up orphaned project activities
        $orphanedActivities = DB::table('project_activities')
            ->leftJoin('projects', 'project_activities.project_id', '=', 'projects.id')
            ->whereNull('projects.id')
            ->count();
        
        if ($orphanedActivities > 0) {
            DB::table('project_activities')
                ->leftJoin('projects', 'project_activities.project_id', '=', 'projects.id')
                ->whereNull('projects.id')
                ->delete();
            
            $results['project_activities'] = [
                'orphaned_records' => $orphanedActivities,
                'action' => 'deleted',
            ];
        }
        
        return $results;
    }
}
