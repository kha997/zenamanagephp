<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DataRetentionService
{
    /**
     * Execute data retention policies
     */
    public static function executeRetentionPolicies(?string $tenantId = null, bool $dryRun = true, bool $allowSystem = false): array
    {
        $results = [];
        $context = $tenantId ? 'tenant' : ($allowSystem ? 'system' : 'global');
        $policies = DB::table('data_retention_policies')
            ->where('is_active', true)
            ->get();

        foreach ($policies as $policy) {
            try {
                $result = self::executePolicy($policy, $tenantId, $dryRun, $allowSystem);
                $results[$policy->table_name] = $result;

                Log::info('Data retention policy executed', [
                    'table' => $policy->table_name,
                    'retention_period' => $policy->retention_period,
                    'retention_type' => $policy->retention_type,
                    'records_affected' => $result['records_affected'],
                    'tenant_id' => $tenantId,
                    'context' => $context,
                    'dry_run' => $dryRun,
                    'skipped' => $result['skipped'] ?? false,
                ]);
            } catch (\Exception $e) {
                $results[$policy->table_name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error('Data retention policy failed', [
                    'table' => $policy->table_name,
                    'error' => $e->getMessage(),
                    'tenant_id' => $tenantId,
                    'context' => $context,
                ]);
            }
        }

        return $results;
    }

    /**
     * Execute a specific retention policy
     */
    protected static function executePolicy(object $policy, ?string $tenantId, bool $dryRun, bool $allowSystem): array
    {
        $cutoffDate = self::calculateCutoffDate($policy->retention_period);
        $tableName = $policy->table_name;
        $dateColumn = self::resolveDateColumn($policy, $tableName);

        if ($reason = self::getSkipReason($policy, $tenantId, $allowSystem, $dateColumn)) {
            return self::skippedPolicyResult($policy, $reason, $cutoffDate, $dryRun);
        }

        switch ($policy->retention_type) {
            case 'soft_delete':
                return self::softDeleteRecords($tableName, $dateColumn, $cutoffDate, $tenantId, $dryRun);
            case 'hard_delete':
                return self::hardDeleteRecords($tableName, $dateColumn, $cutoffDate, $tenantId, $dryRun);
            case 'archive':
                return self::archiveRecords($policy, $tableName, $dateColumn, $cutoffDate, $tenantId, $dryRun, $allowSystem);
            default:
                throw new \InvalidArgumentException("Unknown retention type: {$policy->retention_type}");
        }
    }

    /**
     * Soft delete records older than cutoff date
     */
    protected static function softDeleteRecords(string $tableName, string $dateColumn, Carbon $cutoffDate, ?string $tenantId, bool $dryRun): array
    {
        $pending = self::countRetentionRecords($tableName, $dateColumn, $cutoffDate, $tenantId, true);

        if ($dryRun) {
            return self::formatResult($tableName, 'soft_delete', $pending, $cutoffDate, $dryRun);
        }

        $affected = self::processRecordsInChunks(
            $tableName,
            $dateColumn,
            $cutoffDate,
            $tenantId,
            function (Collection $chunk) use ($tableName) {
                $ids = self::extractChunkIds($chunk);

                if (empty($ids)) {
                    return 0;
                }

                return DB::table($tableName)->whereIn('id', $ids)->update(['deleted_at' => now()]);
            },
            Schema::hasColumn($tableName, 'deleted_at')
        );

        return self::formatResult($tableName, 'soft_delete', $affected, $cutoffDate, $dryRun);
    }

    /**
     * Hard delete records older than cutoff date
     */
    protected static function hardDeleteRecords(string $tableName, string $dateColumn, Carbon $cutoffDate, ?string $tenantId, bool $dryRun): array
    {
        $pending = self::countRetentionRecords($tableName, $dateColumn, $cutoffDate, $tenantId, false);

        if ($dryRun) {
            return self::formatResult($tableName, 'hard_delete', $pending, $cutoffDate, $dryRun);
        }

        $deleted = self::processRecordsInChunks(
            $tableName,
            $dateColumn,
            $cutoffDate,
            $tenantId,
            function (Collection $chunk) use ($tableName) {
                $ids = self::extractChunkIds($chunk);

                if (empty($ids)) {
                    return 0;
                }

                return DB::table($tableName)->whereIn('id', $ids)->delete();
            }
        );

        return self::formatResult($tableName, 'hard_delete', $deleted, $cutoffDate, $dryRun);
    }

    /**
     * Archive records older than cutoff date
     */
    protected static function archiveRecords(object $policy, string $tableName, string $dateColumn, Carbon $cutoffDate, ?string $tenantId, bool $dryRun, bool $allowSystem): array
    {
        $archiveTableName = $tableName . '_archive';
        $pending = self::countRetentionRecords($tableName, $dateColumn, $cutoffDate, $tenantId, false);

        if (!$tenantId && !$dryRun) {
            return self::skippedPolicyResult($policy, 'Archive execution is restricted to tenant-scoped requests', $cutoffDate, $dryRun);
        }

        if ($dryRun) {
            return self::formatResult($tableName, 'archive', $pending, $cutoffDate, $dryRun);
        }

        if (!Schema::hasTable($archiveTableName)) {
            if (!self::createArchiveTable($tableName, $archiveTableName)) {
                return self::skippedPolicyResult($policy, 'Unable to create archive table', $cutoffDate, $dryRun);
            }
        }

        $moved = self::processRecordsInChunks(
            $tableName,
            $dateColumn,
            $cutoffDate,
            $tenantId,
            function (Collection $chunk) use ($tableName, $archiveTableName) {
                $rows = array_map(fn ($record) => (array) $record, $chunk->toArray());

                if (!empty($rows)) {
                    DB::table($archiveTableName)->insert($rows);
                }

                $ids = self::extractChunkIds($chunk);

                if (empty($ids)) {
                    return 0;
                }

                return DB::table($tableName)->whereIn('id', $ids)->delete();
            }
        );

        $result = self::formatResult($tableName, 'archive', $moved, $cutoffDate, $dryRun);
        $result['archive_table'] = $archiveTableName;

        return $result;
    }

    /**
     * Create archive table
     */
    protected static function createArchiveTable(string $originalTable, string $archiveTable): bool
    {
        try {
            $originalColumns = Schema::getColumnListing($originalTable);

            Schema::create($archiveTable, function (Blueprint $table) use ($originalTable, $originalColumns) {
                foreach ($originalColumns as $column) {
                    $columnType = Schema::getColumnType($originalTable, $column);
                    $table->addColumn($columnType, $column);
                }

                $table->timestamp('archived_at')->default(now());
                $table->index(['archived_at']);
            });

            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to create archive table', [
                'original_table' => $originalTable,
                'archive_table' => $archiveTable,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected static function getSkipReason(object $policy, ?string $tenantId, bool $allowSystem, ?string $dateColumn): ?string
    {
        $tableName = $policy->table_name;

        if (!Schema::hasTable($tableName)) {
            return 'Table does not exist';
        }

        if (!self::hasPrimaryKey($tableName)) {
            return 'Retention targets require an id column';
        }

        $hasTenantColumn = Schema::hasColumn($tableName, 'tenant_id');

        if ($hasTenantColumn && !$tenantId) {
            return 'Tenant context required';
        }

        if (!$hasTenantColumn && !$allowSystem) {
            return 'System flag required for global tables';
        }

        if (!$hasTenantColumn && $tenantId) {
            return 'Global tables cannot be scoped to a tenant';
        }

        if (!$dateColumn) {
            return 'Missing created_at or configured date column';
        }

        return null;
    }

    protected static function resolveDateColumn(object $policy, string $tableName): ?string
    {
        if (!Schema::hasTable($tableName)) {
            return null;
        }

        if (Schema::hasColumn($tableName, 'created_at')) {
            return 'created_at';
        }

        if (property_exists($policy, 'date_column') && $policy->date_column) {
            $candidate = $policy->date_column;

            if (Schema::hasColumn($tableName, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function buildRetentionQuery(string $tableName, string $dateColumn, Carbon $cutoffDate, ?string $tenantId): QueryBuilder
    {
        $query = DB::table($tableName)->where($dateColumn, '<', $cutoffDate);

        if ($tenantId && Schema::hasColumn($tableName, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    protected static function countRetentionRecords(string $tableName, string $dateColumn, Carbon $cutoffDate, ?string $tenantId, bool $excludeDeleted): int
    {
        $query = self::buildRetentionQuery($tableName, $dateColumn, $cutoffDate, $tenantId);

        if ($excludeDeleted && Schema::hasColumn($tableName, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }

    protected static function processRecordsInChunks(
        string $tableName,
        string $dateColumn,
        Carbon $cutoffDate,
        ?string $tenantId,
        callable $handler,
        bool $excludeDeleted = false
    ): int {
        $batchSize = 500;
        $processed = 0;
        $filterDeleted = $excludeDeleted && Schema::hasColumn($tableName, 'deleted_at');

        if (self::hasPrimaryKey($tableName)) {
            $query = self::buildRetentionQuery($tableName, $dateColumn, $cutoffDate, $tenantId);

            if ($filterDeleted) {
                $query->whereNull('deleted_at');
            }

            $query->orderBy('id');

            $query->chunkById($batchSize, function (Collection $chunk) use (&$processed, $handler) {
                $processed += $handler($chunk);
            });

            return $processed;
        }

        while (true) {
            $query = self::buildRetentionQuery($tableName, $dateColumn, $cutoffDate, $tenantId);

            if ($filterDeleted) {
                $query->whereNull('deleted_at');
            }

            $chunk = $query->limit($batchSize)->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $processed += $handler($chunk);
        }

        return $processed;
    }

    protected static function extractChunkIds(Collection $chunk): array
    {
        return $chunk->pluck('id')->filter()->values()->all();
    }

    protected static function hasPrimaryKey(string $tableName): bool
    {
        return Schema::hasColumn($tableName, 'id');
    }

    protected static function skippedPolicyResult(object $policy, string $reason, Carbon $cutoffDate, bool $dryRun): array
    {
        return self::formatResult($policy->table_name, $policy->retention_type, 0, $cutoffDate, $dryRun, true, $reason);
    }

    protected static function formatResult(string $tableName, string $type, int $count, Carbon $cutoffDate, bool $dryRun, bool $skipped = false, ?string $reason = null): array
    {
        $result = [
            'success' => true,
            'retention_type' => $type,
            'records_affected' => $count,
            'cutoff_date' => $cutoffDate->toISOString(),
            'dry_run' => $dryRun,
        ];

        if ($skipped) {
            $result['skipped'] = true;
            $result['reason'] = $reason;
        }

        return $result;
    }

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
                'records_to_process' => self::getRecordsToProcess($policy, $cutoffDate),
            ];
        }

        return $status;
    }

    /**
     * Get count of records that would be processed
     */
    protected static function getRecordsToProcess(object $policy, Carbon $cutoffDate): int
    {
        $tableName = $policy->table_name;
        $dateColumn = self::resolveDateColumn($policy, $tableName);

        if (!$dateColumn || !Schema::hasTable($tableName) || !self::hasPrimaryKey($tableName)) {
            return 0;
        }

        $query = self::buildRetentionQuery($tableName, $dateColumn, $cutoffDate, null);

        if ($policy->retention_type === 'soft_delete' && Schema::hasColumn($tableName, 'deleted_at')) {
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
