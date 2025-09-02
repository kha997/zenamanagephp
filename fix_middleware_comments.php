<?php declare(strict_types=1);

// Script để sửa các middleware bị comment sai cách trong routes/api.php

$routeFile = __DIR__ . '/routes/api.php';

if (!file_exists($routeFile)) {
    echo "File routes/api.php không tồn tại!\n";
    exit(1);
}

$content = file_get_contents($routeFile);

echo "Đang sửa các middleware bị comment sai cách...\n";

// Backup file gốc
file_put_contents($routeFile . '.backup', $content);
echo "Đã tạo backup: routes/api.php.backup\n";

// Tìm và sửa các pattern middleware bị comment sai
$patterns = [
    // Pattern 1: ->middleware('// rbac:...')
    '/->middleware\(\s*[\'"]\s*\/\/\s*rbac:[^\)]+\)/' => '',
    
    // Pattern 2: ->middleware(['auth:api', '// rbac:...'])
    '/->middleware\(\[([^\]]*)[\'"]\s*\/\/\s*rbac:[^\'"][^\]]*\]\)/' => function($matches) {
        // Xóa middleware rbac bị comment và clean up array
        $middlewares = $matches[1];
        $middlewares = preg_replace('/,\s*[\'"]\s*\/\/\s*rbac:[^\'"][^,]*/', '', $middlewares);
        $middlewares = preg_replace('/[\'"]\s*\/\/\s*rbac:[^\'"][^,]*,?/', '', $middlewares);
        $middlewares = trim($middlewares, ', ');
        
        if (empty(trim($middlewares))) {
            return '';
        }
        return "->middleware([{$middlewares}])";
    },
    
    // Pattern 3: Xóa các dòng chỉ chứa comment middleware
    '/^\s*\/\/\s*->middleware\([^\n]*$/m' => '',
];

$fixedCount = 0;

foreach ($patterns as $pattern => $replacement) {
    if (is_callable($replacement)) {
        $newContent = preg_replace_callback($pattern, $replacement, $content);
    } else {
        $newContent = preg_replace($pattern, $replacement, $content);
    }
    
    if ($newContent !== $content) {
        $fixedCount += substr_count($content, '// rbac') - substr_count($newContent, '// rbac');
        $content = $newContent;
    }
}

// Clean up multiple empty lines
$content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);

// Ghi lại file
file_put_contents($routeFile, $content);

echo "Đã sửa {$fixedCount} middleware bị comment sai cách.\n";
echo "File routes/api.php đã được cập nhật.\n";

// Test lại API
echo "\nĐang test lại API...\n";

// Clear cache
exec('php artisan optimize:clear 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "Cache đã được clear.\n";
} else {
    echo "Lỗi khi clear cache: " . implode("\n", $output) . "\n";
}

// Test API endpoint
$testUrl = 'http://localhost:8000/api/test';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Lỗi khi test API: {$error}\n";
} else {
    echo "Test API /test - HTTP Code: {$httpCode}\n";
    if ($httpCode === 200) {
        echo "✅ API cơ bản hoạt động tốt!\n";
        
        // Test API với authentication
        $authUrl = 'http://localhost:8000/api/user';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer test-token'
        ]);
        
        $authResponse = curl_exec($ch);
        $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Test API /user (cần auth) - HTTP Code: {$authHttpCode}\n";
        if ($authHttpCode !== 500) {
            echo "✅ Lỗi middleware đã được khắc phục!\n";
        } else {
            echo "⚠️ Vẫn còn lỗi với authentication, cần điều tra thêm.\n";
        }
    } else {
        echo "⚠️ Vẫn còn vấn đề với API.\n";
    }
}

echo "\nHoàn thành!\n";