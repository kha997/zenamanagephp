<?php
/**
 * Comprehensive Test Runner for ZenaManage
 * 
 * This script runs all tests and provides a complete testing report
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class ComprehensiveTestRunner
{
    private $testResults = [];
    private $startTime;
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;

    public function __construct()
    {
        echo "🧪 ZENA MANAGE - COMPREHENSIVE TEST SUITE\n";
        echo "==========================================\n\n";
        $this->startTime = microtime(true);
    }

    public function runAllTests()
    {
        try {
            $this->runUnitTests();
            $this->runFeatureTests();
            $this->runIntegrationTests();
            $this->runSecurityTests();
            $this->runPerformanceTests();
            $this->runMustHaveTests();
            $this->runE2ETests();
            $this->displayFinalReport();
            
        } catch (Exception $e) {
            echo "❌ Test execution failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function runUnitTests()
    {
        echo "🔬 Running Unit Tests...\n";
        echo "------------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --testsuite=Unit --stop-on-failure');
            $this->testResults['unit'] = $this->parseTestOutput($output);
            $this->displayTestResults('Unit Tests', $this->testResults['unit']);
            
        } catch (Exception $e) {
            $this->testResults['unit'] = ['error' => $e->getMessage()];
            echo "❌ Unit Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runFeatureTests()
    {
        echo "🎯 Running Feature Tests...\n";
        echo "----------------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --testsuite=Feature --stop-on-failure');
            $this->testResults['feature'] = $this->parseTestOutput($output);
            $this->displayTestResults('Feature Tests', $this->testResults['feature']);
            
        } catch (Exception $e) {
            $this->testResults['feature'] = ['error' => $e->getMessage()];
            echo "❌ Feature Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runIntegrationTests()
    {
        echo "🔗 Running Integration Tests...\n";
        echo "------------------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --testsuite=Integration --stop-on-failure');
            $this->testResults['integration'] = $this->parseTestOutput($output);
            $this->displayTestResults('Integration Tests', $this->testResults['integration']);
            
        } catch (Exception $e) {
            $this->testResults['integration'] = ['error' => $e->getMessage()];
            echo "❌ Integration Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runSecurityTests()
    {
        echo "🔒 Running Security Tests...\n";
        echo "----------------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --filter=SecurityTest --stop-on-failure');
            $this->testResults['security'] = $this->parseTestOutput($output);
            $this->displayTestResults('Security Tests', $this->testResults['security']);
            
        } catch (Exception $e) {
            $this->testResults['security'] = ['error' => $e->getMessage()];
            echo "❌ Security Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runPerformanceTests()
    {
        echo "⚡ Running Performance Tests...\n";
        echo "------------------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --filter=PerformanceTest --stop-on-failure');
            $this->testResults['performance'] = $this->parseTestOutput($output);
            $this->displayTestResults('Performance Tests', $this->testResults['performance']);
            
        } catch (Exception $e) {
            $this->testResults['performance'] = ['error' => $e->getMessage()];
            echo "❌ Performance Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runMustHaveTests()
    {
        echo "🎯 Running Must Have Tests...\n";
        echo "-----------------------------\n";
        
        try {
            // Run the must have tests script
            $mustHaveTests = [
                'test_rbac_roles.php',
                'test_rfi_workflow.php',
                'test_change_request.php',
                'test_task_dependencies.php',
                'test_multi_tenant.php',
                'test_secure_upload.php'
            ];
            
            $totalMustHaveTests = 0;
            $passedMustHaveTests = 0;
            
            foreach ($mustHaveTests as $testFile) {
                if (file_exists($testFile)) {
                    echo "Running $testFile...\n";
                    $output = shell_exec("php $testFile 2>&1");
                    
                    // Parse output for pass/fail
                    if (strpos($output, 'PASS') !== false) {
                        $passedMustHaveTests++;
                    }
                    $totalMustHaveTests++;
                }
            }
            
            $this->testResults['must_have'] = [
                'total' => $totalMustHaveTests,
                'passed' => $passedMustHaveTests,
                'failed' => $totalMustHaveTests - $passedMustHaveTests,
                'pass_rate' => $totalMustHaveTests > 0 ? round(($passedMustHaveTests / $totalMustHaveTests) * 100, 2) : 0
            ];
            
            $this->displayTestResults('Must Have Tests', $this->testResults['must_have']);
            
        } catch (Exception $e) {
            $this->testResults['must_have'] = ['error' => $e->getMessage()];
            echo "❌ Must Have Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runE2ETests()
    {
        echo "🌐 Running E2E Tests...\n";
        echo "-----------------------\n";
        
        try {
            $output = $this->runArtisanCommand('test --filter=E2E --stop-on-failure');
            $this->testResults['e2e'] = $this->parseTestOutput($output);
            $this->displayTestResults('E2E Tests', $this->testResults['e2e']);
            
        } catch (Exception $e) {
            $this->testResults['e2e'] = ['error' => $e->getMessage()];
            echo "❌ E2E Tests Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function runArtisanCommand($command)
    {
        $exitCode = 0;
        $output = '';
        
        // Capture output
        ob_start();
        $exitCode = Artisan::call($command);
        $output = ob_get_clean();
        
        return $output;
    }

    private function parseTestOutput($output)
    {
        $result = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'pass_rate' => 0
        ];
        
        // Parse PHPUnit output
        if (preg_match('/(\d+) tests?/', $output, $matches)) {
            $result['total'] = (int)$matches[1];
        }
        
        if (preg_match('/(\d+) assertions?/', $output, $matches)) {
            $result['passed'] = (int)$matches[1];
        }
        
        if (preg_match('/(\d+) failures?/', $output, $matches)) {
            $result['failed'] = (int)$matches[1];
        }
        
        if (preg_match('/(\d+) skipped/', $output, $matches)) {
            $result['skipped'] = (int)$matches[1];
        }
        
        if ($result['total'] > 0) {
            $result['pass_rate'] = round((($result['total'] - $result['failed'] - $result['skipped']) / $result['total']) * 100, 2);
        }
        
        return $result;
    }

    private function displayTestResults($testType, $results)
    {
        if (isset($results['error'])) {
            echo "❌ $testType: ERROR - " . $results['error'] . "\n";
            return;
        }
        
        $status = $results['pass_rate'] >= 90 ? "✅" : ($results['pass_rate'] >= 70 ? "⚠️" : "❌");
        
        echo "$status $testType:\n";
        echo "  - Total: " . $results['total'] . "\n";
        echo "  - Passed: " . $results['passed'] . "\n";
        echo "  - Failed: " . $results['failed'] . "\n";
        echo "  - Skipped: " . $results['skipped'] . "\n";
        echo "  - Pass Rate: " . $results['pass_rate'] . "%\n";
        
        $this->totalTests += $results['total'];
        $this->passedTests += $results['passed'];
        $this->failedTests += $results['failed'];
    }

    private function displayFinalReport()
    {
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->startTime, 2);
        $overallPassRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0;
        
        echo "📊 COMPREHENSIVE TEST REPORT\n";
        echo "============================\n\n";
        
        echo "⏱️  Execution Time: {$executionTime} seconds\n";
        echo "📈 Overall Statistics:\n";
        echo "  - Total Tests: {$this->totalTests}\n";
        echo "  - Passed: {$this->passedTests}\n";
        echo "  - Failed: {$this->failedTests}\n";
        echo "  - Overall Pass Rate: {$overallPassRate}%\n\n";
        
        echo "📋 Test Suite Results:\n";
        echo "----------------------\n";
        
        foreach ($this->testResults as $suite => $results) {
            if (isset($results['error'])) {
                echo "❌ " . ucfirst($suite) . ": ERROR\n";
            } else {
                $status = $results['pass_rate'] >= 90 ? "✅" : ($results['pass_rate'] >= 70 ? "⚠️" : "❌");
                echo "$status " . ucfirst($suite) . ": {$results['pass_rate']}% ({$results['passed']}/{$results['total']})\n";
            }
        }
        
        echo "\n🎯 Quality Assessment:\n";
        echo "----------------------\n";
        
        if ($overallPassRate >= 95) {
            echo "🏆 EXCELLENT: System is production ready!\n";
        } elseif ($overallPassRate >= 90) {
            echo "✅ GOOD: System is ready with minor issues\n";
        } elseif ($overallPassRate >= 80) {
            echo "⚠️  FAIR: System needs some improvements\n";
        } else {
            echo "❌ POOR: System needs significant work\n";
        }
        
        echo "\n📝 Recommendations:\n";
        echo "-------------------\n";
        
        if ($overallPassRate < 90) {
            echo "• Fix failing tests before production deployment\n";
            echo "• Increase test coverage for critical components\n";
            echo "• Review and improve error handling\n";
        }
        
        if (isset($this->testResults['security']['pass_rate']) && $this->testResults['security']['pass_rate'] < 100) {
            echo "• Address security vulnerabilities immediately\n";
        }
        
        if (isset($this->testResults['performance']['pass_rate']) && $this->testResults['performance']['pass_rate'] < 90) {
            echo "• Optimize performance bottlenecks\n";
        }
        
        echo "\n🎉 Test execution completed!\n";
    }
}

// Run comprehensive tests
$testRunner = new ComprehensiveTestRunner();
$testRunner->runAllTests();
