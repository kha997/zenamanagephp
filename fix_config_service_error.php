<?php declare(strict_types=1);

echo "=== SỬA LỖI TARGET CLASS [CONFIG] DOES NOT EXIST ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Kiểm tra bootstrap/app.php
echo "1. Kiểm tra bootstrap/app.php...\n";
$bootstrapPath = __DIR__ . '/bootstrap/app.php';
if (!file_exists($bootstrapPath)) {
    echo "❌ File bootstrap/app.php không tồn tại!\n";
    echo "Tạo lại file bootstrap/app.php...\n";
    
    $bootstrapContent = <<<'PHP'
<?php

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
PHP;
    
    file_put_contents($bootstrapPath, $bootstrapContent);
    echo "✅ Đã tạo lại bootstrap/app.php\n";
} else {
    echo "✅ File bootstrap/app.php tồn tại\n";
    
    // Kiểm tra nội dung
    $content = file_get_contents($bootstrapPath);
    if (strpos($content, 'Illuminate\\Foundation\\Application') === false) {
        echo "❌ Nội dung bootstrap/app.php không hợp lệ\n";
        echo "Backup và tạo lại...\n";
        copy($bootstrapPath, $bootstrapPath . '.backup.' . time());
        
        $bootstrapContent = <<<'PHP'
<?php

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
PHP;
        
        file_put_contents($bootstrapPath, $bootstrapContent);
        echo "✅ Đã sửa bootstrap/app.php\n";
    } else {
        echo "✅ Nội dung bootstrap/app.php hợp lệ\n";
    }
}

// 2. Kiểm tra config/app.php
echo "\n2. Kiểm tra config/app.php...\n";
$configAppPath = __DIR__ . '/config/app.php';
if (!file_exists($configAppPath)) {
    echo "❌ File config/app.php không tồn tại!\n";
} else {
    echo "✅ File config/app.php tồn tại\n";
    
    // Kiểm tra providers
    $configContent = file_get_contents($configAppPath);
    if (strpos($configContent, 'Illuminate\\Config\\ConfigServiceProvider') === false) {
        echo "❌ ConfigServiceProvider không được đăng ký\n";
        echo "Thêm ConfigServiceProvider...\n";
        
        // Backup
        copy($configAppPath, $configAppPath . '.backup.' . time());
        
        // Thêm ConfigServiceProvider vào đầu danh sách providers
        $configContent = str_replace(
            "'providers' => [\n",
            "'providers' => [\n        Illuminate\\Config\\ConfigServiceProvider::class,\n",
            $configContent
        );
        
        file_put_contents($configAppPath, $configContent);
        echo "✅ Đã thêm ConfigServiceProvider\n";
    } else {
        echo "✅ ConfigServiceProvider đã được đăng ký\n";
    }
}

// 3. Kiểm tra autoloader
echo "\n3. Kiểm tra autoloader...\n";
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    echo "❌ Vendor autoload không tồn tại!\n";
    echo "Chạy composer install...\n";
    exec('cd ' . __DIR__ . ' && composer install 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        echo "✅ Composer install thành công\n";
    } else {
        echo "❌ Composer install thất bại:\n";
        echo implode("\n", $output) . "\n";
    }
} else {
    echo "✅ Vendor autoload tồn tại\n";
}

// 4. Clear tất cả cache
echo "\n4. Clear tất cả cache...\n";
$commands = [
    'composer dump-autoload',
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan route:clear',
    'php artisan view:clear'
];

foreach ($commands as $command) {
    echo "Chạy: $command\n";
    exec("cd " . __DIR__ . " && $command 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "✅ Thành công\n";
    } else {
        echo "⚠️ Có warning (có thể bỏ qua)\n";
    }
    unset($output);
}

// 5. Kiểm tra quyền thư mục
echo "\n5. Kiểm tra quyền thư mục...\n";
$directories = [
    'storage',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
];

foreach ($directories as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0775, true);
        echo "✅ Tạo thư mục: $dir\n";
    }
    
    if (!is_writable($fullPath)) {
        chmod($fullPath, 0775);
        echo "✅ Sửa quyền: $dir\n";
    }
}

// 6. Test lại route cơ bản
echo "\n6. Test lại route cơ bản...\n";
try {
    // Test include bootstrap
    $app = require __DIR__ . '/bootstrap/app.php';
    echo "✅ Bootstrap app thành công\n";
    
    // Test config service
    if ($app->bound('config')) {
        echo "✅ Config service đã được bound\n";
    } else {
        echo "❌ Config service chưa được bound\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi khi test bootstrap: " . $e->getMessage() . "\n";
}

// 7. Test HTTP request
echo "\n7. Test HTTP request...\n";
$testUrl = 'http://localhost/zenamanage/public/api/test';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    echo "✅ Route /api/test hoạt động bình thường\n";
} else {
    echo "❌ Route /api/test vẫn lỗi\n";
    echo "Response (100 ký tự đầu):\n";
    echo substr($response, 0, 100) . "...\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Nếu vẫn lỗi, hãy kiểm tra:\n";
echo "1. XAMPP Apache error log: /Applications/XAMPP/xamppfiles/logs/error_log\n";
echo "2. PHP error log: /Applications/XAMPP/xamppfiles/logs/php_error_log\n";
echo "3. Laravel log: storage/logs/laravel.log\n";