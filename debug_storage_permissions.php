<?php declare(strict_types=1);

echo "=== Kiểm tra quyền truy cập Storage ===\n";

$storagePath = __DIR__ . '/storage';
$viewsPath = $storagePath . '/framework/views';
$testFile = $viewsPath . '/test_write_permission.txt';

// Kiểm tra quyền thư mục
echo "📁 Kiểm tra quyền thư mục:\n";
echo "Storage: " . substr(sprintf('%o', fileperms($storagePath)), -4) . "\n";
echo "Views: " . substr(sprintf('%o', fileperms($viewsPath)), -4) . "\n";

// Kiểm tra owner
echo "\n👤 Kiểm tra owner:\n";
echo "Current user: " . get_current_user() . "\n";
echo "Storage owner: " . posix_getpwuid(fileowner($storagePath))['name'] . "\n";
echo "Views owner: " . posix_getpwuid(fileowner($viewsPath))['name'] . "\n";

// Test ghi file
echo "\n✍️ Test ghi file:\n";
try {
    $result = file_put_contents($testFile, 'Test write permission: ' . date('Y-m-d H:i:s'));
    if ($result !== false) {
        echo "✅ Ghi file thành công: {$testFile}\n";
        unlink($testFile); // Xóa file test
    } else {
        echo "❌ Không thể ghi file: {$testFile}\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi khi ghi file: " . $e->getMessage() . "\n";
}

// Kiểm tra web server user
echo "\n🌐 Web server info:\n";
if (function_exists('posix_getpwuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    echo "Web server user: " . $processUser['name'] . "\n";
}

echo "\n=== Kết thúc kiểm tra ===\n";