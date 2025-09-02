<?php declare(strict_types=1);

echo "=== SỬA LỖI AUTOLOADER COMPOSER NGHIÊM TRỌNG ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

$projectRoot = __DIR__;

// 1. Kiểm tra vendor directory
echo "1. Kiểm tra vendor directory...\n";
$vendorPath = $projectRoot . '/vendor';
if (!is_dir($vendorPath)) {
    echo "❌ Thư mục vendor không tồn tại\n";
    echo "Cần chạy: composer install\n\n";
} else {
    echo "✅ Thư mục vendor tồn tại\n";
    
    // Kiểm tra autoload.php
    $autoloadPath = $vendorPath . '/autoload.php';
    if (!file_exists($autoloadPath)) {
        echo "❌ File vendor/autoload.php không tồn tại\n";
    } else {
        echo "✅ File vendor/autoload.php tồn tại\n";
        
        // Kiểm tra composer/autoload_classmap.php
        $classmapPath = $vendorPath . '/composer/autoload_classmap.php';
        if (!file_exists($classmapPath)) {
            echo "❌ File vendor/composer/autoload_classmap.php không tồn tại\n";
        } else {
            echo "✅ File vendor/composer/autoload_classmap.php tồn tại\n";
        }
    }
}

// 2. Kiểm tra composer.json
echo "\n2. Kiểm tra composer.json...\n";
$composerJsonPath = $projectRoot . '/composer.json';
if (!file_exists($composerJsonPath)) {
    echo "❌ File composer.json không tồn tại\n";
} else {
    echo "✅ File composer.json tồn tại\n";
    $composerData = json_decode(file_get_contents($composerJsonPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ File composer.json có lỗi syntax JSON\n";
    } else {
        echo "✅ File composer.json hợp lệ\n";
        
        // Kiểm tra Laravel framework dependency
        if (isset($composerData['require']['laravel/framework'])) {
            echo "✅ Laravel framework được khai báo trong dependencies\n";
        } else {
            echo "❌ Laravel framework không được khai báo trong dependencies\n";
        }
    }
}

// 3. Kiểm tra composer.lock
echo "\n3. Kiểm tra composer.lock...\n";
$composerLockPath = $projectRoot . '/composer.lock';
if (!file_exists($composerLockPath)) {
    echo "❌ File composer.lock không tồn tại\n";
} else {
    echo "✅ File composer.lock tồn tại\n";
}

// 4. Test autoloader trực tiếp
echo "\n4. Test autoloader trực tiếp...\n";
try {
    if (file_exists($vendorPath . '/autoload.php')) {
        require_once $vendorPath . '/autoload.php';
        echo "✅ Autoloader được load thành công\n";
        
        // Test load một class Laravel cơ bản
        if (class_exists('Illuminate\\Foundation\\Application')) {
            echo "✅ Class Illuminate\\Foundation\\Application có thể được load\n";
        } else {
            echo "❌ Class Illuminate\\Foundation\\Application không thể được load\n";
        }
    } else {
        echo "❌ Không thể load autoloader\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi khi load autoloader: " . $e->getMessage() . "\n";
}

// 5. Các lệnh sửa chữa
echo "\n5. Thực hiện các lệnh sửa chữa...\n";

// Xóa vendor và reinstall
echo "Xóa thư mục vendor cũ...\n";
if (is_dir($vendorPath)) {
    $output = [];
    $returnVar = 0;
    exec('rm -rf ' . escapeshellarg($vendorPath), $output, $returnVar);
    if ($returnVar === 0) {
        echo "✅ Đã xóa thư mục vendor\n";
    } else {
        echo "❌ Không thể xóa thư mục vendor\n";
    }
}

// Chạy composer install
echo "\nChạy composer install...\n";
$output = [];
$returnVar = 0;
exec('cd ' . escapeshellarg($projectRoot) . ' && composer install --no-dev --optimize-autoloader 2>&1', $output, $returnVar);
if ($returnVar === 0) {
    echo "✅ Composer install thành công\n";
} else {
    echo "❌ Composer install thất bại\n";
    echo "Output: " . implode("\n", $output) . "\n";
}

// 6. Test lại sau khi sửa
echo "\n6. Test lại sau khi sửa...\n";
try {
    if (file_exists($vendorPath . '/autoload.php')) {
        // Clear any previous autoloader
        $autoloadFiles = get_included_files();
        foreach ($autoloadFiles as $file) {
            if (strpos($file, 'vendor/autoload.php') !== false) {
                // Cannot unload, but we can try to reload
                break;
            }
        }
        
        require_once $vendorPath . '/autoload.php';
        
        if (class_exists('Illuminate\\Foundation\\Application')) {
            echo "✅ Autoloader đã được sửa thành công\n";
            
            // Test bootstrap Laravel
            echo "\nTest bootstrap Laravel...\n";
            try {
                $app = require $projectRoot . '/bootstrap/app.php';
                echo "✅ Laravel bootstrap thành công\n";
            } catch (Exception $e) {
                echo "❌ Laravel bootstrap thất bại: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ Autoloader vẫn chưa hoạt động\n";
        }
    } else {
        echo "❌ Autoloader vẫn không tồn tại\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi khi test autoloader: " . $e->getMessage() . "\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Nếu vẫn có lỗi, hãy thử:\n";
echo "1. composer clear-cache\n";
echo "2. composer install --no-cache\n";
echo "3. Kiểm tra PHP version compatibility\n";