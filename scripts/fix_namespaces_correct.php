<?php

/**
 * Script sửa namespace chính xác
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 Sửa namespace chính xác...\n";

// Tìm tất cả file PHP trong app
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
$phpFiles = new RegexIterator($iterator, '/\.php$/');

$fixedCount = 0;
$errorCount = 0;

foreach ($phpFiles as $file) {
    $filePath = $file->getPathname();
    $relativePath = str_replace($basePath . '/', '', $filePath);
    
    // Skip vendor files
    if (strpos($relativePath, 'vendor/') === 0) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }
    
    $originalContent = $content;
    
    // Determine correct namespace based on file path
    $correctNamespace = '';
    if (strpos($relativePath, 'app/Models/') === 0) {
        $className = basename($filePath, '.php');
        $correctNamespace = 'App\\Models';
    } elseif (strpos($relativePath, 'app/Http/Controllers/') === 0) {
        $className = basename($filePath, '.php');
        $correctNamespace = 'App\\Http\\Controllers';
    } elseif (strpos($relativePath, 'app/Http/Middleware/') === 0) {
        $className = basename($filePath, '.php');
        $correctNamespace = 'App\\Http\\Middleware';
    } elseif (strpos($relativePath, 'app/Http/Requests/') === 0) {
        $className = basename($filePath, '.php');
        $correctNamespace = 'App\\Http\\Requests';
    } elseif (strpos($relativePath, 'app/Services/') === 0) {
        $className = basename($filePath, '.php');
        $correctNamespace = 'App\\Services';
    } else {
        continue; // Skip other files
    }
    
    // Fix namespace
    $content = preg_replace('/^namespace\s+[^;]+;/m', "namespace {$correctNamespace};", $content);
    
    // Fix use statements that reference old namespaces
    $content = preg_replace('/use\s+Src\\[^;]+;/', '', $content);
    
    // Remove any malformed use statements
    $content = preg_replace('/use\s+[^;]*\$[^;]*;/', '', $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "  ✅ Fixed: {$relativePath}\n";
            $fixedCount++;
        } else {
            echo "  ❌ Failed: {$relativePath}\n";
            $errorCount++;
        }
    }
}

echo "\n📊 Kết quả:\n";
echo "  ✅ Fixed: {$fixedCount} files\n";
echo "  ❌ Errors: {$errorCount} files\n";

echo "\n🎯 Hoàn thành sửa namespace!\n";
