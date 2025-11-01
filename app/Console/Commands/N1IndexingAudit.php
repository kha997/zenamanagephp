<?php

namespace App\Console\Commands;

use App\Services\N1IndexingAuditService;
use App\Services\StructuredLoggingService;
use Illuminate\Console\Command;

class N1IndexingAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:n1-indexing {--detailed : Show detailed information} {--log : Log audit results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit N+1 queries and indexing patterns for optimization opportunities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting N+1 and Indexing audit...');
        
        try {
            $startTime = microtime(true);
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            $duration = microtime(true) - $startTime;
            
            // Display N+1 analysis
            $this->displayN1Analysis($audit['n1_analysis']);
            
            // Display indexing analysis
            $this->displayIndexingAnalysis($audit['indexing_analysis']);
            
            // Display query performance
            $this->displayQueryPerformance($audit['query_performance']);
            
            // Display recommendations
            $this->displayRecommendations($audit['recommendations']);
            
            // Display optimization plan if detailed
            if ($this->option('detailed')) {
                $this->displayOptimizationPlan($audit['optimization_plan']);
            }
            
            // Log results if requested
            if ($this->option('log')) {
                StructuredLoggingService::logEvent('n1_indexing_audit_cli', [
                    'duration_ms' => round($duration * 1000, 2),
                    'n1_patterns_analyzed' => count($audit['n1_analysis']),
                    'tables_analyzed' => count($audit['indexing_analysis']),
                    'recommendations_count' => count($audit['recommendations']),
                ]);
                $this->info('N+1 and indexing audit results logged');
            }
            
            $this->line('');
            $this->info("N+1 and Indexing audit completed in " . round($duration * 1000, 2) . "ms");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('N+1 and indexing audit command failed', $e);
            
            $this->error('N+1 and indexing audit failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display N+1 analysis
     */
    protected function displayN1Analysis(array $n1Analysis): void
    {
        $this->line('');
        $this->line('<fg=cyan>N+1 Query Analysis:</>');
        $this->line(str_repeat('=', 50));
        
        foreach ($n1Analysis as $patternName => $analysis) {
            if ($analysis['status'] === 'error') {
                $this->line("<fg=red>✗ {$patternName}</>");
                $this->line("  Error: {$analysis['message']}");
                continue;
            }
            
            $this->line("<fg=green>✓ {$patternName}</>");
            $this->line("  Model: {$analysis['model']}");
            $this->line("  Relationship: {$analysis['relationship']}");
            
            if (!empty($analysis['recommendations'])) {
                $this->line("  Recommendations:");
                foreach ($analysis['recommendations'] as $recommendation) {
                    $priorityColor = match($recommendation['priority']) {
                        'high' => 'red',
                        'medium' => 'yellow',
                        'low' => 'blue',
                        default => 'white'
                    };
                    $this->line("    <fg={$priorityColor}>[{$recommendation['priority']}]</> {$recommendation['title']}");
                }
            }
            $this->line('');
        }
    }

    /**
     * Display indexing analysis
     */
    protected function displayIndexingAnalysis(array $indexingAnalysis): void
    {
        $this->line('');
        $this->line('<fg=cyan>Indexing Analysis:</>');
        $this->line(str_repeat('=', 50));
        
        foreach ($indexingAnalysis as $table => $analysis) {
            if (isset($analysis['error'])) {
                $this->line("<fg=red>✗ {$table}</>");
                $this->line("  Error: {$analysis['error']}");
                continue;
            }
            
            $this->line("<fg=green>✓ {$table}</>");
            $this->line("  Existing Indexes: {$analysis['existing_indexes']}");
            $this->line("  Index Coverage: {$analysis['index_coverage']}%");
            
            if (!empty($analysis['missing_indexes'])) {
                $this->line("  Missing Indexes: " . implode(', ', $analysis['missing_indexes']));
            }
            
            if (!empty($analysis['recommended_indexes'])) {
                $this->line("  Recommended Indexes:");
                foreach ($analysis['recommended_indexes'] as $index) {
                    $priorityColor = match($index['priority']) {
                        'high' => 'red',
                        'medium' => 'yellow',
                        'low' => 'blue',
                        default => 'white'
                    };
                    
                    if ($index['type'] === 'composite') {
                        $this->line("    <fg={$priorityColor}>[{$index['priority']}]</> Composite: " . implode(', ', $index['columns']));
                    } else {
                        $this->line("    <fg={$priorityColor}>[{$index['priority']}]</> Single: {$index['column']}");
                    }
                }
            }
            $this->line('');
        }
    }

    /**
     * Display query performance
     */
    protected function displayQueryPerformance(array $queryPerformance): void
    {
        $this->line('');
        $this->line('<fg=cyan>Query Performance Analysis:</>');
        $this->line(str_repeat('=', 50));
        
        // Display performance metrics
        if (isset($queryPerformance['performance_metrics'])) {
            $metrics = $queryPerformance['performance_metrics'];
            $this->line('Table Performance Metrics:');
            
            foreach ($metrics as $table => $metric) {
                if (isset($metric['error'])) {
                    $this->line("  <fg=red>{$table}: Error - {$metric['error']}</>");
                    continue;
                }
                
                $sizeColor = match($metric['size_category']) {
                    'small' => 'green',
                    'medium' => 'yellow',
                    'large' => 'red',
                    'very_large' => 'red',
                    default => 'white'
                };
                
                $impactColor = match($metric['performance_impact']) {
                    'minimal' => 'green',
                    'low' => 'yellow',
                    'medium' => 'red',
                    'high' => 'red',
                    default => 'white'
                };
                
                $this->line("  {$table}:");
                $this->line("    Rows: {$metric['row_count']}");
                $this->line("    Size: <fg={$sizeColor}>{$metric['size_category']}</>");
                $this->line("    Performance Impact: <fg={$impactColor}>{$metric['performance_impact']}</>");
            }
        }
        
        // Display query patterns
        if (isset($queryPerformance['query_patterns'])) {
            $this->line('');
            $this->line('Query Pattern Analysis:');
            
            foreach ($queryPerformance['query_patterns'] as $category => $patterns) {
                $this->line("  {$category}:");
                foreach ($patterns as $pattern => $description) {
                    $this->line("    • {$pattern}: {$description}");
                }
            }
        }
        
        $this->line('');
    }

    /**
     * Display recommendations
     */
    protected function displayRecommendations(array $recommendations): void
    {
        $this->line('');
        $this->line('<fg=magenta>Overall Recommendations:</>');
        $this->line(str_repeat('=', 50));
        
        foreach ($recommendations as $recommendation) {
            $priorityColor = match($recommendation['priority']) {
                'high' => 'red',
                'medium' => 'yellow',
                'low' => 'blue',
                default => 'white'
            };
            
            $this->line('');
            $this->line("<fg={$priorityColor}>[{$recommendation['priority']}]</> {$recommendation['title']}");
            $this->line("Category: {$recommendation['category']}");
            $this->line("Description: {$recommendation['description']}");
            $this->line("Impact: {$recommendation['impact']}");
        }
        
        $this->line('');
    }

    /**
     * Display optimization plan
     */
    protected function displayOptimizationPlan(array $optimizationPlan): void
    {
        $this->line('');
        $this->line('<fg=green>Optimization Plan:</>');
        $this->line(str_repeat('=', 50));
        
        foreach ($optimizationPlan as $phaseKey => $phase) {
            $this->line('');
            $this->line("<fg=cyan>{$phase['title']}</>");
            $this->line("Duration: {$phase['duration']}");
            $this->line("Tasks:");
            
            foreach ($phase['tasks'] as $task) {
                $this->line("  • {$task}");
            }
        }
        
        $this->line('');
    }
}
