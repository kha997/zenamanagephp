<?php

namespace App\Services;

use App\Traits\SkipsSchemaIntrospection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SchemaAuditService
{
    use SkipsSchemaIntrospection;

    /**
     * Audit documents and history schemas
     */
    public static function auditDocumentsAndHistory(): array
    {
        $audit = [
            'timestamp' => now()->toISOString(),
            'documents_table' => self::auditDocumentsTable(),
            'document_versions_table' => self::auditDocumentVersionsTable(),
            'project_activities_table' => self::auditProjectActivitiesTable(),
            'audit_logs_table' => self::auditAuditLogsTable(),
            'recommendations' => self::generateRecommendations(),
            'performance_analysis' => self::analyzePerformance(),
        ];

        return $audit;
    }

    /**
     * Audit documents table schema
     */
    protected static function auditDocumentsTable(): array
    {
        $table = 'documents';
        $columns = Schema::getColumnListing($table);
        $indexes = self::getTableIndexes($table);
        $foreignKeys = self::getTableForeignKeys($table);
        
        return [
            'table_name' => $table,
            'columns' => $columns,
            'column_count' => count($columns),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'issues' => self::analyzeDocumentsTableIssues($columns, $indexes, $foreignKeys),
            'optimizations' => self::suggestDocumentsTableOptimizations($columns, $indexes),
        ];
    }

    /**
     * Audit document_versions table schema
     */
    protected static function auditDocumentVersionsTable(): array
    {
        $table = 'document_versions';
        $columns = Schema::getColumnListing($table);
        $indexes = self::getTableIndexes($table);
        $foreignKeys = self::getTableForeignKeys($table);
        
        return [
            'table_name' => $table,
            'columns' => $columns,
            'column_count' => count($columns),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'issues' => self::analyzeDocumentVersionsTableIssues($columns, $indexes, $foreignKeys),
            'optimizations' => self::suggestDocumentVersionsTableOptimizations($columns, $indexes),
        ];
    }

    /**
     * Audit project_activities table schema
     */
    protected static function auditProjectActivitiesTable(): array
    {
        $table = 'project_activities';
        $columns = Schema::getColumnListing($table);
        $indexes = self::getTableIndexes($table);
        $foreignKeys = self::getTableForeignKeys($table);
        
        return [
            'table_name' => $table,
            'columns' => $columns,
            'column_count' => count($columns),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'issues' => self::analyzeProjectActivitiesTableIssues($columns, $indexes, $foreignKeys),
            'optimizations' => self::suggestProjectActivitiesTableOptimizations($columns, $indexes),
        ];
    }

    /**
     * Audit audit_logs table schema
     */
    protected static function auditAuditLogsTable(): array
    {
        $table = 'audit_logs';
        $columns = Schema::getColumnListing($table);
        $indexes = self::getTableIndexes($table);
        $foreignKeys = self::getTableForeignKeys($table);
        
        return [
            'table_name' => $table,
            'columns' => $columns,
            'column_count' => count($columns),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'issues' => self::analyzeAuditLogsTableIssues($columns, $indexes, $foreignKeys),
            'optimizations' => self::suggestAuditLogsTableOptimizations($columns, $indexes),
        ];
    }

    /**
     * Analyze documents table issues
     */
    protected static function analyzeDocumentsTableIssues(array $columns, array $indexes, array $foreignKeys): array
    {
        $issues = [];
        
        // Check for missing indexes
        if (!self::hasIndex($indexes, ['tenant_id', 'status'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'medium',
                'description' => 'Missing composite index on (tenant_id, status) for tenant-scoped queries',
                'impact' => 'Slow queries when filtering documents by tenant and status',
            ];
        }
        
        if (!self::hasIndex($indexes, ['project_id', 'category', 'status'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'medium',
                'description' => 'Missing composite index on (project_id, category, status) for project document filtering',
                'impact' => 'Slow queries when filtering project documents by category and status',
            ];
        }
        
        if (!self::hasIndex($indexes, ['created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'low',
                'description' => 'Missing index on created_at for time-based queries',
                'impact' => 'Slow queries when sorting or filtering by creation date',
            ];
        }
        
        // Check for potential data integrity issues
        if (!in_array('tenant_id', $columns) || !in_array('project_id', $columns)) {
            $issues[] = [
                'type' => 'data_integrity',
                'severity' => 'high',
                'description' => 'Missing tenant_id or project_id columns for proper data isolation',
                'impact' => 'Potential data leakage between tenants or projects',
            ];
        }
        
        // Check for file hash uniqueness
        if (!self::hasUniqueIndex($indexes, ['file_hash'])) {
            $issues[] = [
                'type' => 'data_integrity',
                'severity' => 'medium',
                'description' => 'File hash should be unique to prevent duplicate file storage',
                'impact' => 'Potential duplicate file storage and inconsistent data',
            ];
        }
        
        return $issues;
    }

    /**
     * Analyze document_versions table issues
     */
    protected static function analyzeDocumentVersionsTableIssues(array $columns, array $indexes, array $foreignKeys): array
    {
        $issues = [];
        
        // Check for missing indexes
        if (!self::hasIndex($indexes, ['document_id', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'medium',
                'description' => 'Missing composite index on (document_id, created_at) for version history queries',
                'impact' => 'Slow queries when retrieving document version history',
            ];
        }
        
        if (!self::hasIndex($indexes, ['created_by', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'low',
                'description' => 'Missing composite index on (created_by, created_at) for user activity queries',
                'impact' => 'Slow queries when tracking user document version activity',
            ];
        }
        
        // Check for version number integrity
        if (!self::hasUniqueIndex($indexes, ['document_id', 'version_number'])) {
            $issues[] = [
                'type' => 'data_integrity',
                'severity' => 'high',
                'description' => 'Missing unique constraint on (document_id, version_number)',
                'impact' => 'Potential duplicate version numbers for the same document',
            ];
        }
        
        return $issues;
    }

    /**
     * Analyze project_activities table issues
     */
    protected static function analyzeProjectActivitiesTableIssues(array $columns, array $indexes, array $foreignKeys): array
    {
        $issues = [];
        
        // Check for missing indexes
        if (!self::hasIndex($indexes, ['entity_type', 'entity_id', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'medium',
                'description' => 'Missing composite index on (entity_type, entity_id, created_at) for entity history queries',
                'impact' => 'Slow queries when retrieving activity history for specific entities',
            ];
        }
        
        if (!self::hasIndex($indexes, ['action', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'low',
                'description' => 'Missing composite index on (action, created_at) for action-based queries',
                'impact' => 'Slow queries when filtering activities by action type',
            ];
        }
        
        // Check for tenant isolation
        if (!in_array('tenant_id', $columns)) {
            $issues[] = [
                'type' => 'data_integrity',
                'severity' => 'high',
                'description' => 'Missing tenant_id column for proper tenant isolation',
                'impact' => 'Potential data leakage between tenants in activity logs',
            ];
        }
        
        return $issues;
    }

    /**
     * Analyze audit_logs table issues
     */
    protected static function analyzeAuditLogsTableIssues(array $columns, array $indexes, array $foreignKeys): array
    {
        $issues = [];
        
        // Check for missing indexes
        if (!self::hasIndex($indexes, ['entity_type', 'entity_id', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'medium',
                'description' => 'Missing composite index on (entity_type, entity_id, created_at) for entity audit queries',
                'impact' => 'Slow queries when retrieving audit history for specific entities',
            ];
        }
        
        if (!self::hasIndex($indexes, ['action', 'created_at'])) {
            $issues[] = [
                'type' => 'missing_index',
                'severity' => 'low',
                'description' => 'Missing composite index on (action, created_at) for action-based audit queries',
                'impact' => 'Slow queries when filtering audit logs by action type',
            ];
        }
        
        // Check for data retention
        $issues[] = [
            'type' => 'data_retention',
            'severity' => 'medium',
            'description' => 'Consider implementing data retention policy for audit logs',
            'impact' => 'Audit logs may grow indefinitely without proper cleanup',
        ];
        
        return $issues;
    }

    /**
     * Suggest documents table optimizations
     */
    protected static function suggestDocumentsTableOptimizations(array $columns, array $indexes): array
    {
        $optimizations = [];
        
        // Add composite indexes for common query patterns
        $optimizations[] = [
            'type' => 'index',
            'name' => 'documents_tenant_status_index',
            'columns' => ['tenant_id', 'status'],
            'description' => 'Optimize tenant-scoped status queries',
        ];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'documents_project_category_status_index',
            'columns' => ['project_id', 'category', 'status'],
            'description' => 'Optimize project document filtering by category and status',
        ];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'documents_created_at_index',
            'columns' => ['created_at'],
            'description' => 'Optimize time-based queries and sorting',
        ];
        
        // Add unique constraint for file hash
        $optimizations[] = [
            'type' => 'unique_constraint',
            'name' => 'documents_file_hash_unique',
            'columns' => ['file_hash'],
            'description' => 'Prevent duplicate file storage',
        ];
        
        return $optimizations;
    }

    /**
     * Suggest document_versions table optimizations
     */
    protected static function suggestDocumentVersionsTableOptimizations(array $columns, array $indexes): array
    {
        $optimizations = [];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'document_versions_document_created_index',
            'columns' => ['document_id', 'created_at'],
            'description' => 'Optimize version history queries',
        ];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'document_versions_created_by_created_index',
            'columns' => ['created_by', 'created_at'],
            'description' => 'Optimize user activity queries',
        ];
        
        return $optimizations;
    }

    /**
     * Suggest project_activities table optimizations
     */
    protected static function suggestProjectActivitiesTableOptimizations(array $columns, array $indexes): array
    {
        $optimizations = [];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'project_activities_entity_history_index',
            'columns' => ['entity_type', 'entity_id', 'created_at'],
            'description' => 'Optimize entity history queries',
        ];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'project_activities_action_created_index',
            'columns' => ['action', 'created_at'],
            'description' => 'Optimize action-based queries',
        ];
        
        // Add tenant_id column if missing
        if (!in_array('tenant_id', $columns)) {
            $optimizations[] = [
                'type' => 'column',
                'name' => 'tenant_id',
                'type' => 'string',
                'description' => 'Add tenant_id column for proper tenant isolation',
            ];
        }
        
        return $optimizations;
    }

    /**
     * Suggest audit_logs table optimizations
     */
    protected static function suggestAuditLogsTableOptimizations(array $columns, array $indexes): array
    {
        $optimizations = [];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'audit_logs_entity_history_index',
            'columns' => ['entity_type', 'entity_id', 'created_at'],
            'description' => 'Optimize entity audit history queries',
        ];
        
        $optimizations[] = [
            'type' => 'index',
            'name' => 'audit_logs_action_created_index',
            'columns' => ['action', 'created_at'],
            'description' => 'Optimize action-based audit queries',
        ];
        
        return $optimizations;
    }

    /**
     * Generate overall recommendations
     */
    protected static function generateRecommendations(): array
    {
        return [
            [
                'priority' => 'high',
                'category' => 'data_integrity',
                'title' => 'Implement Tenant Isolation',
                'description' => 'Ensure all tables have proper tenant_id columns and constraints',
                'tables' => ['project_activities'],
            ],
            [
                'priority' => 'high',
                'category' => 'data_integrity',
                'title' => 'Add Unique Constraints',
                'description' => 'Add unique constraints to prevent duplicate data',
                'tables' => ['documents', 'document_versions'],
            ],
            [
                'priority' => 'medium',
                'category' => 'performance',
                'title' => 'Add Composite Indexes',
                'description' => 'Add composite indexes for common query patterns',
                'tables' => ['documents', 'document_versions', 'project_activities', 'audit_logs'],
            ],
            [
                'priority' => 'medium',
                'category' => 'data_retention',
                'title' => 'Implement Data Retention',
                'description' => 'Implement data retention policies for audit logs and activities',
                'tables' => ['audit_logs', 'project_activities'],
            ],
            [
                'priority' => 'low',
                'category' => 'performance',
                'title' => 'Add Time-based Indexes',
                'description' => 'Add indexes on created_at columns for time-based queries',
                'tables' => ['documents', 'document_versions'],
            ],
        ];
    }

    /**
     * Analyze performance characteristics
     */
    protected static function analyzePerformance(): array
    {
        $analysis = [];
        
        // Analyze table sizes
        $tables = ['documents', 'document_versions', 'project_activities', 'audit_logs'];
        
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $analysis[$table] = [
                    'row_count' => $count,
                    'size_category' => self::getSizeCategory($count),
                    'performance_impact' => self::getPerformanceImpact($count),
                ];
            } catch (\Exception $e) {
                $analysis[$table] = [
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $analysis;
    }

    /**
     * Get table indexes
     */
    protected static function getTableIndexes(string $table): array
    {
        if (self::shouldSkipSchemaIntrospection()) {
            return [];
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            return array_map(function($index) {
                return [
                    'name' => $index->Key_name,
                    'column' => $index->Column_name,
                    'unique' => $index->Non_unique == 0,
                    'type' => $index->Index_type,
                ];
            }, $indexes);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get table foreign keys
     */
    protected static function getTableForeignKeys(string $table): array
    {
        if (self::shouldSkipSchemaIntrospection()) {
            return [];
        }

        try {
            $foreignKeys = DB::select("
                SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME,
                    UPDATE_RULE,
                    DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            return $foreignKeys;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if table has specific index
     */
    protected static function hasIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            if ($index['name'] === implode('_', $columns) . '_index' || 
                $index['name'] === implode('_', $columns) . '_unique') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if table has unique index
     */
    protected static function hasUniqueIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            if ($index['unique'] && $index['column'] === $columns[0]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get size category based on row count
     */
    protected static function getSizeCategory(int $count): string
    {
        if ($count < 1000) return 'small';
        if ($count < 10000) return 'medium';
        if ($count < 100000) return 'large';
        return 'very_large';
    }

    /**
     * Get performance impact based on row count
     */
    protected static function getPerformanceImpact(int $count): string
    {
        if ($count < 1000) return 'minimal';
        if ($count < 10000) return 'low';
        if ($count < 100000) return 'medium';
        return 'high';
    }
}
