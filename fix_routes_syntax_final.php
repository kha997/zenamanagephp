<?php declare(strict_types=1);

echo "🔧 Sửa lỗi cú pháp trong routes/api.php...\n\n";

$routesFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php';
$backupFile = $routesFile . '.backup_' . date('Y-m-d_H-i-s');

// Backup file gốc
copy($routesFile, $backupFile);
echo "📁 Backup: $backupFile\n";

// Đọc nội dung file
$content = file_get_contents($routesFile);

// Sửa lỗi cú pháp: thêm dấu đóng ngoặc vuông cho mảng middleware
$content = str_replace(
    "'middleware' => ['auth:api'] // 'rbac:project.view,projectId', function () {",
    "'middleware' => ['auth:api']], function () { // 'rbac:project.view,projectId'",
    $content
);

// Ghi lại file
file_put_contents($routesFile, $content);
echo "✅ Đã sửa lỗi cú pháp\n\n";

// Clear cache
echo "🔄 Clearing cache...\n";
exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan route:clear 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Route cache cleared\n";
} else {
    echo "⚠️  Route cache clear warning: " . implode("\n", $output) . "\n";
}

exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan config:clear 2>&1', $output2, $returnCode2);
if ($returnCode2 === 0) {
    echo "✅ Config cache cleared\n";
} else {
    echo "⚠️  Config cache clear warning: " . implode("\n", $output2) . "\n";
}

echo "\n🗑️  Restore nếu cần: cp $backupFile $routesFile\n";
echo "✅ Hoàn thành!\n";