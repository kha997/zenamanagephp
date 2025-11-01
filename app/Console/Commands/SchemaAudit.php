<?php

namespace App\Console\Commands;

use App\Services\SchemaAuditService;
use App\Services\StructuredLoggingService;
use Illuminate\Console\Command;

class SchemaAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:audit {--table= : Audit specific table} {--detailed : Show detailed information} {--log : Log audit results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit documents and history table schemas for optimization opportunities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting schema audit...');
        
        try {
            $startTime = microtime(true);
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            $duration = microtime(true) - $startTime;
            
            $table = $this->option('table');
            
            if ($table) {
                $this->auditSpecificTable($audit, $table);
            } else {
                $this->auditAllTables($audit);
            }
            
            // Display recommendations
            $this->displayRecommendations($audit['recommendations']);
            
            // Display performance analysis
            $this->displayPerformanceAnalysis($audit['performance_analysis']);
            
            // Log results if requested
            if ($this->option('log')) {
                StructuredLoggingService::logEvent('schema_audit_cli', [
                    'duration_ms' => round($duration * 1000, 2),
                    'tables_audited' => 4,
                    'issues_found' => $this->countTotalIssues($audit),
                    'recommendations_count' => count($audit['recommendations']),
                ]);
                $this->info('Schema audit results logged');
            }
            
            $this->line('');
            $this->info("Schema audit completed in " . round($duration * 1000, 2) . "ms");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Schema audit command failed', $e);
            
            $this->error('Schema audit failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Audit all tables
     */
    protected function auditAllTables(array $audit): void
    {
        $tables = [
            'documents' => $audit['documents_table'],
            'document_versions' => $audit['document_versions_table'],
            'project_activities' => $audit['project_activities_table'],
            'audit_logs' => $audit['audit_logs_table'],
        ];
        
        foreach ($tables as $tableName => $tableAudit) {
            $this->displayTableAudit($tableName, $tableAudit);
        }
    }

    /**
     * Audit specific table
     */
    protected function auditSpecificTable(array $audit, string $table): void
    {
        $tableMap = [
            'documents' => 'documents_table',
            'document_versions' => 'document_versions_table',
            'project_activities' => 'project_activities_table',
            'audit_logs' => 'audit_logs_table',
        ];
        
        if (!isset($tableMap[$table])) {
            $this->error("Unknown table: {$table}");
            $this->line("Available tables: " . implode(', ', array_keys($tableMap)));
            return;
        }
        
        $tableKey = $tableMap[$table];
        $this->displayTableAudit($table, $audit[$tableKey]);
    }

    /**
     * Display table audit results
     */
    protected function displayTableAudit(string $tableName, array $tableAudit): void
    {
        $this->line('');
        $this->line("Table: <fg=cyan>{$tableName}</>");
        $this->line(str_repeat('=', 50));
        
        $this->line("Columns: {$tableAudit['column_count']}");
        $this->line("Indexes: " . count($tableAudit['indexes']));
        $this->line("Foreign Keys: " . count($tableAudit['foreign_keys']));
        
        // Display issues
        if (!empty($tableAudit['issues'])) {
            $this->line('');
            $this->line('<fg=yellow>Issues Found:</>');
            foreach ($tableAudit['issues'] as $issue) {
                $severityColor = match($issue['severity']) {
                    'high' => 'red',
                    'medium' => 'yellow',
                    'low' => 'blue',
                    default => 'white'
                };
                
                $this->line("  <fg={$severityColor}>[{$issue['severity']}]</> {$issue['description']}");
                $this->line("    Impact: {$issue['impact']}");
            }
        } else {
            $this->line('');
            $this->line('<fg=green>No issues found</>');
        }
        
        // Display optimizations if detailed
        if ($this->option('detailed') && !empty($tableAudit['optimizations'])) {
            $this->line('');
            $this->line('<fg=cyan>Suggested Optimizations:</>');
            foreach ($tableAudit['optimizations'] as $optimization) {
                $this->line("  • {$optimization['description']}");
                if (isset($optimization['columns'])) {
                    $this->line("    Columns: " . implode(', ', $optimization['columns']));
                }
            }
        }
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
            if (isset($recommendation['tables'])) {
                $this->line("Affected Tables: " . implode(', ', $recommendation['tables']));
            }
        }
    }

    /**
     * Display performance analysis
     */
    protected function displayPerformanceAnalysis(array $performance): void
    {
        $this->line('');
        $this->line('<fg=green>Performance Analysis:</>');
        $this->line(str_repeat('=', 50));
        
        foreach ($performance as $table => $analysis) {
            if (isset($analysis['error'])) {
                $this->line("<fg=red>{$table}: Error - {$analysis['error']}</>");
                continue;
            }
            
            $sizeColor = match($analysis['size_category']) {
                'small' => 'green',
                'medium' => 'yellow',
                'large' => 'red',
                'very_large' => 'red',
                default => 'white'
            };
            
            $impactColor = match($analysis['performance_impact']) {
                'minimal' => 'green',
                'low' => 'yellow',
                'medium' => 'red',
                'high' => 'red',
                default => 'white'
            };
            
            $this->line("{$table}:");
            $this->line("  Rows: {$analysis['row_count']}");
            $this->line("  Size: <fg={$sizeColor}>{$analysis['size_category']}</>");
            $this->line("  Performance Impact: <fg={$impactColor}>{$analysis['performance_impact']}</>");
        }
    }

    /**
     * Count total issues across all tables
     */
    protected function countTotalIssues(array $audit): int
    {
        $total = 0;
        $tables = ['documents_table', 'document_versions_table', 'project_activities_table', 'audit_logs_table'];
        
        foreach ($tables as $table) {
            if (isset($audit[$table]['issues'])) {
                $total += count($audit[$table]['issues']);
            }
        }
        
        return $total;
    }
}
