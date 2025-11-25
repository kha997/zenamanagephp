#!/usr/bin/env php
<?php

/**
 * Query Plan Validation Script
 * 
 * Validates that top API endpoints use proper indexes for tenant isolation and performance.
 * Generates EXPLAIN plans and checks for index usage.
 * 
 * Usage: php scripts/validate_query_plans.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryPlanValidator
{
    private array $results = [];
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = storage_path('app/query-plans');
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Validate query plans for top endpoints
     */
    public function validate(): void
    {
        echo "Validating query plans for top endpoints...\n\n";

        // Top endpoints to validate
        $endpoints = [
            'projects_list' => [
                'query' => 'SELECT * FROM projects WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20',
                'params' => ['tenant123'],
                'expected_index' => 'idx_projects_tenant_created',
            ],
            'projects_detail' => [
                'query' => 'SELECT * FROM projects WHERE tenant_id = ? AND id = ?',
                'params' => ['tenant123', 'project456'],
                'expected_index' => 'idx_projects_tenant_id',
            ],
            'tasks_list' => [
                'query' => 'SELECT * FROM tasks WHERE tenant_id = ? AND project_id = ? ORDER BY created_at DESC LIMIT 50',
                'params' => ['tenant123', 'project456'],
                'expected_index' => 'idx_tasks_tenant_created',
            ],
            'tasks_detail' => [
                'query' => 'SELECT * FROM tasks WHERE tenant_id = ? AND id = ?',
                'params' => ['tenant123', 'task789'],
                'expected_index' => 'idx_tasks_tenant_id',
            ],
            'documents_list' => [
                'query' => 'SELECT * FROM documents WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20',
                'params' => ['tenant123'],
                'expected_index' => 'idx_documents_tenant_created',
            ],
        ];

        foreach ($endpoints as $endpointName => $config) {
            $this->validateEndpoint($endpointName, $config);
        }

        $this->generateReport();
    }

    /**
     * Validate a single endpoint
     */
    private function validateEndpoint(string $endpointName, array $config): void
    {
        echo "Validating: {$endpointName}...\n";

        try {
            // Get EXPLAIN plan
            $explainQuery = "EXPLAIN " . $config['query'];
            $plan = DB::select($explainQuery, $config['params']);

            // Check if expected index is used
            $usesIndex = false;
            $indexUsed = null;
            $rowsExamined = 0;

            foreach ($plan as $row) {
                $rowArray = (array) $row;
                $key = $rowArray['key'] ?? null;
                $rows = $rowArray['rows'] ?? 0;

                if ($key && str_contains($key, $config['expected_index'])) {
                    $usesIndex = true;
                    $indexUsed = $key;
                }

                $rowsExamined += (int) $rows;
            }

            // Check for full table scan
            $hasFullScan = false;
            foreach ($plan as $row) {
                $rowArray = (array) $row;
                $type = $rowArray['type'] ?? '';
                if ($type === 'ALL') {
                    $hasFullScan = true;
                }
            }

            $result = [
                'endpoint' => $endpointName,
                'query' => $config['query'],
                'uses_index' => $usesIndex,
                'index_used' => $indexUsed,
                'expected_index' => $config['expected_index'],
                'rows_examined' => $rowsExamined,
                'has_full_scan' => $hasFullScan,
                'plan' => $plan,
                'status' => $usesIndex && !$hasFullScan ? 'PASS' : 'FAIL',
            ];

            $this->results[] = $result;

            // Save individual plan
            $this->savePlan($endpointName, $plan);

            if ($result['status'] === 'PASS') {
                echo "  ✓ PASS - Uses index: {$indexUsed}\n";
            } else {
                echo "  ✗ FAIL - ";
                if (!$usesIndex) {
                    echo "Expected index not used\n";
                }
                if ($hasFullScan) {
                    echo "Full table scan detected\n";
                }
            }

        } catch (\Exception $e) {
            echo "  ✗ ERROR: {$e->getMessage()}\n";
            $this->results[] = [
                'endpoint' => $endpointName,
                'status' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Save EXPLAIN plan to file
     */
    private function savePlan(string $endpointName, array $plan): void
    {
        $filename = $this->outputDir . '/' . $endpointName . '_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($filename, json_encode($plan, JSON_PRETTY_PRINT));
    }

    /**
     * Generate validation report
     */
    private function generateReport(): void
    {
        echo "\n=== Validation Report ===\n\n";

        $passed = 0;
        $failed = 0;
        $errors = 0;

        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed++;
            } elseif ($result['status'] === 'FAIL') {
                $failed++;
            } else {
                $errors++;
            }
        }

        echo "Total endpoints: " . count($this->results) . "\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Errors: {$errors}\n\n";

        if ($failed > 0 || $errors > 0) {
            echo "Failed endpoints:\n";
            foreach ($this->results as $result) {
                if ($result['status'] !== 'PASS') {
                    echo "  - {$result['endpoint']}: {$result['status']}\n";
                    if (isset($result['error'])) {
                        echo "    Error: {$result['error']}\n";
                    }
                }
            }
        }

        // Save report to file
        $reportFile = $this->outputDir . '/validation_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode([
            'timestamp' => now()->toISOString(),
            'summary' => [
                'total' => count($this->results),
                'passed' => $passed,
                'failed' => $failed,
                'errors' => $errors,
            ],
            'results' => $this->results,
        ], JSON_PRETTY_PRINT));

        echo "\nReport saved to: {$reportFile}\n";

        // Exit with error code if any failures
        if ($failed > 0 || $errors > 0) {
            exit(1);
        }
    }
}

// Run validator
$validator = new QueryPlanValidator();
$validator->validate();

