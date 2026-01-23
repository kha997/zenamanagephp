<?php

namespace App\Services;

use App\Traits\SkipsSchemaIntrospection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class N1IndexingAuditService
{
    use SkipsSchemaIntrospection;

    /**
     * Perform comprehensive N+1 and indexing audit
     */
    public static function auditN1AndIndexing(): array
    {
        $audit = [
            'timestamp' => now()->toISOString(),
            'n1_analysis' => self::analyzeN1Queries(),
            'indexing_analysis' => self::analyzeIndexing(),
            'query_performance' => self::analyzeQueryPerformance(),
            'recommendations' => self::generateRecommendations(),
            'optimization_plan' => self::generateOptimizationPlan(),
        ];

        return $audit;
    }

    /**
     * Analyze N+1 query patterns
     */
    protected static function analyzeN1Queries(): array
    {
        $analysis = [];
        
        // Common N+1 patterns to check
        $patterns = [
            'projects_with_tasks' => [
                'model' => 'App\Models\Project',
                'relationship' => 'tasks',
                'query' => 'Project::with("tasks")->get()',
                'description' => 'Loading projects with their tasks',
            ],
            'users_with_projects' => [
                'model' => 'App\Models\User',
                'relationship' => 'projects',
                'query' => 'User::with("projects")->get()',
                'description' => 'Loading users with their projects',
            ],
            'documents_with_versions' => [
                'model' => 'App\Models\Document',
                'relationship' => 'versions',
                'query' => 'Document::with("versions")->get()',
                'description' => 'Loading documents with their versions',
            ],
            'tasks_with_assignments' => [
                'model' => 'App\Models\Task',
                'relationship' => 'assignments',
                'query' => 'Task::with("assignments")->get()',
                'description' => 'Loading tasks with their assignments',
            ],
            'projects_with_activities' => [
                'model' => 'App\Models\Project',
                'relationship' => 'activities',
                'query' => 'Project::with("activities")->get()',
                'description' => 'Loading projects with their activities',
            ],
        ];

        foreach ($patterns as $patternName => $pattern) {
            $analysis[$patternName] = self::analyzeN1Pattern($pattern);
        }

        return $analysis;
    }

    /**
     * Analyze a specific N+1 pattern
     */
    protected static function analyzeN1Pattern(array $pattern): array
    {
        try {
            $modelClass = $pattern['model'];
            
            if (!class_exists($modelClass)) {
                return [
                    'status' => 'error',
                    'message' => "Model {$modelClass} not found",
                ];
            }

            $model = new $modelClass;
            $tableName = $model->getTable();
            
            // Check if relationship exists
            $relationship = $pattern['relationship'];
            if (!method_exists($model, $relationship)) {
                return [
                    'status' => 'error',
                    'message' => "Relationship {$relationship} not found on {$modelClass}",
                ];
            }

            // Analyze relationship definition
            $relationshipAnalysis = self::analyzeRelationship($model, $relationship);
            
            // Check for proper eager loading
            $eagerLoadingAnalysis = self::analyzeEagerLoading($modelClass, $relationship);
            
            // Check for N+1 prevention measures
            $preventionAnalysis = self::analyzeN1Prevention($modelClass, $relationship);

            return [
                'status' => 'analyzed',
                'model' => $modelClass,
                'table' => $tableName,
                'relationship' => $relationship,
                'relationship_analysis' => $relationshipAnalysis,
                'eager_loading_analysis' => $eagerLoadingAnalysis,
                'prevention_analysis' => $preventionAnalysis,
                'recommendations' => self::generateN1Recommendations($relationshipAnalysis, $eagerLoadingAnalysis, $preventionAnalysis),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze relationship definition
     */
    protected static function analyzeRelationship(Model $model, string $relationship): array
    {
        try {
            $relation = $model->$relationship();
            $relationType = class_basename($relation);
            
            $analysis = [
                'type' => $relationType,
                'foreign_key' => null,
                'local_key' => null,
                'related_model' => null,
                'related_table' => null,
            ];

            // Get relationship details based on type
            switch ($relationType) {
                case 'HasMany':
                    $analysis['foreign_key'] = $relation->getForeignKeyName();
                    $analysis['local_key'] = $relation->getLocalKeyName();
                    $analysis['related_model'] = get_class($relation->getRelated());
                    $analysis['related_table'] = $relation->getRelated()->getTable();
                    break;
                case 'BelongsTo':
                    $analysis['foreign_key'] = $relation->getForeignKeyName();
                    $analysis['local_key'] = $relation->getOwnerKeyName();
                    $analysis['related_model'] = get_class($relation->getRelated());
                    $analysis['related_table'] = $relation->getRelated()->getTable();
                    break;
                case 'BelongsToMany':
                    $analysis['foreign_key'] = $relation->getForeignPivotKeyName();
                    $analysis['local_key'] = $relation->getLocalPivotKeyName();
                    $analysis['related_model'] = get_class($relation->getRelated());
                    $analysis['related_table'] = $relation->getRelated()->getTable();
                    break;
            }

            return $analysis;
        } catch (\Exception $e) {
            return [
                'type' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze eager loading patterns
     */
    protected static function analyzeEagerLoading(string $modelClass, string $relationship): array
    {
        $analysis = [
            'with_usage' => false,
            'with_count_usage' => false,
            'lazy_loading_detected' => false,
            'recommended_eager_loading' => [],
        ];

        // Check if model uses eager loading by default
        if (method_exists($modelClass, 'getDefaultEagerLoads')) {
            $defaultEagerLoads = $modelClass::getDefaultEagerLoads();
            $analysis['with_usage'] = in_array($relationship, $defaultEagerLoads);
        }

        // Check for common eager loading patterns
        $commonPatterns = [
            'with' => "{$modelClass}::with('{$relationship}')",
            'with_count' => "{$modelClass}::withCount('{$relationship}')",
            'with_where' => "{$modelClass}::with(['{$relationship}' => function(\$query) { \$query->where('status', 'active'); }])",
        ];

        $analysis['recommended_eager_loading'] = $commonPatterns;

        return $analysis;
    }

    /**
     * Analyze N+1 prevention measures
     */
    protected static function analyzeN1Prevention(string $modelClass, string $relationship): array
    {
        $analysis = [
            'global_scopes' => false,
            'query_scopes' => false,
            'accessors_cached' => false,
            'relationship_cached' => false,
        ];

        // Check for global scopes that might prevent N+1
        try {
            $model = new $modelClass;
            if (method_exists($model, 'getGlobalScopes')) {
                $globalScopes = $model->getGlobalScopes();
                $analysis['global_scopes'] = !empty($globalScopes);
            }
        } catch (\Exception $e) {
            $analysis['global_scopes'] = false;
        }

        // Check for query scopes
        $reflection = new \ReflectionClass($modelClass);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if (str_starts_with($method->getName(), 'scope')) {
                $analysis['query_scopes'] = true;
                break;
            }
        }

        return $analysis;
    }

    /**
     * Generate N+1 recommendations
     */
    protected static function generateN1Recommendations(array $relationshipAnalysis, array $eagerLoadingAnalysis, array $preventionAnalysis): array
    {
        $recommendations = [];

        // Eager loading recommendations
        if (!$eagerLoadingAnalysis['with_usage']) {
            $recommendations[] = [
                'type' => 'eager_loading',
                'priority' => 'high',
                'title' => 'Implement Eager Loading',
                'description' => 'Use with() to prevent N+1 queries',
                'example' => $eagerLoadingAnalysis['recommended_eager_loading']['with'] ?? 'N/A',
            ];
        }

        // Index recommendations
        if ($relationshipAnalysis['foreign_key']) {
            $recommendations[] = [
                'type' => 'indexing',
                'priority' => 'medium',
                'title' => 'Add Foreign Key Index',
                'description' => "Ensure {$relationshipAnalysis['foreign_key']} is indexed",
                'table' => $relationshipAnalysis['related_table'] ?? 'unknown',
                'column' => $relationshipAnalysis['foreign_key'],
            ];
        }

        // Caching recommendations
        if (!$preventionAnalysis['relationship_cached']) {
            $recommendations[] = [
                'type' => 'caching',
                'priority' => 'low',
                'title' => 'Consider Relationship Caching',
                'description' => 'Cache frequently accessed relationships',
                'implementation' => 'Use Laravel cache or Redis for relationship data',
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze indexing patterns
     */
    protected static function analyzeIndexing(): array
    {
        $analysis = [];
        
        $tables = [
            'projects' => ['tenant_id', 'status', 'created_at'],
            'tasks' => ['project_id', 'status', 'assignee_id', 'created_at'],
            'documents' => ['project_id', 'tenant_id', 'status', 'created_at'],
            'users' => ['tenant_id', 'email', 'created_at'],
            'project_activities' => ['project_id', 'user_id', 'entity_type', 'created_at'],
            'audit_logs' => ['user_id', 'tenant_id', 'entity_type', 'created_at'],
        ];

        foreach ($tables as $table => $importantColumns) {
            $analysis[$table] = self::analyzeTableIndexing($table, $importantColumns);
        }

        return $analysis;
    }

    /**
     * Analyze table indexing
     */
    protected static function analyzeTableIndexing(string $table, array $importantColumns): array
    {
        try {
            $existingIndexes = self::getTableIndexes($table);
            $missingIndexes = [];
            $recommendedIndexes = [];

            foreach ($importantColumns as $column) {
                if (!self::hasIndexOnColumn($existingIndexes, $column)) {
                    $missingIndexes[] = $column;
                }
            }

            // Check for composite indexes
            $compositeIndexes = self::getCompositeIndexes($existingIndexes);
            
            // Generate recommendations
            foreach ($importantColumns as $column) {
                if (in_array($column, $missingIndexes)) {
                    $recommendedIndexes[] = [
                        'type' => 'single',
                        'column' => $column,
                        'priority' => self::getColumnPriority($column),
                    ];
                }
            }

            // Check for common composite patterns
            $commonPatterns = [
                ['tenant_id', 'status'],
                ['project_id', 'status'],
                ['user_id', 'created_at'],
                ['entity_type', 'entity_id'],
            ];

            foreach ($commonPatterns as $pattern) {
                if (self::hasAllColumns($table, $pattern) && !self::hasCompositeIndex($existingIndexes, $pattern)) {
                    $recommendedIndexes[] = [
                        'type' => 'composite',
                        'columns' => $pattern,
                        'priority' => 'medium',
                    ];
                }
            }

            return [
                'table' => $table,
                'existing_indexes' => count($existingIndexes),
                'missing_indexes' => $missingIndexes,
                'composite_indexes' => $compositeIndexes,
                'recommended_indexes' => $recommendedIndexes,
                'index_coverage' => round((count($importantColumns) - count($missingIndexes)) / count($importantColumns) * 100, 2),
            ];
        } catch (\Exception $e) {
            return [
                'table' => $table,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze query performance
     */
    protected static function analyzeQueryPerformance(): array
    {
        $analysis = [
            'slow_queries' => self::getSlowQueries(),
            'query_patterns' => self::analyzeQueryPatterns(),
            'performance_metrics' => self::getPerformanceMetrics(),
        ];

        return $analysis;
    }

    /**
     * Get slow queries from database
     */
    protected static function getSlowQueries(): array
    {
        try {
            // This would typically come from query log or slow query log
            // For now, we'll return a placeholder structure
            return [
                'total_slow_queries' => 0,
                'average_execution_time' => 0,
                'slowest_query' => null,
                'common_slow_patterns' => [],
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze query patterns
     */
    protected static function analyzeQueryPatterns(): array
    {
        return [
            'select_patterns' => [
                'SELECT *' => 'Avoid SELECT * in production',
                'JOIN without WHERE' => 'Ensure JOINs have proper WHERE clauses',
                'ORDER BY without LIMIT' => 'Use LIMIT with ORDER BY for large datasets',
            ],
            'n1_patterns' => [
                'Loop with queries' => 'Use eager loading instead of loops',
                'Missing with()' => 'Add with() for relationships',
                'Lazy loading' => 'Avoid lazy loading in loops',
            ],
        ];
    }

    /**
     * Get performance metrics
     */
    protected static function getPerformanceMetrics(): array
    {
        try {
            $metrics = [];
            
            $tables = ['projects', 'tasks', 'documents', 'users', 'project_activities', 'audit_logs'];
            
            foreach ($tables as $table) {
                $count = DB::table($table)->count();
                $metrics[$table] = [
                    'row_count' => $count,
                    'size_category' => self::getSizeCategory($count),
                    'performance_impact' => self::getPerformanceImpact($count),
                ];
            }

            return $metrics;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate overall recommendations
     */
    protected static function generateRecommendations(): array
    {
        return [
            [
                'priority' => 'high',
                'category' => 'n1_prevention',
                'title' => 'Implement Eager Loading',
                'description' => 'Use with() to prevent N+1 queries in all relationship loading',
                'impact' => 'Significant performance improvement',
            ],
            [
                'priority' => 'high',
                'category' => 'indexing',
                'title' => 'Add Missing Indexes',
                'description' => 'Add indexes on foreign keys and frequently queried columns',
                'impact' => 'Faster query execution',
            ],
            [
                'priority' => 'medium',
                'category' => 'query_optimization',
                'title' => 'Optimize Query Patterns',
                'description' => 'Avoid SELECT * and use proper WHERE clauses',
                'impact' => 'Reduced data transfer and faster queries',
            ],
            [
                'priority' => 'medium',
                'category' => 'caching',
                'title' => 'Implement Query Caching',
                'description' => 'Cache frequently accessed data',
                'impact' => 'Reduced database load',
            ],
            [
                'priority' => 'low',
                'category' => 'monitoring',
                'title' => 'Add Query Monitoring',
                'description' => 'Monitor slow queries and N+1 patterns',
                'impact' => 'Proactive performance management',
            ],
        ];
    }

    /**
     * Generate optimization plan
     */
    protected static function generateOptimizationPlan(): array
    {
        return [
            'phase_1' => [
                'title' => 'Critical N+1 Fixes',
                'duration' => '1-2 days',
                'tasks' => [
                    'Add with() to all relationship loading',
                    'Add indexes on foreign keys',
                    'Fix SELECT * queries',
                ],
            ],
            'phase_2' => [
                'title' => 'Index Optimization',
                'duration' => '2-3 days',
                'tasks' => [
                    'Add composite indexes for common queries',
                    'Optimize existing indexes',
                    'Remove unused indexes',
                ],
            ],
            'phase_3' => [
                'title' => 'Query Optimization',
                'duration' => '3-5 days',
                'tasks' => [
                    'Optimize complex queries',
                    'Implement query caching',
                    'Add query monitoring',
                ],
            ],
        ];
    }

    /**
     * Helper methods
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

    protected static function hasIndexOnColumn(array $indexes, string $column): bool
    {
        foreach ($indexes as $index) {
            if ($index['column'] === $column) {
                return true;
            }
        }
        return false;
    }

    protected static function getCompositeIndexes(array $indexes): array
    {
        $composite = [];
        foreach ($indexes as $index) {
            if (str_contains($index['name'], '_') && $index['name'] !== 'PRIMARY') {
                $composite[] = $index['name'];
            }
        }
        return array_unique($composite);
    }

    protected static function hasCompositeIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            if (in_array($index['column'], $columns)) {
                return true;
            }
        }
        return false;
    }

    protected static function hasAllColumns(string $table, array $columns): bool
    {
        $tableColumns = Schema::getColumnListing($table);
        foreach ($columns as $column) {
            if (!in_array($column, $tableColumns)) {
                return false;
            }
        }
        return true;
    }

    protected static function getColumnPriority(string $column): string
    {
        $highPriority = ['tenant_id', 'project_id', 'user_id', 'status'];
        $mediumPriority = ['created_at', 'updated_at', 'email'];
        
        if (in_array($column, $highPriority)) return 'high';
        if (in_array($column, $mediumPriority)) return 'medium';
        return 'low';
    }

    protected static function getSizeCategory(int $count): string
    {
        if ($count < 1000) return 'small';
        if ($count < 10000) return 'medium';
        if ($count < 100000) return 'large';
        return 'very_large';
    }

    protected static function getPerformanceImpact(int $count): string
    {
        if ($count < 1000) return 'minimal';
        if ($count < 10000) return 'low';
        if ($count < 100000) return 'medium';
        return 'high';
    }
}
