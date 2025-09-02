<?php declare(strict_types=1);

/**
 * Script tự động thay thế Auth facade bằng AuthHelper
 * Chạy: php fix_auth_facades.php
 */

function fixAuthFacades($directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );
    
    $phpFiles = new RegexIterator($iterator, '/\.php$/');
    $fixedFiles = [];
    
    foreach ($phpFiles as $file) {
        $filePath = $file->getRealPath();
        
        // Bỏ qua AuthHelper.php để tránh thay thế chính nó
        if (strpos($filePath, 'AuthHelper.php') !== false) {
            continue;
        }
        
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Kiểm tra xem file có sử dụng Auth facade không
        if (!preg_match('/Auth::(check|id|user)\(\)/', $content)) {
            continue;
        }
        
        echo "Đang sửa file: $filePath\n";
        
        // 1. Thay thế các lệnh gọi Auth facade
        $replacements = [
            // Thay thế Auth::id() ?? 'system' trước
            '/Auth::id\(\)\s*\?\?\s*[\'"]system[\'"]/' => 'AuthHelper::idOrSystem()',
            '/Auth::check\(\)\s*\?\s*\(string\)\s*Auth::id\(\)\s*:\s*[\'"]system[\'"]/' => 'AuthHelper::idOrSystem()',
            
            // Thay thế các lệnh gọi đơn giản
            '/Auth::check\(\)/' => 'AuthHelper::check()',
            '/Auth::id\(\)/' => 'AuthHelper::id()',
            '/Auth::user\(\)/' => 'AuthHelper::user()',
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // 2. Thêm import AuthHelper nếu chưa có
        if (strpos($content, 'use Src\\Foundation\\Helpers\\AuthHelper;') === false) {
            // Tìm vị trí để thêm import
            if (preg_match('/(namespace\s+[^;]+;)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $namespaceEnd = $matches[0][1] + strlen($matches[0][0]);
                $beforeImports = substr($content, 0, $namespaceEnd);
                $afterNamespace = substr($content, $namespaceEnd);
                
                // Thêm import AuthHelper
                $import = "\n\nuse Src\\Foundation\\Helpers\\AuthHelper;";
                $content = $beforeImports . $import . $afterNamespace;
            }
        }
        
        // 3. Xóa import Auth facade nếu không còn sử dụng
        if (!preg_match('/Auth::/', $content)) {
            $content = preg_replace('/use\s+Illuminate\\\\Support\\\\Facades\\\\Auth;\s*\n/', '', $content);
        }
        
        // Chỉ ghi file nếu có thay đổi
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $fixedFiles[] = $filePath;
            echo "✓ Đã sửa: $filePath\n";
        }
    }
    
    return $fixedFiles;
}

// Chạy script
echo "Bắt đầu sửa Auth facades...\n\n";

$srcDirectory = __DIR__ . '/src';
$fixedFiles = fixAuthFacades($srcDirectory);

echo "\n=== KẾT QUẢ ===\n";
echo "Đã sửa " . count($fixedFiles) . " file(s):\n";
foreach ($fixedFiles as $file) {
    echo "- " . basename($file) . "\n";
}

echo "\nHoàn thành! Hãy chạy test để kiểm tra.\n";