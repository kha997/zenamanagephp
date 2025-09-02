<?php declare(strict_types=1);

echo "=== TERMINATE BREW PROCESSES AND INSTALL PHP 8.2 ===\n";
echo "Thời gian bắt đầu: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Terminate các process brew đang chạy
echo "1. Terminate các process brew đang chạy...\n";
$brewProcesses = [
    67660, // brew.rb install php@8.2
    67661  // brew postinstall openldap
];

foreach ($brewProcesses as $pid) {
    echo "   - Terminate process PID $pid...\n";
    exec("kill -TERM $pid 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        echo "     ✓ Process $pid đã được terminate\n";
    } else {
        echo "     ⚠ Không thể terminate process $pid (có thể đã kết thúc)\n";
    }
}

// Chờ một chút để các process kết thúc hoàn toàn
echo "   - Chờ 3 giây để các process kết thúc hoàn toàn...\n";
sleep(3);

// 2. Kiểm tra xem còn process brew nào đang chạy không
echo "\n2. Kiểm tra process brew còn lại...\n";
exec("ps aux | grep brew | grep -v grep", $remainingProcesses);
if (empty($remainingProcesses)) {
    echo "   ✓ Không còn process brew nào đang chạy\n";
} else {
    echo "   ⚠ Vẫn còn process brew đang chạy:\n";
    foreach ($remainingProcesses as $process) {
        echo "     $process\n";
    }
}

// 3. Clear Homebrew locks
echo "\n3. Clear Homebrew locks...\n";
exec("brew cleanup --prune=all 2>&1", $cleanupOutput, $cleanupCode);
echo "   Cleanup output:\n";
foreach ($cleanupOutput as $line) {
    echo "   $line\n";
}

// 4. Thử cài đặt PHP 8.2 lại
echo "\n4. Cài đặt PHP 8.2...\n";
echo "   Chạy: brew install php@8.2\n";
exec("brew install php@8.2 2>&1", $installOutput, $installCode);

echo "   Install output:\n";
foreach ($installOutput as $line) {
    echo "   $line\n";
}

if ($installCode === 0) {
    echo "\n   ✓ PHP 8.2 đã được cài đặt thành công!\n";
    
    // 5. Link PHP 8.2
    echo "\n5. Link PHP 8.2...\n";
    exec("brew link php@8.2 --force --overwrite 2>&1", $linkOutput, $linkCode);
    foreach ($linkOutput as $line) {
        echo "   $line\n";
    }
    
    // 6. Cập nhật PATH
    echo "\n6. Cập nhật PATH...\n";
    $phpPath = '/opt/homebrew/bin:/opt/homebrew/sbin';
    $currentPath = getenv('PATH');
    
    if (strpos($currentPath, '/opt/homebrew/bin') === false) {
        echo "   Thêm Homebrew path vào PATH...\n";
        putenv("PATH=$phpPath:$currentPath");
        echo "   ✓ PATH đã được cập nhật\n";
    } else {
        echo "   ✓ Homebrew path đã có trong PATH\n";
    }
    
    // 7. Kiểm tra phiên bản PHP mới
    echo "\n7. Kiểm tra phiên bản PHP...\n";
    exec("/opt/homebrew/bin/php --version 2>&1", $versionOutput);
    foreach ($versionOutput as $line) {
        echo "   $line\n";
    }
    
    echo "\n=== HƯỚNG DẪN TIẾP THEO ===\n";
    echo "1. Khởi động lại terminal hoặc chạy:\n";
    echo "   export PATH=/opt/homebrew/bin:/opt/homebrew/sbin:\$PATH\n";
    echo "\n2. Kiểm tra PHP version:\n";
    echo "   php --version\n";
    echo "\n3. Nếu PHP 8.2+ đã active, chạy:\n";
    echo "   cd /path/to/your/laravel/project\n";
    echo "   composer install\n";
    echo "   php artisan optimize:clear\n";
    echo "   php artisan config:cache\n";
    
} else {
    echo "\n   ✗ Lỗi khi cài đặt PHP 8.2 (Exit code: $installCode)\n";
    echo "\n=== GIẢI PHÁP THAY THẾ ===\n";
    echo "1. Thử cài đặt thủ công:\n";
    echo "   brew cleanup\n";
    echo "   brew update\n";
    echo "   brew install php@8.2\n";
    echo "\n2. Hoặc sử dụng phpenv/phpbrew để quản lý multiple PHP versions\n";
    echo "\n3. Hoặc hạ cấp Laravel xuống version 9.x tương thích với PHP 8.0\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Thời gian kết thúc: " . date('Y-m-d H:i:s') . "\n";
?>