<?php
/**
 * Script chạy tất cả các test Must Have
 * RBAC, RFI, Change Request, Task Dependencies, Multi-tenant, Secure Upload
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class MustHaveTestRunner
{
    private $testResults = [];
    private $startTime;
    private $endTime;

    public function __construct()
    {
        echo "🚀 ZENA MANAGE - MUST HAVE FEATURES TEST SUITE\n";
        echo "=============================================\n\n";
        echo "Kiểm tra 6 tính năng Must Have:\n";
        echo "1. 🔐 RBAC Roles\n";
        echo "2. 📝 RFI Workflow\n";
        echo "3. 🔄 Change Request\n";
        echo "4. 🔗 Task Dependencies\n";
        echo "5. 🏢 Multi-tenant\n";
        echo "6. 🔒 Secure Upload\n\n";
        
        $this->startTime = microtime(true);
    }

    public function runAllTests()
    {
        try {
            echo "⏰ Bắt đầu test lúc: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Test 1: RBAC Roles
            $this->runTest('RBAC Roles', 'test_rbac_roles.php');
            
            // Test 2: RFI Workflow
            $this->runTest('RFI Workflow', 'test_rfi_workflow.php');
            
            // Test 3: Change Request
            $this->runTest('Change Request', 'test_change_request.php');
            
            // Test 4: Task Dependencies
            $this->runTest('Task Dependencies', 'test_task_dependencies.php');
            
            // Test 5: Multi-tenant
            $this->runTest('Multi-tenant', 'test_multi_tenant.php');
            
            // Test 6: Secure Upload
            $this->runTest('Secure Upload', 'test_secure_upload.php');
            
            $this->endTime = microtime(true);
            $this->displayFinalResults();
            
        } catch (Exception $e) {
            echo "❌ Lỗi trong quá trình test: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function runTest($testName, $testFile)
    {
        echo "🔄 Đang chạy test: {$testName}\n";
        echo str_repeat('-', 50) . "\n";
        
        $startTime = microtime(true);
        
        try {
            // Capture output
            ob_start();
            
            // Include và chạy test file
            $testPath = __DIR__ . '/' . $testFile;
            if (file_exists($testPath)) {
                include $testPath;
                $output = ob_get_contents();
            } else {
                $output = "❌ Test file không tồn tại: {$testFile}\n";
            }
            
            ob_end_clean();
            
            // Parse kết quả từ output
            $result = $this->parseTestResult($output);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->testResults[$testName] = [
                'status' => $result['status'],
                'pass_rate' => $result['pass_rate'],
                'total_tests' => $result['total_tests'],
                'passed_tests' => $result['passed_tests'],
                'failed_tests' => $result['failed_tests'],
                'duration' => $duration,
                'output' => $output
            ];
            
            echo $output;
            echo "\n⏱️  Thời gian: {$duration}s\n";
            echo "📊 Kết quả: " . ($result['status'] === 'PASS' ? "✅ PASS" : "❌ FAIL") . " ({$result['pass_rate']}%)\n\n";
            
        } catch (Exception $e) {
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->testResults[$testName] = [
                'status' => 'ERROR',
                'pass_rate' => 0,
                'total_tests' => 0,
                'passed_tests' => 0,
                'failed_tests' => 0,
                'duration' => $duration,
                'error' => $e->getMessage()
            ];
            
            echo "❌ Lỗi trong test {$testName}: " . $e->getMessage() . "\n";
            echo "⏱️  Thời gian: {$duration}s\n\n";
        }
    }

    private function parseTestResult($output)
    {
        // Parse kết quả từ output của test
        $lines = explode("\n", $output);
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Tổng số test:') !== false) {
                preg_match('/Tổng số test: (\d+)/', $line, $matches);
                if (isset($matches[1])) {
                    $totalTests = (int)$matches[1];
                }
            }
            
            if (strpos($line, 'Passed:') !== false) {
                preg_match('/Passed: (\d+)/', $line, $matches);
                if (isset($matches[1])) {
                    $passedTests = (int)$matches[1];
                }
            }
            
            if (strpos($line, 'Failed:') !== false) {
                preg_match('/Failed: (\d+)/', $line, $matches);
                if (isset($matches[1])) {
                    $failedTests = (int)$matches[1];
                }
            }
        }
        
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
        $status = $passRate >= 80 ? 'PASS' : 'FAIL';
        
        return [
            'status' => $status,
            'pass_rate' => $passRate,
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests
        ];
    }

    private function displayFinalResults()
    {
        $totalDuration = round($this->endTime - $this->startTime, 2);
        
        echo "🏁 KẾT QUẢ CUỐI CÙNG - MUST HAVE FEATURES TEST\n";
        echo "=============================================\n\n";
        
        $overallPassed = 0;
        $overallTotal = 0;
        $passedTests = 0;
        $failedTests = 0;
        $errorTests = 0;
        
        foreach ($this->testResults as $testName => $result) {
            echo "📋 {$testName}:\n";
            echo "  - Trạng thái: " . ($result['status'] === 'PASS' ? "✅ PASS" : ($result['status'] === 'ERROR' ? "❌ ERROR" : "❌ FAIL")) . "\n";
            echo "  - Pass rate: {$result['pass_rate']}%\n";
            echo "  - Tests: {$result['passed_tests']}/{$result['total_tests']}\n";
            echo "  - Thời gian: {$result['duration']}s\n";
            
            if ($result['status'] === 'ERROR') {
                echo "  - Lỗi: {$result['error']}\n";
                $errorTests++;
            } else {
                $overallPassed += $result['passed_tests'];
                $overallTotal += $result['total_tests'];
                
                if ($result['status'] === 'PASS') {
                    $passedTests++;
                } else {
                    $failedTests++;
                }
            }
            echo "\n";
        }
        
        $overallPassRate = $overallTotal > 0 ? round(($overallPassed / $overallTotal) * 100, 2) : 0;
        
        echo "📊 TỔNG KẾT:\n";
        echo "  - Tổng thời gian: {$totalDuration}s\n";
        echo "  - Tests hoàn thành: " . ($passedTests + $failedTests + $errorTests) . "/6\n";
        echo "  - Passed: {$passedTests}\n";
        echo "  - Failed: {$failedTests}\n";
        echo "  - Error: {$errorTests}\n";
        echo "  - Tổng số test cases: {$overallTotal}\n";
        echo "  - Tổng số test cases passed: {$overallPassed}\n";
        echo "  - Overall pass rate: {$overallPassRate}%\n\n";
        
        // Đánh giá tổng thể
        if ($overallPassRate >= 90 && $errorTests === 0) {
            echo "🎉 HỆ THỐNG ĐẠT YÊU CẦU XUẤT SẮC!\n";
            echo "   Tất cả tính năng Must Have hoạt động tốt.\n";
        } elseif ($overallPassRate >= 80 && $errorTests === 0) {
            echo "✅ HỆ THỐNG ĐẠT YÊU CẦU CƠ BẢN!\n";
            echo "   Các tính năng Must Have hoạt động ổn định.\n";
        } elseif ($overallPassRate >= 60) {
            echo "⚠️  HỆ THỐNG CẦN CẢI THIỆN!\n";
            echo "   Một số tính năng cần được sửa chữa.\n";
        } else {
            echo "❌ HỆ THỐNG CẦN SỬA CHỮA NGHIÊM TRỌNG!\n";
            echo "   Nhiều tính năng Must Have không hoạt động đúng.\n";
        }
        
        echo "\n📝 KHUYẾN NGHỊ:\n";
        
        if ($overallPassRate >= 80) {
            echo "  - Hệ thống sẵn sàng cho production\n";
            echo "  - Tiếp tục phát triển các tính năng Should Have\n";
            echo "  - Thực hiện load testing và security audit\n";
        } else {
            echo "  - Ưu tiên sửa chữa các test cases failed\n";
            echo "  - Kiểm tra lại implementation của các tính năng\n";
            echo "  - Thực hiện code review và testing kỹ lưỡng hơn\n";
        }
        
        echo "\n⏰ Kết thúc test lúc: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Chạy tất cả tests
$runner = new MustHaveTestRunner();
$runner->runAllTests();
