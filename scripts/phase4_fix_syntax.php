<?php

/**
 * PHASE 4: Script sửa lỗi syntax
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 PHASE 4: SỬA LỖI SYNTAX\n";
echo "=========================\n\n";

$fixedFiles = 0;
$errors = 0;

// Danh sách file có lỗi syntax cần sửa
$filesToFix = [
    'app/Models/Team.php',
    'app/Models/UserDashboard.php',
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
    
    // 1. Sửa unclosed parentheses
    $content = preg_replace('/\s*\)\s*$/', ')', $content);
    
    // 2. Sửa missing braces
    $content = preg_replace('/\s*if\s*\([^)]+\)\s*$/', 'if ($1) {', $content);
    
    // 3. Sửa trailing commas
    $content = preg_replace('/,\s*\)/', ')', $content);
    
    // 4. Sửa mixed quotes
    $content = preg_replace('/"([^"]*)"([^"]*)"([^"]*)"/', '"$1$2$3"', $content);
    
    // 5. Sửa missing semicolons
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (!empty($line) && 
            !preg_match('/[{};]$/', $line) && 
            !preg_match('/^(class|function|if|else|foreach|for|while|switch|case|default|return|throw|use|namespace)/', $line) &&
            !preg_match('/^\/\//', $line) &&
            !preg_match('/^\*/', $line)) {
            $lines[$i] = $line . ';';
        }
    }
    $content = implode("\n", $lines);
    
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
