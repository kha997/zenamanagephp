<?php

/**
 * Script sửa text color của tất cả input fields từ text-gray-900 thành text-gray-700
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🎨 SỬA TEXT COLOR CỦA INPUT FIELDS\n";
echo "====================================\n\n";

$filesToCheck = [
    'resources/views/tasks/edit.blade.php',
    'resources/views/tasks/create.blade.php',
    'resources/views/tasks/show.blade.php',
    'resources/views/projects/edit.blade.php',
    'resources/views/projects/create.blade.php',
    'resources/views/projects/show.blade.php',
    'resources/views/documents/edit.blade.php',
    'resources/views/documents/create.blade.php',
    'resources/views/documents/show.blade.php',
    'resources/views/change-requests/edit.blade.php',
    'resources/views/change-requests/create.blade.php',
    'resources/views/change-requests/show.blade.php',
];

$totalFixed = 0;

foreach ($filesToCheck as $file) {
    $filePath = $basePath . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "⚠️ File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Sửa text-gray-900 thành text-gray-700 trong input, select, textarea
    $content = preg_replace('/(<input[^>]*class="[^"]*)\btext-gray-900\b([^"]*")/', '$1text-gray-700$2', $content);
    $content = preg_replace('/(<select[^>]*class="[^"]*)\btext-gray-900\b([^"]*")/', '$1text-gray-700$2', $content);
    $content = preg_replace('/(<textarea[^>]*class="[^"]*)\btext-gray-900\b([^"]*")/', '$1text-gray-700$2', $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            $changes = substr_count($originalContent, 'text-gray-900') - substr_count($content, 'text-gray-900');
            echo "✅ Fixed {$changes} instances in: {$file}\n";
            $totalFixed += $changes;
        } else {
            echo "❌ Failed to write: {$file}\n";
        }
    } else {
        echo "⚪ No changes needed: {$file}\n";
    }
}

echo "\n🎯 Tổng cộng đã sửa: {$totalFixed} instances\n";
echo "🎨 Hoàn thành sửa text color!\n";
