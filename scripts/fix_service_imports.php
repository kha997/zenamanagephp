<?php

/**
 * Script sửa lỗi import Service trong Controllers
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI IMPORT SERVICE TRONG CONTROLLERS\n";
echo "==========================================\n\n";

$controllerFiles = glob($basePath . '/app/Http/Controllers/**/*.php');
$fixedFiles = 0;
$errors = 0;

foreach ($controllerFiles as $controllerFile) {
    $content = file_get_contents($controllerFile);
    $originalContent = $content;
    $relativePath = str_replace($basePath . '/', '', $controllerFile);
    
    // Tìm services được sử dụng
    $servicesUsed = [];
    if (preg_match_all('/private\s+(\w+Service)\s+\$/', $content, $matches)) {
        foreach ($matches[1] as $service) {
            $servicesUsed[] = $service;
        }
    }
    
    if (preg_match_all('/public function __construct\([^)]*(\w+Service)\s+\$/', $content, $matches)) {
        foreach ($matches[1] as $service) {
            if (!in_array($service, $servicesUsed)) {
                $servicesUsed[] = $service;
            }
        }
    }
    
    if (empty($servicesUsed)) {
        continue;
    }
    
    $importsAdded = [];
    foreach ($servicesUsed as $service) {
        $serviceClass = "App\\Services\\{$service}";
        
        // Kiểm tra xem đã có import chưa
        if (strpos($content, "use {$serviceClass};") === false) {
            // Thêm import
            if (strpos($content, 'use App\\Http\\Controllers\\Controller;') !== false) {
                $content = str_replace('use App\\Http\\Controllers\\Controller;', "use App\\Http\\Controllers\\Controller;\nuse {$serviceClass};", $content);
            } else {
                // Thêm sau namespace
                $content = preg_replace('/namespace App\\\\Http\\\\Controllers\\\\([^;]+);(\s*)/', "namespace App\\Http\\Controllers\\$1;\n\nuse {$serviceClass};\n", $content);
            }
            
            $importsAdded[] = $service;
        }
    }
    
    if (!empty($importsAdded)) {
        if (file_put_contents($controllerFile, $content)) {
            echo "  ✅ Fixed {$relativePath}: " . implode(', ', $importsAdded) . "\n";
            $fixedFiles++;
        } else {
            echo "  ❌ Failed {$relativePath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No changes needed: {$relativePath}\n";
    }
}

echo "\n📊 KẾT QUẢ:\n";
echo "===========\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa lỗi Service imports!\n";
