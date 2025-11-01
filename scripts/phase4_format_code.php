<?php

/**
 * PHASE 4: Script format code và cleanup style
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "✨ PHASE 4: FORMAT CODE & CLEANUP STYLE\n";
echo "======================================\n\n";

$fixedFiles = 0;
$errors = 0;

// Scan tất cả file PHP trong app/
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // 1. Xóa trailing whitespace
        $content = preg_replace('/\s+$/', '', $content);
        
        // 2. Chuẩn hóa line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // 3. Xóa BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // 4. Chuẩn hóa indentation (4 spaces)
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // Thay thế tabs bằng 4 spaces
            $lines[$i] = str_replace("\t", "    ", $line);
        }
        $content = implode("\n", $lines);
        
        // 5. Xóa empty lines thừa
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // 6. Sắp xếp imports alphabetically
        $lines = explode("\n", $content);
        $importLines = [];
        $importStart = -1;
        $importEnd = -1;
        
        foreach ($lines as $i => $line) {
            if (preg_match('/^use\s+/', trim($line))) {
                if ($importStart === -1) {
                    $importStart = $i;
                }
                $importLines[] = ['line' => $i, 'content' => trim($line)];
                $importEnd = $i;
            }
        }
        
        if (!empty($importLines)) {
            // Sắp xếp imports
            usort($importLines, function($a, $b) {
                return strcmp($a['content'], $b['content']);
            });
            
            // Thay thế imports đã sắp xếp
            for ($i = $importStart; $i <= $importEnd; $i++) {
                if (isset($importLines[$i - $importStart])) {
                    $lines[$i] = $importLines[$i - $importStart]['content'];
                }
            }
            
            $content = implode("\n", $lines);
        }
        
        // 7. Xóa comments không cần thiết
        $content = preg_replace('/\/\/\s*TODO.*$/m', '', $content);
        $content = preg_replace('/\/\/\s*FIXME.*$/m', '', $content);
        $content = preg_replace('/\/\/\s*DEBUG.*$/m', '', $content);
        $content = preg_replace('/\/\/\s*$/m', '', $content);
        
        // 8. Xóa empty lines sau khi xóa comments
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        if ($content !== $originalContent) {
            if (file_put_contents($filePath, $content)) {
                echo "  ✅ Formatted: {$relativePath}\n";
                $fixedFiles++;
            } else {
                echo "  ❌ Failed: {$relativePath}\n";
                $errors++;
            }
        }
    }
}

echo "\n📊 KẾT QUẢ FORMAT CODE:\n";
echo "======================\n";
echo "  ✅ Files formatted: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành format code PHASE 4!\n";
