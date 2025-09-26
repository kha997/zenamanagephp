<?php

/**
 * Script sửa lỗi syntax trong TaskService
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI SYNTAX TRONG TASKSERVICE\n";
echo "===================================\n\n";

$filePath = $basePath . '/app/Services/TaskService.php';

if (!file_exists($filePath)) {
    echo "❌ File not found: {$filePath}\n";
    exit(1);
}

$content = file_get_contents($filePath);
$originalContent = $content;

// Sửa tất cả các lỗi "function () " thiếu dấu {
$content = preg_replace('/return DB::transaction\(function \(\)\s*\n\s*([^{])/', "return DB::transaction(function () {\n            $1", $content);

// Sửa các lỗi khác tương tự
$content = preg_replace('/function \(\)\s*\n\s*([^{])/', "function () {\n            $1", $content);

if ($content !== $originalContent) {
    if (file_put_contents($filePath, $content)) {
        echo "✅ Fixed syntax errors in TaskService.php\n";
    } else {
        echo "❌ Failed to write file\n";
        exit(1);
    }
} else {
    echo "⚠️ No syntax errors found\n";
}

// Kiểm tra syntax
echo "\n🔍 Checking syntax...\n";
$output = [];
$returnCode = 0;
exec("php -l {$filePath} 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Syntax is valid!\n";
} else {
    echo "❌ Syntax errors still exist:\n";
    foreach ($output as $line) {
        echo "   {$line}\n";
    }
}

echo "\n🎯 Hoàn thành sửa lỗi syntax!\n";
