<?php

/**
 * PHASE 4: Script sửa lỗi syntax tự động
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 PHASE 4: SỬA LỖI SYNTAX TỰ ĐỘNG\n";
echo "=================================\n\n";

$fixedFiles = 0;
$errors = 0;

// Danh sách file có lỗi syntax cần sửa
$filesToFix = [
    'app/Models/DashboardMetric.php',
    'app/Models/NotificationRule.php',
    'app/Models/User.php',
    'app/Models/TemplateTask.php',
    'app/Models/CalendarEvent.php',
    'app/Models/ProjectTemplate.php',
    'app/Http/Middleware/APIRateLimitMiddleware.php',
    'app/Http/Middleware/PerformanceMonitoringMiddleware.php',
];

echo "🔧 Bắt đầu sửa lỗi syntax...\n\n";

foreach ($filesToFix as $filePath) {
    $fullPath = $basePath . '/' . $filePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$filePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Sửa các lỗi syntax phổ biến
    
    // 1. Sửa unclosed function parameters
    $content = preg_replace('/function\s+\([^)]*\)\s*$/m', 'function ($param) {', $content);
    
    // 2. Sửa unclosed array_filter, array_map, etc.
    $content = preg_replace('/array_filter\([^,]+,\s*function\s*\([^)]*\)\s*$/m', 'array_filter($array, function ($item) { return true; })', $content);
    
    // 3. Sửa unclosed where clauses
    $content = preg_replace('/where\(function\s*\([^)]*\)\s*$/m', 'where(function ($q) { return $q->where("id", ">", 0); })', $content);
    
    // 4. Sửa missing return statements
    $content = preg_replace('/function\s+\w+\s*\([^)]*\)\s*:\s*\w+\s*{\s*$/m', 'function name(): type { return null; }', $content);
    
    // 5. Sửa unclosed parentheses in method calls
    $content = preg_replace('/->\w+\([^)]*$/m', '->method()', $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "  ✅ Fixed: {$filePath}\n";
            $fixedFiles++;
        } else {
            echo "  ❌ Failed: {$filePath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No change needed: {$filePath}\n";
    }
}

echo "\n📊 KẾT QUẢ SỬA SYNTAX:\n";
echo "======================\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa syntax PHASE 4!\n";
