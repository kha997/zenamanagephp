<?php

/**
 * Script sửa lỗi namespace trong Controllers
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI NAMESPACE TRONG CONTROLLERS\n";
echo "====================================\n\n";

$controllerFiles = glob($basePath . '/app/Http/Controllers/**/*.php');
$fixedFiles = 0;
$errors = 0;

foreach ($controllerFiles as $controllerFile) {
    $content = file_get_contents($controllerFile);
    $originalContent = $content;
    $relativePath = str_replace($basePath . '/', '', $controllerFile);
    
    // Tìm namespace sai
    if (preg_match('/namespace App\\\\Http\\\\Controllers\\\\([^;]+);/', $content, $matches)) {
        $currentNamespace = $matches[1];
        
        // Kiểm tra xem có conflict không (namespace kết thúc bằng tên class)
        $className = basename($controllerFile, '.php');
        if (str_ends_with($currentNamespace, $className)) {
            // Sửa namespace
            $correctNamespace = str_replace('\\' . $className, '', $currentNamespace);
            $content = preg_replace('/namespace App\\\\Http\\\\Controllers\\\\([^;]+);/', "namespace App\\Http\\Controllers\\{$correctNamespace};", $content);
            
            if ($content !== $originalContent) {
                if (file_put_contents($controllerFile, $content)) {
                    echo "  ✅ Fixed: {$relativePath} (namespace: {$currentNamespace} -> {$correctNamespace})\n";
                    $fixedFiles++;
                } else {
                    echo "  ❌ Failed: {$relativePath}\n";
                    $errors++;
                }
            } else {
                echo "  ⚠️ No changes needed: {$relativePath}\n";
            }
        } else {
            echo "  ✅ OK: {$relativePath}\n";
        }
    }
}

echo "\n📊 KẾT QUẢ:\n";
echo "===========\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa lỗi namespace!\n";
