<?php declare(strict_types=1);

/**
 * Script để tạm thời comment middleware rbac trong routes/api.php
 * Mục đích: Kiểm tra xem lỗi 'Target class [rbac] does not exist' có biến mất không
 */

$routeFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php';
$backupFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php.backup';

// Tạo backup trước khi sửa
if (!file_exists($backupFile)) {
    copy($routeFile, $backupFile);
    echo "✓ Đã tạo backup file: $backupFile\n";
}

// Đọc nội dung file
$content = file_get_contents($routeFile);

// Thay thế 'rbac:' thành '// rbac:' để comment middleware
$modifiedContent = str_replace("'rbac:", "'// rbac:", $content);

// Ghi lại file
file_put_contents($routeFile, $modifiedContent);

echo "✓ Đã comment tất cả middleware rbac trong routes/api.php\n";
echo "✓ File backup được lưu tại: $backupFile\n";
echo "\nĐể khôi phục lại, chạy lệnh:\n";
echo "cp $backupFile $routeFile\n";