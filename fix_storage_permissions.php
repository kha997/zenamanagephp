<?php declare(strict_types=1);

/**
 * Script để sửa quyền truy cập cho thư mục storage và bootstrap/cache
 * Giải quyết lỗi "Permission denied" khi Laravel cố ghi file
 */

echo "=== Sửa quyền truy cập cho Laravel ===\n";

$projectRoot = __DIR__;
$storagePath = $projectRoot . '/storage';
$bootstrapCachePath = $projectRoot . '/bootstrap/cache';

// Kiểm tra thư mục tồn tại
if (!is_dir($storagePath)) {
    echo "❌ Thư mục storage không tồn tại: {$storagePath}\n";
    exit(1);
}

if (!is_dir($bootstrapCachePath)) {
    echo "❌ Thư mục bootstrap/cache không tồn tại: {$bootstrapCachePath}\n";
    exit(1);
}

echo "📁 Đang sửa quyền cho thư mục storage...\n";

// Sửa quyền cho storage (775 = rwxrwxr-x)
if (chmod($storagePath, 0775)) {
    echo "✅ Đã sửa quyền cho: {$storagePath}\n";
} else {
    echo "❌ Không thể sửa quyền cho: {$storagePath}\n";
}

// Sửa quyền đệ quy cho tất cả file/folder trong storage
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($storagePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isDir()) {
        chmod($item->getRealPath(), 0775);
    } else {
        chmod($item->getRealPath(), 0664);
    }
}

echo "📁 Đang sửa quyền cho thư mục bootstrap/cache...\n";

// Sửa quyền cho bootstrap/cache
if (chmod($bootstrapCachePath, 0775)) {
    echo "✅ Đã sửa quyền cho: {$bootstrapCachePath}\n";
} else {
    echo "❌ Không thể sửa quyền cho: {$bootstrapCachePath}\n";
}

// Sửa quyền đệ quy cho bootstrap/cache
if (is_dir($bootstrapCachePath)) {
    $cacheIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bootstrapCachePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($cacheIterator as $item) {
        if ($item->isDir()) {
            chmod($item->getRealPath(), 0775);
        } else {
            chmod($item->getRealPath(), 0664);
        }
    }
}

echo "\n🎉 Hoàn thành sửa quyền truy cập!\n";
echo "📋 Quyền đã được thiết lập:\n";
echo "   - Thư mục: 775 (rwxrwxr-x)\n";
echo "   - File: 664 (rw-rw-r--)\n";
echo "\n🔄 Hãy test lại API ngay bây giờ!\n";