<?php

/**
 * PHASE 4: Script phân tích và format code theo chuẩn PSR
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "✨ PHASE 4: FORMAT & LÀM SẠCH CODE\n";
echo "=================================\n\n";

// 1. Phân tích lỗi syntax
echo "1️⃣ Phân tích lỗi syntax...\n";

$syntaxErrors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        
        // Kiểm tra syntax bằng php -l
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $syntaxErrors[] = [
                'file' => $relativePath,
                'error' => implode(' ', $output)
            ];
        }
    }
}

echo "   📊 Files with syntax errors: " . count($syntaxErrors) . "\n\n";

// 2. Phân tích code style issues
echo "2️⃣ Phân tích code style issues...\n";

$styleIssues = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        $content = file_get_contents($filePath);
        
        $issues = [];
        
        // Kiểm tra trailing whitespace
        if (preg_match('/\s+$/', $content)) {
            $issues[] = 'Trailing whitespace';
        }
        
        // Kiểm tra mixed line endings
        if (strpos($content, "\r\n") !== false && strpos($content, "\n") !== false) {
            $issues[] = 'Mixed line endings';
        }
        
        // Kiểm tra BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $issues[] = 'BOM detected';
        }
        
        // Kiểm tra tab vs spaces
        if (strpos($content, "\t") !== false && strpos($content, '    ') !== false) {
            $issues[] = 'Mixed indentation (tabs and spaces)';
        }
        
        // Kiểm tra long lines (>120 characters)
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (strlen($line) > 120) {
                $issues[] = "Long line at " . ($lineNum + 1) . " (" . strlen($line) . " chars)";
                break; // Chỉ báo cáo line đầu tiên
            }
        }
        
        // Kiểm tra missing docblocks
        if (preg_match('/class\s+\w+/', $content) && !preg_match('/\/\*\*.*?\*\//s', $content)) {
            $issues[] = 'Missing class docblock';
        }
        
        if (!empty($issues)) {
            $styleIssues[] = [
                'file' => $relativePath,
                'issues' => $issues
            ];
        }
    }
}

echo "   📊 Files with style issues: " . count($styleIssues) . "\n\n";

// 3. Phân tích comments không cần thiết
echo "3️⃣ Phân tích comments không cần thiết...\n";

$unnecessaryComments = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        $content = file_get_contents($filePath);
        
        $comments = [];
        
        // Tìm TODO comments
        if (preg_match_all('/\/\/\s*TODO.*$/m', $content, $matches)) {
            $comments = array_merge($comments, $matches[0]);
        }
        
        // Tìm FIXME comments
        if (preg_match_all('/\/\/\s*FIXME.*$/m', $content, $matches)) {
            $comments = array_merge($comments, $matches[0]);
        }
        
        // Tìm DEBUG comments
        if (preg_match_all('/\/\/\s*DEBUG.*$/m', $content, $matches)) {
            $comments = array_merge($comments, $matches[0]);
        }
        
        // Tìm empty comments
        if (preg_match_all('/\/\/\s*$/', $content, $matches)) {
            $comments = array_merge($comments, $matches[0]);
        }
        
        if (!empty($comments)) {
            $unnecessaryComments[] = [
                'file' => $relativePath,
                'comments' => $comments
            ];
        }
    }
}

echo "   📊 Files with unnecessary comments: " . count($unnecessaryComments) . "\n\n";

// 4. Phân tích imports không tối ưu
echo "4️⃣ Phân tích imports không tối ưu...\n";

$importIssues = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '/', '', $filePath);
        $content = file_get_contents($filePath);
        
        $issues = [];
        
        // Kiểm tra imports không được sắp xếp
        $lines = explode("\n", $content);
        $importLines = [];
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/^use\s+/', trim($line))) {
                $importLines[] = ['line' => $lineNum + 1, 'content' => trim($line)];
            }
        }
        
        if (count($importLines) > 1) {
            $sorted = $importLines;
            usort($sorted, function($a, $b) {
                return strcmp($a['content'], $b['content']);
            });
            
            if ($importLines !== $sorted) {
                $issues[] = 'Imports not sorted alphabetically';
            }
        }
        
        // Kiểm tra duplicate imports
        $imports = [];
        foreach ($importLines as $import) {
            if (in_array($import['content'], $imports)) {
                $issues[] = 'Duplicate import: ' . $import['content'];
            }
            $imports[] = $import['content'];
        }
        
        if (!empty($issues)) {
            $importIssues[] = [
                'file' => $relativePath,
                'issues' => $issues
            ];
        }
    }
}

echo "   📊 Files with import issues: " . count($importIssues) . "\n\n";

// 5. Tạo báo cáo chi tiết
echo "📋 BÁO CÁO CHI TIẾT:\n";
echo "==================\n\n";

if (!empty($syntaxErrors)) {
    echo "❌ SYNTAX ERRORS:\n";
    foreach (array_slice($syntaxErrors, 0, 10) as $error) {
        echo "   - {$error['file']}: {$error['error']}\n";
    }
    if (count($syntaxErrors) > 10) {
        echo "   ... và " . (count($syntaxErrors) - 10) . " lỗi khác\n";
    }
    echo "\n";
}

if (!empty($styleIssues)) {
    echo "🎨 STYLE ISSUES:\n";
    foreach (array_slice($styleIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}:\n";
        foreach ($issue['issues'] as $detail) {
            echo "     * {$detail}\n";
        }
    }
    if (count($styleIssues) > 10) {
        echo "   ... và " . (count($styleIssues) - 10) . " file khác\n";
    }
    echo "\n";
}

if (!empty($unnecessaryComments)) {
    echo "💬 UNNECESSARY COMMENTS:\n";
    foreach (array_slice($unnecessaryComments, 0, 10) as $comment) {
        echo "   - {$comment['file']}:\n";
        foreach (array_slice($comment['comments'], 0, 3) as $detail) {
            echo "     * {$detail}\n";
        }
        if (count($comment['comments']) > 3) {
            echo "     * ... và " . (count($comment['comments']) - 3) . " comment khác\n";
        }
    }
    if (count($unnecessaryComments) > 10) {
        echo "   ... và " . (count($unnecessaryComments) - 10) . " file khác\n";
    }
    echo "\n";
}

if (!empty($importIssues)) {
    echo "📦 IMPORT ISSUES:\n";
    foreach (array_slice($importIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}:\n";
        foreach ($issue['issues'] as $detail) {
            echo "     * {$detail}\n";
        }
    }
    if (count($importIssues) > 10) {
        echo "   ... và " . (count($importIssues) - 10) . " file khác\n";
    }
    echo "\n";
}

// 6. Tính tổng số issues
$totalIssues = count($syntaxErrors) + count($styleIssues) + count($unnecessaryComments) + count($importIssues);

echo "📊 TỔNG KẾT:\n";
echo "============\n";
echo "  ❌ Syntax errors: " . count($syntaxErrors) . "\n";
echo "  🎨 Style issues: " . count($styleIssues) . "\n";
echo "  💬 Unnecessary comments: " . count($unnecessaryComments) . "\n";
echo "  📦 Import issues: " . count($importIssues) . "\n";
echo "  📊 Total issues: " . $totalIssues . "\n\n";

echo "🎯 Hoàn thành phân tích PHASE 4!\n";
