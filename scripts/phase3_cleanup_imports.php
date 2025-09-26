<?php

/**
 * PHASE 3: Script cleanup imports không sử dụng
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🧹 PHASE 3: CLEANUP IMPORTS KHÔNG SỬ DỤNG\n";
echo "========================================\n\n";

$cleanedFiles = 0;
$totalImportsRemoved = 0;
$errors = 0;

// Scan tất cả file PHP trong app/
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $lines = explode("\n", $content);
        $importsRemoved = 0;
        
        // Tìm và xóa unused imports
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/^use\s+([^;]+);/', trim($line), $matches)) {
                $import = $matches[1];
                $className = basename(str_replace('\\', '/', $import));
                
                // Kiểm tra xem class có được sử dụng trong file không
                $remainingContent = implode("\n", array_slice($lines, $lineNum + 1));
                
                // Loại trừ một số trường hợp đặc biệt
                $skipRemoval = false;
                
                // Không xóa nếu là trait hoặc interface
                if (strpos($remainingContent, 'use ' . $className) !== false) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu là namespace chính của file
                if (strpos($remainingContent, 'namespace ' . $import) !== false) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong type hints
                if (preg_match('/\b' . preg_quote($className, '/') . '\s*[\(\[\{]/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong extends/implements
                if (preg_match('/(extends|implements)\s+' . preg_quote($className, '/') . '\b/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong new
                if (preg_match('/new\s+' . preg_quote($className, '/') . '\s*\(/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong static calls
                if (preg_match('/' . preg_quote($className, '/') . '::/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong return type
                if (preg_match('/:\s*' . preg_quote($className, '/') . '\b/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                // Không xóa nếu class được sử dụng trong property type
                if (preg_match('/\$\w+:\s*' . preg_quote($className, '/') . '\b/', $remainingContent)) {
                    $skipRemoval = true;
                }
                
                if (!$skipRemoval) {
                    // Xóa dòng import
                    unset($lines[$lineNum]);
                    $importsRemoved++;
                }
            }
        }
        
        if ($importsRemoved > 0) {
            $newContent = implode("\n", $lines);
            
            if (file_put_contents($filePath, $newContent)) {
                echo "  ✅ Cleaned: {$relativePath} ({$importsRemoved} imports)\n";
                $cleanedFiles++;
                $totalImportsRemoved += $importsRemoved;
            } else {
                echo "  ❌ Failed: {$relativePath}\n";
                $errors++;
            }
        }
    }
}

echo "\n📊 KẾT QUẢ CLEANUP IMPORTS:\n";
echo "==========================\n";
echo "  ✅ Files cleaned: {$cleanedFiles}\n";
echo "  ✅ Imports removed: {$totalImportsRemoved}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành cleanup imports PHASE 3!\n";
