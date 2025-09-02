<?php declare(strict_types=1);

echo "=== FIX PACKAGE DISCOVERY BYPASS ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Backup và tạm thời disable package discovery
echo "1. Backup và disable package discovery...\n";
$composerJsonPath = __DIR__ . '/composer.json';
$composerBackupPath = __DIR__ . '/composer.json.backup-discovery';

if (file_exists($composerJsonPath)) {
    copy($composerJsonPath, $composerBackupPath);
    echo "✅ Đã backup composer.json\n";
    
    $composerContent = file_get_contents($composerJsonPath);
    $composerData = json_decode($composerContent, true);
    
    // Disable package discovery
    $composerData['extra']['laravel']['dont-discover'] = ['*'];
    
    file_put_contents($composerJsonPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "✅ Đã disable package discovery\n";
} else {
    echo "❌ Không tìm thấy composer.json\n";
    exit(1);
}

// 2. Chạy composer install với package discovery disabled
echo "\n2. Chạy composer install với package discovery disabled...\n";
echo "Chạy: composer install --no-scripts --no-dev\n";
passthru('composer install --no-scripts --no-dev 2>&1', $exitCode);

if ($exitCode === 0) {
    echo "✅ Composer install thành công\n";
} else {
    echo "❌ Composer install thất bại\n";
}

// 3. Test autoloader cơ bản
echo "\n3. Test autoloader cơ bản...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ Autoloader loaded thành công\n";
    
    // Test các class cơ bản
    if (class_exists('Illuminate\\Foundation\\Application')) {
        echo "✅ Illuminate\\Foundation\\Application tồn tại\n";
    } else {
        echo "❌ Illuminate\\Foundation\\Application không tồn tại\n";
    }
    
    if (class_exists('Illuminate\\Config\\ConfigServiceProvider')) {
        echo "✅ Illuminate\\Config\\ConfigServiceProvider tồn tại\n";
    } else {
        echo "❌ Illuminate\\Config\\ConfigServiceProvider không tồn tại\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi autoloader: " . $e->getMessage() . "\n";
}

// 4. Test Laravel bootstrap cơ bản
echo "\n4. Test Laravel bootstrap cơ bản...\n";
try {
    $app = require_once __DIR__ . '/bootstrap/app.php';
    echo "✅ Laravel application bootstrap thành công\n";
    
    // Test config service
    if ($app->bound('config')) {
        echo "✅ Config service đã được bound\n";
        $config = $app->make('config');
        echo "✅ Config service có thể được resolved\n";
    } else {
        echo "❌ Config service chưa được bound\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi Laravel bootstrap: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// 5. Kiểm tra config/app.php chi tiết
echo "\n5. Kiểm tra config/app.php...\n";
$configAppPath = __DIR__ . '/config/app.php';
if (file_exists($configAppPath)) {
    echo "✅ config/app.php tồn tại\n";
    
    try {
        $config = include $configAppPath;
        if (is_array($config) && isset($config['providers'])) {
            echo "✅ Config array hợp lệ với providers\n";
            
            $configProviderFound = false;
            foreach ($config['providers'] as $provider) {
                if (strpos($provider, 'ConfigServiceProvider') !== false) {
                    echo "✅ Tìm thấy ConfigServiceProvider: $provider\n";
                    $configProviderFound = true;
                    break;
                }
            }
            
            if (!$configProviderFound) {
                echo "❌ Không tìm thấy ConfigServiceProvider trong providers\n";
            }
        } else {
            echo "❌ Config array không hợp lệ\n";
        }
    } catch (Exception $e) {
        echo "❌ Lỗi đọc config/app.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ config/app.php không tồn tại\n";
}

// 6. Test HTTP request cơ bản
echo "\n6. Test HTTP request cơ bản...\n";
$testUrl = 'http://localhost/zenamanage/public/api/test';
echo "Test URL: $testUrl\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($testUrl, false, $context);
$headers = $http_response_header ?? [];

if ($response !== false) {
    echo "✅ HTTP request thành công\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
    
    foreach ($headers as $header) {
        if (strpos($header, 'HTTP/') === 0) {
            echo "Status: $header\n";
            break;
        }
    }
} else {
    echo "❌ HTTP request thất bại\n";
    if (!empty($headers)) {
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                echo "Status: $header\n";
                break;
            }
        }
    }
}

// 7. Restore composer.json
echo "\n7. Restore composer.json gốc...\n";
if (file_exists($composerBackupPath)) {
    copy($composerBackupPath, $composerJsonPath);
    unlink($composerBackupPath);
    echo "✅ Đã restore composer.json gốc\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Nếu Laravel bootstrap thành công nhưng HTTP request vẫn lỗi,\n";
echo "vấn đề có thể nằm ở package discovery hoặc service providers.\n";
?>