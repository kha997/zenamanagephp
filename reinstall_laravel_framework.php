<?php declare(strict_types=1);

echo "=== REINSTALL LARAVEL FRAMEWORK ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Backup composer.json và composer.lock
echo "1. Backup composer files...\n";
if (file_exists('composer.json')) {
    copy('composer.json', 'composer.json.backup.' . date('Ymd_His'));
    echo "✅ Đã backup composer.json\n";
}
if (file_exists('composer.lock')) {
    copy('composer.lock', 'composer.lock.backup.' . date('Ymd_His'));
    echo "✅ Đã backup composer.lock\n";
}

// 2. Xóa thư mục vendor cũ
echo "\n2. Xóa thư mục vendor cũ...\n";
if (is_dir('vendor')) {
    echo "Chạy: rm -rf vendor\n";
    exec('rm -rf vendor 2>&1', $output, $return_code);
    if ($return_code === 0) {
        echo "✅ Đã xóa thư mục vendor\n";
    } else {
        echo "❌ Lỗi khi xóa vendor: " . implode("\n", $output) . "\n";
    }
} else {
    echo "⚠️ Thư mục vendor không tồn tại\n";
}

// 3. Clear Composer cache
echo "\n3. Clear Composer cache...\n";
echo "Chạy: composer clear-cache\n";
exec('composer clear-cache 2>&1', $output, $return_code);
echo implode("\n", $output) . "\n";

// 4. Validate composer.json
echo "\n4. Validate composer.json...\n";
echo "Chạy: composer validate\n";
exec('composer validate 2>&1', $output, $return_code);
if ($return_code === 0) {
    echo "✅ composer.json hợp lệ\n";
} else {
    echo "❌ composer.json có vấn đề:\n" . implode("\n", $output) . "\n";
}

// 5. Install dependencies từ đầu
echo "\n5. Install dependencies từ đầu...\n";
echo "Chạy: composer install --no-cache --no-dev\n";
exec('composer install --no-cache --no-dev 2>&1', $output, $return_code);
echo implode("\n", $output) . "\n";

if ($return_code !== 0) {
    echo "❌ Composer install thất bại, thử với --ignore-platform-reqs\n";
    echo "Chạy: composer install --no-cache --no-dev --ignore-platform-reqs\n";
    exec('composer install --no-cache --no-dev --ignore-platform-reqs 2>&1', $output, $return_code);
    echo implode("\n", $output) . "\n";
}

// 6. Test autoloader
echo "\n6. Test autoloader...\n";
if (file_exists('vendor/autoload.php')) {
    echo "✅ vendor/autoload.php tồn tại\n";
    
    try {
        require_once 'vendor/autoload.php';
        echo "✅ Autoloader load thành công\n";
        
        // Test Laravel core classes
        if (class_exists('Illuminate\Foundation\Application')) {
            echo "✅ Illuminate\Foundation\Application tồn tại\n";
        } else {
            echo "❌ Illuminate\Foundation\Application không tồn tại\n";
        }
        
        if (class_exists('Illuminate\Config\ConfigServiceProvider')) {
            echo "✅ Illuminate\Config\ConfigServiceProvider tồn tại\n";
        } else {
            echo "❌ Illuminate\Config\ConfigServiceProvider không tồn tại\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Lỗi khi load autoloader: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ vendor/autoload.php không tồn tại\n";
}

// 7. Test Laravel bootstrap
echo "\n7. Test Laravel bootstrap...\n";
try {
    if (file_exists('bootstrap/app.php')) {
        echo "Chạy: php -r \"require 'bootstrap/app.php'; echo 'Bootstrap OK';\"\n";
        exec('php -r "require \'bootstrap/app.php\'; echo \'Bootstrap OK\';" 2>&1', $output, $return_code);
        if ($return_code === 0) {
            echo "✅ Laravel bootstrap thành công\n";
        } else {
            echo "❌ Laravel bootstrap thất bại:\n" . implode("\n", $output) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Lỗi khi test bootstrap: " . $e->getMessage() . "\n";
}

// 8. Test HTTP request
echo "\n8. Test HTTP request...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/zenamanage/api/test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo "✅ HTTP request thành công (200)\n";
    echo "Response: " . substr($response, strpos($response, "\r\n\r\n") + 4) . "\n";
} else {
    echo "❌ HTTP request thất bại (HTTP $http_code)\n";
    echo "Response headers:\n" . substr($response, 0, strpos($response, "\r\n\r\n")) . "\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Nếu vẫn có lỗi, hãy thử:\n";
echo "1. Kiểm tra PHP version: php -v\n";
echo "2. Kiểm tra Composer version: composer --version\n";
echo "3. Khởi động lại Apache: sudo /Applications/XAMPP/xamppfiles/bin/apachectl restart\n";
echo "4. Kiểm tra PHP error log trong XAMPP\n";