<?php

/**
 * Git Pre-commit Hook để kiểm tra duplicate imports
 * 
 * Cài đặt:
 * 1. Copy file này vào .git/hooks/pre-commit
 * 2. chmod +x .git/hooks/pre-commit
 * 
 * Hoặc sử dụng với husky:
 * npx husky add .husky/pre-commit "php scripts/check-duplicate-imports.php"
 */

function checkDuplicateImports($filePath) {
    $content = file_get_contents($filePath);
    
    // Tìm tất cả use statements
    preg_match_all('/^use\s+([^;]+);/m', $content, $matches);
    
    if (empty($matches[1])) {
        return [];
    }
    
    $imports = array_map('trim', $matches[1]);
    $duplicateImports = array_diff_assoc($imports, array_unique($imports));
    
    return array_unique($duplicateImports);
}

function main() {
    $stagedFiles = [];
    
    // Lấy danh sách files đã staged
    $output = shell_exec('git diff --cached --name-only --diff-filter=ACMR');
    if ($output) {
        $stagedFiles = array_filter(explode("\n", trim($output)), function($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'php';
        });
    }
    
    if (empty($stagedFiles)) {
        echo "✅ Không có PHP files nào được staged.\n";
        return 0;
    }
    
    $hasErrors = false;
    
    foreach ($stagedFiles as $file) {
        if (!file_exists($file)) {
            continue;
        }
        
        $duplicates = checkDuplicateImports($file);
        
        if (!empty($duplicates)) {
            $hasErrors = true;
            echo "❌ File: $file\n";
            foreach ($duplicates as $duplicate) {
                echo "   🔄 Duplicate import: $duplicate\n";
            }
            echo "\n";
        }
    }
    
    if ($hasErrors) {
        echo "🚫 Commit bị từ chối do có duplicate imports!\n";
        echo "💡 Hãy sửa các duplicate imports trước khi commit.\n";
        return 1;
    }
    
    echo "✅ Không có duplicate imports nào được phát hiện.\n";
    return 0;
}

exit(main());
