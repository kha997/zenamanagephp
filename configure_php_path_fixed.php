<?php declare(strict_types=1);

echo "=== Cấu hình PHP 8.2 Path và Test Laravel (Fixed) ===\n\n";

// 1. Kiểm tra các đường dẫn PHP có sẵn
echo "1. Kiểm tra các đường dẫn PHP có sẵn:\n";
$phpPaths = [
    '/opt/homebrew/bin/php',
    '/usr/local/bin/php', 
    '/Applications/XAMPP/xamppfiles/bin/php',
    '/usr/bin/php'
];

foreach ($phpPaths as $path) {
    if (file_exists($path)) {
        $version = shell_exec("$path -v 2>/dev/null");
        if ($version !== null) {
            // Lấy dòng đầu tiên bằng cách split theo newline
            $firstLine = explode("\n", trim($version))[0];
            echo "   ✅ $path: $firstLine\n";
        } else {
            echo "   ⚠️  $path: Tồn tại nhưng không thể chạy\n";
        }
    } else {
        echo "   ❌ $path: Không tồn tại\n";
    }
}

// 2. Tìm PHP 8.2 từ Homebrew
echo "\n2. Tìm PHP 8.2 từ Homebrew:\n";
$brewPhpPath = shell_exec('brew --prefix php@8.2 2>/dev/null');
if ($brewPhpPath !== null && trim($brewPhpPath) !== '') {
    $brewPhpPath = trim($brewPhpPath) . '/bin/php';
    if (file_exists($brewPhpPath)) {
        $version = shell_exec("$brewPhpPath -v 2>/dev/null");
        if ($version !== null) {
            $firstLine = explode("\n", trim($version))[0];
            echo "   ✅ Tìm thấy PHP 8.2: $brewPhpPath\n";
            echo "   📋 Version: $firstLine\n";
            
            // 3. Tạo symlink cho PHP 8.2
            echo "\n3. Tạo symlink cho PHP 8.2:\n";
            
            // Tạo thư mục nếu chưa tồn tại
            if (!is_dir('/usr/local/bin')) {
                echo "   📁 Tạo thư mục /usr/local/bin\n";
                shell_exec('sudo mkdir -p /usr/local/bin 2>&1');
            }
            if (!is_dir('/opt/homebrew/bin')) {
                echo "   📁 Tạo thư mục /opt/homebrew/bin\n";
                shell_exec('sudo mkdir -p /opt/homebrew/bin 2>&1');
            }
            
            $symlinkCommands = [
                "sudo ln -sf $brewPhpPath /usr/local/bin/php",
                "sudo ln -sf $brewPhpPath /opt/homebrew/bin/php"
            ];
            
            foreach ($symlinkCommands as $cmd) {
                echo "   🔗 Chạy: $cmd\n";
                $result = shell_exec("$cmd 2>&1");
                if ($result !== null && trim($result) !== '') {
                    echo "   📝 Kết quả: " . trim($result) . "\n";
                } else {
                    echo "   ✅ Thành công\n";
                }
            }
        } else {
            echo "   ❌ Không thể chạy PHP tại: $brewPhpPath\n";
        }
    } else {
        echo "   ❌ PHP binary không tồn tại tại: $brewPhpPath\n";
    }
} else {
    echo "   ❌ Không tìm thấy PHP 8.2 từ Homebrew\n";
    echo "   💡 Thử chạy: brew install php@8.2\n";
}

// 4. Cập nhật PATH trong shell profiles
echo "\n4. Cập nhật PATH trong shell profiles:\n";
$shellProfiles = [
    $_SERVER['HOME'] . '/.zshrc',
    $_SERVER['HOME'] . '/.bash_profile',
    $_SERVER['HOME'] . '/.bashrc'
];

$pathExport = 'export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"';

foreach ($shellProfiles as $profile) {
    if (file_exists($profile)) {
        $content = file_get_contents($profile);
        if ($content !== false && strpos($content, '/opt/homebrew/bin') === false) {
            file_put_contents($profile, "\n# Added by PHP upgrade script\n$pathExport\n", FILE_APPEND);
            echo "   ✅ Đã cập nhật PATH trong: $profile\n";
        } else {
            echo "   ℹ️  PATH đã tồn tại trong: $profile\n";
        }
    } else {
        echo "   ⚠️  File không tồn tại: $profile\n";
    }
}

// 5. Test PHP version sau khi cấu hình
echo "\n5. Test PHP version sau khi cấu hình:\n";
$testCommands = [
    'php -v',
    '/opt/homebrew/bin/php -v',
    '/usr/local/bin/php -v'
];

foreach ($testCommands as $cmd) {
    echo "   🧪 Test: $cmd\n";
    $result = shell_exec("$cmd 2>&1");
    if ($result !== null) {
        $firstLine = explode("\n", trim($result))[0];
        echo "   📋 Kết quả: $firstLine\n\n";
    } else {
        echo "   ❌ Lệnh thất bại\n\n";
    }
}

// 6. Test Composer với PHP mới
echo "6. Test Composer với PHP mới:\n";
echo "   🧪 Test: composer --version\n";
$composerResult = shell_exec('composer --version 2>&1');
if ($composerResult !== null) {
    echo "   📋 Kết quả: " . trim($composerResult) . "\n\n";
} else {
    echo "   ❌ Composer không hoạt động\n\n";
}

// 7. Kiểm tra PHP requirements
echo "7. Kiểm tra PHP requirements:\n";
$phpVersion = shell_exec('php -r "echo PHP_VERSION;" 2>&1');
if ($phpVersion !== null) {
    echo "   📋 PHP Version hiện tại: " . trim($phpVersion) . "\n";
    if (version_compare(trim($phpVersion), '8.2.0', '>=')) {
        echo "   ✅ PHP version đáp ứng yêu cầu Laravel (>= 8.2.0)\n";
        
        // 8. Reinstall Composer dependencies với PHP 8.2
        echo "\n8. Reinstall Composer dependencies với PHP 8.2:\n";
        echo "   🔄 Chạy: composer install --no-dev --optimize-autoloader\n";
        $installResult = shell_exec('composer install --no-dev --optimize-autoloader 2>&1');
        if ($installResult !== null) {
            // Chỉ hiển thị 10 dòng cuối để tránh quá dài
            $lines = explode("\n", trim($installResult));
            $lastLines = array_slice($lines, -10);
            echo "   📋 Kết quả (10 dòng cuối):\n";
            foreach ($lastLines as $line) {
                echo "   " . $line . "\n";
            }
        } else {
            echo "   ❌ Composer install thất bại\n";
        }
    } else {
        echo "   ❌ PHP version chưa đáp ứng yêu cầu Laravel (cần >= 8.2.0)\n";
        echo "   💡 Cần cấu hình lại PATH hoặc cài đặt PHP 8.2\n";
    }
} else {
    echo "   ❌ Không thể kiểm tra PHP version\n";
}

// 9. Clear Laravel cache và optimize (chỉ khi PHP >= 8.2)
if (isset($phpVersion) && $phpVersion !== null && version_compare(trim($phpVersion), '8.2.0', '>=')) {
    echo "\n9. Clear Laravel cache và optimize:\n";
    $laravelCommands = [
        'php artisan config:clear',
        'php artisan cache:clear', 
        'php artisan route:clear',
        'php artisan view:clear',
        'php artisan optimize:clear'
    ];

    foreach ($laravelCommands as $cmd) {
        echo "   🧹 Chạy: $cmd\n";
        $result = shell_exec("$cmd 2>&1");
        if ($result !== null && trim($result) !== '') {
            echo "   📋 Kết quả: " . trim($result) . "\n";
        } else {
            echo "   ✅ Thành công\n";
        }
    }

    // 10. Test Laravel cơ bản
    echo "\n10. Test Laravel cơ bản:\n";
    echo "   🧪 Test: php artisan --version\n";
    $artisanResult = shell_exec('php artisan --version 2>&1');
    if ($artisanResult !== null) {
        echo "   📋 Kết quả: " . trim($artisanResult) . "\n\n";
    } else {
        echo "   ❌ Artisan không hoạt động\n\n";
    }

    // 11. Test API endpoint
    echo "11. Test API endpoint:\n";
    echo "   🧪 Test: curl http://localhost/api/test\n";
    $apiResult = shell_exec('curl -s http://localhost/api/test 2>&1');
    if ($apiResult !== null) {
        echo "   📋 Kết quả API: " . trim($apiResult) . "\n\n";
    } else {
        echo "   ❌ API test thất bại\n\n";
    }
}

echo "=== Hoàn thành cấu hình PHP 8.2 ===\n";
echo "\n📝 Các bước tiếp theo:\n";
echo "1. Khởi động lại terminal hoặc chạy: source ~/.zshrc\n";
echo "2. Kiểm tra lại: php -v\n";
echo "3. Test API với: curl http://localhost/api/test\n";
echo "4. Nếu vẫn có lỗi, kiểm tra XAMPP có sử dụng PHP 8.2 không\n";
?>