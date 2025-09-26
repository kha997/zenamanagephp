<?php

/**
 * Script sửa lỗi import trong Commands
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI IMPORT TRONG COMMANDS\n";
echo "==============================\n\n";

// Tìm tất cả services được sử dụng trong Commands
$commandFiles = glob($basePath . '/app/Console/Commands/*.php');
$servicesUsed = [];

foreach ($commandFiles as $commandFile) {
    $content = file_get_contents($commandFile);
    $filename = basename($commandFile);
    
    // Tìm services được sử dụng
    if (preg_match_all('/private\s+(\w+Service)\s+\$/', $content, $matches)) {
        foreach ($matches[1] as $service) {
            $servicesUsed[$filename][] = $service;
        }
    }
}

echo "📊 Services được sử dụng trong Commands:\n";
foreach ($servicesUsed as $command => $services) {
    echo "  - {$command}: " . implode(', ', $services) . "\n";
}

echo "\n";

// Sửa imports
$fixedFiles = 0;
$errors = 0;

foreach ($commandFiles as $commandFile) {
    $content = file_get_contents($commandFile);
    $originalContent = $content;
    $filename = basename($commandFile);
    
    if (!isset($servicesUsed[$filename])) {
        continue;
    }
    
    $services = $servicesUsed[$filename];
    $importsAdded = [];
    
    foreach ($services as $service) {
        $serviceClass = "App\\Services\\{$service}";
        
        // Kiểm tra xem đã có import chưa
        if (strpos($content, "use {$serviceClass};") === false) {
            // Thêm import
            if (strpos($content, 'use Illuminate\\Console\\Command;') !== false) {
                $content = str_replace('use Illuminate\\Console\\Command;', "use Illuminate\\Console\\Command;\nuse {$serviceClass};", $content);
            } else {
                // Thêm sau namespace
                $content = preg_replace('/namespace App\\\\Console\\\\Commands;(\s*)/', "namespace App\\Console\\Commands;\n\nuse {$serviceClass};\n", $content);
            }
            
            $importsAdded[] = $service;
        }
    }
    
    if (!empty($importsAdded)) {
        if (file_put_contents($commandFile, $content)) {
            echo "  ✅ Fixed {$filename}: " . implode(', ', $importsAdded) . "\n";
            $fixedFiles++;
        } else {
            echo "  ❌ Failed {$filename}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No changes needed: {$filename}\n";
    }
}

echo "\n📊 KẾT QUẢ:\n";
echo "===========\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa lỗi Commands!\n";
