<?php
declare(strict_types=1);

echo "=== KHẮC PHỤC VENDOR BỊ HỎNG - GIẢI PHÁP B ===\n";
echo "Thực hiện: Xóa vendor + composer.lock, clear cache, cài lại sạch\n\n";

// Bước 1: Xóa hoàn toàn vendor và composer.lock
echo "[1] Xóa thư mục vendor và composer.lock...\n";
if (is_dir('vendor')) {
    exec('rm -rf vendor', $output1, $return1);
    echo "   - Đã xóa thư mục vendor: " . ($return1 === 0 ? "✓" : "✗") . "\n";
} else {
    echo "   - Thư mục vendor không tồn tại\n";
}

if (file_exists('composer.lock')) {
    unlink('composer.lock');
    echo "   - Đã xóa composer.lock: ✓\n";
} else {
    echo "   - File composer.lock không tồn tại\n";
}

// Bước 2: Clear cache Composer
echo "\n[2] Clear cache Composer...\n";
exec('composer clear-cache 2>&1', $output2, $return2);
echo "   - Clear cache: " . ($return2 === 0 ? "✓" : "✗") . "\n";
if ($return2 !== 0) {
    echo "   Output: " . implode("\n   ", $output2) . "\n";
}

// Bước 3: Cài đặt lại dependencies (KHÔNG dùng --no-scripts/--no-plugins)
echo "\n[3] Cài đặt lại tất cả dependencies...\n";
echo "   Chạy: composer install -o\n";
exec('composer install -o 2>&1', $output3, $return3);
echo "   - Install: " . ($return3 === 0 ? "✓" : "✗") . "\n";

// Hiển thị output chi tiết
if (!empty($output3)) {
    echo "\n   === OUTPUT COMPOSER INSTALL ===\n";
    foreach ($output3 as $line) {
        echo "   $line\n";
    }
}

// Bước 4: Kiểm tra phpunit/php-timer có tồn tại không
echo "\n[4] Kiểm tra phpunit/php-timer...\n";
$phpunitTimerPath = 'vendor/phpunit/php-timer/src';
if (is_dir($phpunitTimerPath)) {
    echo "   - Thư mục phpunit/php-timer/src: ✓ Tồn tại\n";
    $files = scandir($phpunitTimerPath);
    echo "   - Số file trong src: " . (count($files) - 2) . "\n";
} else {
    echo "   - Thư mục phpunit/php-timer/src: ✗ KHÔNG tồn tại\n";
}

// Bước 5: Test composer dump-autoload
echo "\n[5] Test composer dump-autoload...\n";
exec('composer dump-autoload -o 2>&1', $output4, $return4);
echo "   - Dump autoload: " . ($return4 === 0 ? "✓" : "✗") . "\n";
if ($return4 !== 0) {
    echo "   Lỗi dump-autoload:\n";
    foreach ($output4 as $line) {
        echo "   $line\n";
    }
} else {
    // Tìm số classes được load
    foreach ($output4 as $line) {
        if (strpos($line, 'classes') !== false) {
            echo "   $line\n";
        }
    }
}

echo "\n=== KẾT QUẢ ===\n";
if ($return3 === 0 && $return4 === 0 && is_dir($phpunitTimerPath)) {
    echo "✓ THÀNH CÔNG: Vendor đã được cài đặt lại hoàn toàn\n";
    echo "✓ phpunit/php-timer đã tồn tại\n";
    echo "✓ Autoloader hoạt động bình thường\n";
    echo "\n=== BƯỚC TIẾP THEO ===\n";
    echo "1. Chạy: php artisan --version\n";
    echo "2. Chạy: php artisan optimize:clear\n";
    echo "3. Test API: curl http://localhost/api/test\n";
} else {
    echo "✗ VẪN CÒN VẤN ĐỀ - Cần điều tra thêm\n";
    if (!is_dir($phpunitTimerPath)) {
        echo "  - phpunit/php-timer vẫn thiếu\n";
    }
    if ($return3 !== 0) {
        echo "  - Composer install thất bại\n";
    }
    if ($return4 !== 0) {
        echo "  - Dump autoload thất bại\n";
    }
}

echo "\n=== HOÀN THÀNH ===\n";
?>