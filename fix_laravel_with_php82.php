<?php declare(strict_types=1);

echo "=== Khắc phục Laravel với PHP 8.2 ===\n\n";

// Kiểm tra PHP version
echo "1. Kiểm tra PHP version:\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Clear tất cả cache Composer
echo "2. Clear Composer cache:\n";
echo shell_exec('composer clear-cache 2>&1') . "\n";

// Xóa vendor directory
echo "3. Xóa vendor directory:\n";
if (is_dir('vendor')) {
    echo shell_exec('rm -rf vendor 2>&1') . "\n";
    echo "Vendor directory đã được xóa\n";
} else {
    echo "Vendor directory không tồn tại\n";
}

// Xóa composer.lock
echo "\n4. Xóa composer.lock:\n";
if (file_exists('composer.lock')) {
    unlink('composer.lock');
    echo "composer.lock đã được xóa\n";
} else {
    echo "composer.lock không tồn tại\n";
}

// Xóa bootstrap/cache
echo "\n5. Xóa bootstrap cache:\n";
if (is_dir('bootstrap/cache')) {
    $files = glob('bootstrap/cache/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Bootstrap cache đã được xóa\n";
} else {
    echo "Bootstrap cache directory không tồn tại\n";
}

// Reinstall dependencies
echo "\n6. Reinstall Composer dependencies:\n";
echo "Đang chạy composer install...\n";
$output = shell_exec('composer install --no-scripts --no-plugins 2>&1');
echo $output . "\n";

// Kiểm tra xem có lỗi không
if (strpos($output, 'ConfigServiceProvider') !== false) {
    echo "\n⚠️  Vẫn còn lỗi ConfigServiceProvider. Thử cách khác...\n\n";
    
    // Thử với --no-dev
    echo "7. Thử install với --no-dev:\n";
    $output2 = shell_exec('composer install --no-dev --no-scripts --no-plugins 2>&1');
    echo $output2 . "\n";
    
    if (strpos($output2, 'ConfigServiceProvider') !== false) {
        echo "\n⚠️  Vẫn còn lỗi. Thử update thay vì install...\n\n";
        
        // Thử composer update
        echo "8. Thử composer update:\n";
        $output3 = shell_exec('composer update --no-scripts --no-plugins 2>&1');
        echo $output3 . "\n";
    }
} else {
    echo "\n✅ Composer install thành công!\n";
}

// Generate autoloader
echo "\n9. Generate autoloader:\n";
echo shell_exec('composer dump-autoload --optimize 2>&1') . "\n";

// Test Laravel
echo "\n10. Test Laravel cơ bản:\n";
echo "Kiểm tra php artisan list:\n";
$artisan_output = shell_exec('php artisan list 2>&1');
if (strpos($artisan_output, 'ConfigServiceProvider') !== false) {
    echo "❌ Vẫn còn lỗi ConfigServiceProvider trong artisan\n";
    echo "Output: " . substr($artisan_output, 0, 500) . "...\n";
} else if (strpos($artisan_output, 'Available commands') !== false) {
    echo "✅ Laravel artisan hoạt động bình thường\n";
} else {
    echo "⚠️  Artisan output không như mong đợi:\n";
    echo substr($artisan_output, 0, 500) . "...\n";
}

// Test API
echo "\n11. Test API cơ bản:\n";
$api_test = shell_exec('curl -s http://localhost/api/test 2>&1');
echo "API Response: " . $api_test . "\n";

echo "\n=== Hoàn thành ===\n";
echo "Nếu vẫn còn lỗi, có thể cần:\n";
echo "1. Kiểm tra file config/app.php\n";
echo "2. Kiểm tra Laravel version compatibility với PHP 8.2\n";
echo "3. Reinstall Laravel framework hoàn toàn\n";
?>