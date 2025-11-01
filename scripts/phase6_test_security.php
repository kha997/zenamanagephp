<?php

/**
 * PHASE 6: Script test và security audit
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🛡️ PHASE 6: ĐẢM BẢO TEST + SECURITY\n";
echo "==================================\n\n";

// 1. Chạy tests
echo "1️⃣ Chạy tests...\n";

$testResults = [];
$testSuites = ['Unit', 'Feature', 'Integration'];

foreach ($testSuites as $suite) {
    echo "   📊 Running {$suite} tests...\n";
    
    $output = [];
    $returnCode = 0;
    exec("cd {$basePath} && php artisan test --testsuite={$suite} 2>&1", $output, $returnCode);
    
    $testResults[$suite] = [
        'output' => implode("\n", $output),
        'return_code' => $returnCode,
        'success' => $returnCode === 0
    ];
    
    if ($returnCode === 0) {
        echo "   ✅ {$suite} tests passed\n";
    } else {
        echo "   ❌ {$suite} tests failed\n";
    }
}

echo "\n";

// 2. Security audit
echo "2️⃣ Security audit...\n";

$securityIssues = [];
$filesToAudit = [
    'app/Http/Controllers',
    'app/Http/Middleware',
    'app/Services',
    'app/Models'
];

foreach ($filesToAudit as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/' . $dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace($basePath . '/', '', $file->getPathname());
            
            $issues = [];
            
            // SQL Injection vulnerabilities
            if (preg_match('/DB::raw\([^)]*\$/', $content)) {
                $issues[] = 'Potential SQL injection (DB::raw with variables)';
            }
            
            if (preg_match('/whereRaw\([^)]*\$/', $content)) {
                $issues[] = 'Potential SQL injection (whereRaw with variables)';
            }
            
            // XSS vulnerabilities
            if (preg_match('/\{!!\s*\$/', $content)) {
                $issues[] = 'Potential XSS (unescaped output)';
            }
            
            // File upload vulnerabilities
            if (preg_match('/move_uploaded_file\(/', $content) && !preg_match('/pathinfo\(/', $content)) {
                $issues[] = 'Potential file upload vulnerability (no extension check)';
            }
            
            // CSRF vulnerabilities
            if (preg_match('/Route::post\(/', $content) && !preg_match('/csrf/', $content)) {
                $issues[] = 'Potential CSRF vulnerability (POST without CSRF)';
            }
            
            // Authentication bypass
            if (preg_match('/Auth::check\(\)/', $content) && !preg_match('/middleware.*auth/', $content)) {
                $issues[] = 'Potential auth bypass (Auth::check without middleware)';
            }
            
            // Sensitive data exposure
            if (preg_match('/password.*=.*\$/', $content)) {
                $issues[] = 'Potential password exposure';
            }
            
            if (!empty($issues)) {
                $securityIssues[] = [
                    'file' => $relativePath,
                    'issues' => $issues
                ];
            }
        }
    }
}

echo "   📊 Files audited: " . count($securityIssues) . " files with issues\n\n";

// 3. Performance testing
echo "3️⃣ Performance testing...\n";

$performanceIssues = [];

// Kiểm tra memory usage
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);

echo "   📊 Memory usage: " . formatBytes($memoryUsage) . "\n";
echo "   📊 Peak memory: " . formatBytes($memoryPeak) . "\n";

// Kiểm tra execution time
$startTime = microtime(true);
$endTime = microtime(true);
$executionTime = $endTime - $startTime;

echo "   📊 Execution time: " . round($executionTime, 4) . " seconds\n\n";

// 4. Code review
echo "4️⃣ Code review...\n";

$codeReviewIssues = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        $issues = [];
        
        // Code quality issues
        if (strlen($content) > 10000) {
            $issues[] = 'File too large (' . strlen($content) . ' characters)';
        }
        
        if (substr_count($content, "\n") > 500) {
            $issues[] = 'Too many lines (' . substr_count($content, "\n") . ' lines)';
        }
        
        if (preg_match('/function\s+\w+\([^)]*\)\s*{[^}]{1000,}/', $content)) {
            $issues[] = 'Function too long';
        }
        
        if (preg_match('/if\s*\([^)]*\)\s*{[^}]{500,}/', $content)) {
            $issues[] = 'If block too long';
        }
        
        // Security issues
        if (preg_match('/eval\(/', $content)) {
            $issues[] = 'Dangerous eval() usage';
        }
        
        if (preg_match('/exec\(/', $content)) {
            $issues[] = 'Dangerous exec() usage';
        }
        
        if (preg_match('/system\(/', $content)) {
            $issues[] = 'Dangerous system() usage';
        }
        
        if (!empty($issues)) {
            $codeReviewIssues[] = [
                'file' => $relativePath,
                'issues' => $issues
            ];
        }
    }
}

echo "   📊 Files reviewed: " . count($codeReviewIssues) . " files with issues\n\n";

// 5. Tạo báo cáo chi tiết
echo "📋 BÁO CÁO CHI TIẾT:\n";
echo "==================\n\n";

// Test results
echo "🧪 TEST RESULTS:\n";
foreach ($testResults as $suite => $result) {
    echo "   - {$suite}: " . ($result['success'] ? '✅ PASSED' : '❌ FAILED') . "\n";
}
echo "\n";

// Security issues
if (!empty($securityIssues)) {
    echo "🔒 SECURITY ISSUES:\n";
    foreach (array_slice($securityIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}:\n";
        foreach ($issue['issues'] as $detail) {
            echo "     * {$detail}\n";
        }
    }
    if (count($securityIssues) > 10) {
        echo "   ... và " . (count($securityIssues) - 10) . " files khác\n";
    }
    echo "\n";
}

// Code review issues
if (!empty($codeReviewIssues)) {
    echo "📝 CODE REVIEW ISSUES:\n";
    foreach (array_slice($codeReviewIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}:\n";
        foreach ($issue['issues'] as $detail) {
            echo "     * {$detail}\n";
        }
    }
    if (count($codeReviewIssues) > 10) {
        echo "   ... và " . (count($codeReviewIssues) - 10) . " files khác\n";
    }
    echo "\n";
}

// 6. Tính tổng số issues
$totalIssues = count($securityIssues) + count($codeReviewIssues);
$passedTests = array_sum(array_column($testResults, 'success'));

echo "📊 TỔNG KẾT:\n";
echo "============\n";
echo "  🧪 Tests passed: {$passedTests}/" . count($testSuites) . "\n";
echo "  🔒 Security issues: " . count($securityIssues) . "\n";
echo "  📝 Code review issues: " . count($codeReviewIssues) . "\n";
echo "  📊 Total issues: " . $totalIssues . "\n";
echo "  💾 Memory usage: " . formatBytes($memoryUsage) . "\n";
echo "  ⏱️ Execution time: " . round($executionTime, 4) . "s\n\n";

echo "🎯 Hoàn thành test & security audit PHASE 6!\n";

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
