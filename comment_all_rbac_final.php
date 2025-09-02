<?php

// Script để comment tất cả middleware rbac trong routes/api.php
$routeFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php';

if (!file_exists($routeFile)) {
    echo "File routes/api.php không tồn tại!\n";
    exit(1);
}

$content = file_get_contents($routeFile);
$originalContent = $content;

// Patterns để tìm và comment middleware rbac
$patterns = [
    // Pattern 1: 'rbac:permission'
    "/('rbac:[^']*')/" => "// $1",
    // Pattern 2: "rbac:permission"
    '/"rbac:[^"]*"/' => '// $0',
    // Pattern 3: middleware(['auth:api', 'rbac:...'])
    "/middleware\(\['auth:api',\s*'rbac:[^']*'\]\)/" => "middleware(['auth:api']) // rbac disabled",
    // Pattern 4: middleware(['auth:api', "rbac:..."])
    '/middleware\(\["auth:api",\s*"rbac:[^"]*"\]\)/' => 'middleware(["auth:api"]) // rbac disabled',
    // Pattern 5: ->middleware(['auth:api', 'rbac:...'])
    "/->middleware\(\['auth:api',\s*'rbac:[^']*'\]\)/" => "->middleware(['auth:api']) // rbac disabled",
];

$changeCount = 0;

foreach ($patterns as $pattern => $replacement) {
    $newContent = preg_replace($pattern, $replacement, $content);
    if ($newContent !== $content) {
        $matches = preg_match_all($pattern, $content);
        $changeCount += $matches;
        $content = $newContent;
        echo "Pattern '$pattern' - Đã sửa $matches lần\n";
    }
}

// Kiểm tra và sửa các dòng có format đặc biệt
$lines = explode("\n", $content);
$modifiedLines = [];

foreach ($lines as $lineNum => $line) {
    // Tìm các dòng chứa middleware rbac
    if (preg_match('/middleware\(.*rbac:/', $line) && !preg_match('/^\/\//', trim($line))) {
        // Comment toàn bộ dòng
        $modifiedLines[] = '    // ' . trim($line) . ' // RBAC disabled temporarily';
        $changeCount++;
        echo "Dòng " . ($lineNum + 1) . ": Comment toàn bộ dòng middleware rbac\n";
    } else {
        $modifiedLines[] = $line;
    }
}

$content = implode("\n", $modifiedLines);

if ($content !== $originalContent) {
    // Backup file gốc
    $backupFile = $routeFile . '.backup.' . date('Y-m-d_H-i-s');
    copy($routeFile, $backupFile);
    echo "Đã backup file gốc: $backupFile\n";
    
    // Ghi file mới
    file_put_contents($routeFile, $content);
    echo "\n✅ Đã comment $changeCount middleware rbac trong routes/api.php\n";
    
    // Clear cache
    echo "\nClearing Laravel cache...\n";
    exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan route:clear 2>&1', $output, $return);
    if ($return === 0) {
        echo "✅ Route cache cleared\n";
    }
    
    exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan config:clear 2>&1', $output, $return);
    if ($return === 0) {
        echo "✅ Config cache cleared\n";
    }
    
} else {
    echo "Không có thay đổi nào được thực hiện.\n";
}

echo "\nScript hoàn thành!\n";