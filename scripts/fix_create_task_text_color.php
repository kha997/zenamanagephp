<?php

/**
 * Script sửa text color của tất cả input fields trong trang create task
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🎨 SỬA TEXT COLOR CỦA INPUT FIELDS TRONG CREATE TASK\n";
echo "====================================================\n\n";

$filePath = $basePath . '/resources/views/tasks/create.blade.php';

if (!file_exists($filePath)) {
    echo "❌ File not found: {$filePath}\n";
    exit(1);
}

$content = file_get_contents($filePath);
$originalContent = $content;

// Sửa các input fields thiếu text-gray-700
$patterns = [
    // Input text fields
    '/(<input[^>]*type="text"[^>]*class="[^"]*)(focus:ring-blue-500[^"]*")/' => '$1text-gray-700 $2',
    
    // Textarea fields
    '/(<textarea[^>]*class="[^"]*)(focus:ring-blue-500[^"]*")/' => '$1text-gray-700 $2',
    
    // Select fields
    '/(<select[^>]*class="[^"]*)(focus:ring-blue-500[^"]*")/' => '$1text-gray-700 $2',
    
    // Input number fields
    '/(<input[^>]*type="number"[^>]*class="[^"]*)(focus:ring-blue-500[^"]*")/' => '$1text-gray-700 $2',
    
    // Input date fields
    '/(<input[^>]*type="date"[^>]*class="[^"]*)(focus:ring-blue-500[^"]*")/' => '$1text-gray-700 $2',
];

$totalFixed = 0;

foreach ($patterns as $pattern => $replacement) {
    $newContent = preg_replace($pattern, $replacement, $content);
    if ($newContent !== $content) {
        $content = $newContent;
        $totalFixed++;
    }
}

if ($content !== $originalContent) {
    if (file_put_contents($filePath, $content)) {
        echo "✅ Fixed text color in create task form\n";
        echo "🎯 Total patterns applied: {$totalFixed}\n";
    } else {
        echo "❌ Failed to write file\n";
        exit(1);
    }
} else {
    echo "⚠️ No changes needed\n";
}

echo "\n🎨 Hoàn thành sửa text color!\n";
