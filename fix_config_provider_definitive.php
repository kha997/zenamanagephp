<?php declare(strict_types=1);

echo "=== FIX CONFIG SERVICE PROVIDER - DEFINITIVE SOLUTION ===\n";
echo "Thực hiện theo hướng dẫn phân tích chính xác của người dùng\n\n";

// Bước 1: Sửa config/app.php - xóa ConfigServiceProvider sai
echo "1. Sửa config/app.php - xóa Illuminate\\Config\\ConfigServiceProvider::class\n";
$configPath = __DIR__ . '/config/app.php';
$configContent = file_get_contents($configPath);

// Backup file gốc
file_put_contents($configPath . '.backup', $configContent);
echo "   - Đã backup config/app.php\n";

// Xóa dòng ConfigServiceProvider sai
$configContent = str_replace(
    "        Illuminate\\Config\\ConfigServiceProvider::class,\n",
    "",
    $configContent
);

// Xóa các variant khác có thể có
$configContent = str_replace(
    "Illuminate\\Config\\ConfigServiceProvider::class,",
    "",
    $configContent
);

file_put_contents($configPath, $configContent);
echo "   ✅ Đã xóa ConfigServiceProvider khỏi config/app.php\n\n";

// Bước 2: Làm sạch cache & rebuild autoload
echo "2. Làm sạch cache và rebuild autoload\n";

// Xóa bootstrap cache
echo "   - Xóa bootstrap/cache/*.php\n";
$bootstrapCacheDir = __DIR__ . '/bootstrap/cache';
if (is_dir($bootstrapCacheDir)) {
    $files = glob($bootstrapCacheDir . '/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "   ✅ Đã xóa " . count($files) . " file cache\n";
}

// Composer dump-autoload
echo "   - Chạy composer dump-autoload -o\n";
exec('composer dump-autoload -o 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "   ✅ Composer dump-autoload thành công\n";
} else {
    echo "   ❌ Composer dump-autoload thất bại: " . implode("\n", $output) . "\n";
}

// Bước 3: Test package discovery
echo "\n3. Test package discovery\n";
exec('php artisan package:discover 2>&1', $discoverOutput, $discoverCode);
if ($discoverCode === 0) {
    echo "   ✅ Package discovery thành công!\n";
    echo "   " . implode("\n   ", $discoverOutput) . "\n";
} else {
    echo "   ❌ Package discovery vẫn lỗi:\n";
    echo "   " . implode("\n   ", $discoverOutput) . "\n";
}

// Bước 4: Clear các cache Laravel
if ($discoverCode === 0) {
    echo "\n4. Clear cache Laravel\n";
    
    $commands = [
        'php artisan config:clear',
        'php artisan cache:clear', 
        'php artisan route:clear',
        'php artisan view:clear'
    ];
    
    foreach ($commands as $cmd) {
        echo "   - Chạy: $cmd\n";
        exec("$cmd 2>&1", $cmdOutput, $cmdCode);
        if ($cmdCode === 0) {
            echo "   ✅ Thành công\n";
        } else {
            echo "   ⚠️  Lỗi: " . implode(" ", $cmdOutput) . "\n";
        }
    }
}

// Bước 5: Test artisan version
echo "\n5. Test Laravel Artisan\n";
exec('php artisan --version 2>&1', $versionOutput, $versionCode);
if ($versionCode === 0) {
    echo "   ✅ Laravel Artisan hoạt động: " . implode(" ", $versionOutput) . "\n";
} else {
    echo "   ❌ Laravel Artisan vẫn lỗi: " . implode(" ", $versionOutput) . "\n";
}

echo "\n=== HOÀN THÀNH ===\n";
if ($discoverCode === 0 && $versionCode === 0) {
    echo "🎉 Đã khắc phục thành công lỗi ConfigServiceProvider!\n";
    echo "Laravel framework hiện đã hoạt động bình thường.\n";
    echo "\nTiếp theo cần:\n";
    echo "- Kiểm tra middleware alias 'rbac' trong app/Http/Kernel.php\n";
    echo "- Sửa các route middleware từ 'auth' thành 'auth:api'\n";
    echo "- Test API endpoints\n";
} else {
    echo "⚠️  Vẫn còn vấn đề cần khắc phục thêm.\n";
}