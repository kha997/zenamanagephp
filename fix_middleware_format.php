<?php declare(strict_types=1);

echo "Đang sửa format middleware rbac...\n";

$apiFile = 'routes/api.php';

// Tạo backup
$backupFile = $apiFile . '.backup_' . date('Y-m-d_H-i-s');
copy($apiFile, $backupFile);
echo "Đã tạo backup: $backupFile\n";

// Đọc nội dung file
$content = file_get_contents($apiFile);

// Thay thế tất cả '// rbac:' thành 'rbac:'
$originalContent = $content;
$content = preg_replace('/\'\s*\/\/\s*rbac:([^\']*)\'/i', "'rbac:$1'", $content);

// Đếm số lượng thay thế
$changes = 0;
if ($originalContent !== $content) {
    preg_match_all('/\'\s*\/\/\s*rbac:([^\']*)\'/i', $originalContent, $matches);
    $changes = count($matches[0]);
}

// Ghi lại file
file_put_contents($apiFile, $content);

echo "Đã sửa $changes middleware rbac.\n";
echo "File routes/api.php đã được cập nhật.\n";

// Clear cache và test
echo "\nĐang clear cache và test API...\n";
exec('php artisan route:clear');
exec('php artisan config:clear');
exec('php artisan cache:clear');

// Test API
$testUrl = 'http://localhost:8000/api/test';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Accept: application/json'
    ]
]);

$response = @file_get_contents($testUrl, false, $context);
$httpCode = 200;
if (isset($http_response_header)) {
    preg_match('/HTTP\/\d+\.\d+ (\d+)/', $http_response_header[0], $matches);
    $httpCode = (int)$matches[1];
}

echo "Test API /test - HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    echo "✅ API hoạt động bình thường!\n";
} else {
    echo "⚠️ Vẫn còn vấn đề với API.\n";
}

echo "\nHoàn thành!\n";