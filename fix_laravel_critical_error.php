<?php declare(strict_types=1);

echo "=== KHẮC PHỤC LỖI LARAVEL CRITICAL ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Directory: " . getcwd() . "\n\n";

// 1. Kiểm tra composer.json và composer.lock
echo "1. Kiểm tra Composer files...\n";
if (!file_exists('composer.json')) {
    echo "❌ composer.json không tồn tại!\n";
    exit(1);
}

if (!file_exists('composer.lock')) {
    echo "⚠️ composer.lock không tồn tại, sẽ tạo mới\n";
}

// 2. Xóa hoàn toàn vendor và cache
echo "\n2. Xóa hoàn toàn vendor và cache...\n";
if (is_dir('vendor')) {
    echo "Xóa thư mục vendor...\n";
    shell_exec('rm -rf vendor');
}

if (file_exists('composer.lock')) {
    echo "Xóa composer.lock...\n";
    unlink('composer.lock');
}

// Xóa tất cả cache Laravel
$cacheDirs = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/routes.php', 
    'bootstrap/cache/services.php',
    'bootstrap/cache/packages.php'
];

foreach ($cacheDirs as $file) {
    if (file_exists($file)) {
        echo "Xóa cache: $file\n";
        unlink($file);
    }
}

// 3. Clear Composer cache
echo "\n3. Clear Composer cache...\n";
shell_exec('composer clear-cache');

// 4. Kiểm tra PHP requirements trong composer.json
echo "\n4. Kiểm tra PHP requirements...\n";
$composerData = json_decode(file_get_contents('composer.json'), true);
if (isset($composerData['require']['php'])) {
    echo "PHP requirement: " . $composerData['require']['php'] . "\n";
}

// 5. Install với các options khác nhau
echo "\n5. Thử install với các options...\n";

$installCommands = [
    'composer install --no-cache --no-scripts --no-plugins',
    'composer install --no-cache --ignore-platform-reqs',
    'composer install --no-cache'
];

foreach ($installCommands as $cmd) {
    echo "\nThử: $cmd\n";
    $output = shell_exec($cmd . ' 2>&1');
    echo $output;
    
    // Kiểm tra xem có thành công không
    if (file_exists('vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php')) {
        echo "✅ Thành công với lệnh: $cmd\n";
        break;
    } else {
        echo "❌ Thất bại với lệnh: $cmd\n";
    }
}

// 6. Kiểm tra file ConfigServiceProvider
echo "\n6. Kiểm tra ConfigServiceProvider...\n";
$configServiceProvider = 'vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php';
if (file_exists($configServiceProvider)) {
    echo "✅ ConfigServiceProvider tồn tại\n";
} else {
    echo "❌ ConfigServiceProvider KHÔNG tồn tại\n";
    
    // Thử tải lại Laravel framework
    echo "Thử tải lại Laravel framework...\n";
    shell_exec('composer require laravel/framework --no-cache');
}

// 7. Tạo lại autoloader
echo "\n7. Tạo lại autoloader...\n";
shell_exec('composer dump-autoload --optimize');

// 8. Test Laravel
echo "\n8. Test Laravel...\n";
echo "Test php artisan --version:\n";
$artisanOutput = shell_exec('php artisan --version 2>&1');
echo $artisanOutput;

if (strpos($artisanOutput, 'Laravel Framework') !== false) {
    echo "✅ Laravel hoạt động bình thường\n";
    
    // Clear cache Laravel
    echo "\nClear Laravel cache...\n";
    shell_exec('php artisan config:clear 2>&1');
    shell_exec('php artisan route:clear 2>&1');
    shell_exec('php artisan view:clear 2>&1');
    
    // Test API
    echo "\nTest API...\n";
    $apiOutput = shell_exec('curl -s http://localhost/api/test 2>&1');
    echo "API Response: $apiOutput\n";
    
} else {
    echo "❌ Laravel vẫn chưa hoạt động\n";
    echo "Cần kiểm tra thêm...\n";
}

echo "\n=== HOÀN THÀNH ===\n";
?>