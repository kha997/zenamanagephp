<?php declare(strict_types=1);

echo "🧪 Test RBAC middleware từng bước...\n\n";

$routesFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php';
$backupFile = $routesFile . '.backup_' . date('Y-m-d_H-i-s');

// Backup file gốc
copy($routesFile, $backupFile);
echo "📁 Backup: $backupFile\n";

// Đọc nội dung file
$content = file_get_contents($routesFile);

// Thêm một route test đơn giản với RBAC middleware
$testRoute = "\n\n// Test RBAC middleware\nRoute::get('/rbac-test', function () {\n    return response()->json([\n        'status' => 'success',\n        'message' => 'RBAC middleware hoạt động tốt!',\n        'user' => request()->user('api'),\n        'timestamp' => now()\n    ]);\n})->middleware(['auth:api', 'rbac:project.view']);\n";

// Thêm route test vào cuối file (trước dấu đóng ngoặc cuối cùng nếu có)
$content = rtrim($content);
if (substr($content, -2) === '});') {
    $content = substr($content, 0, -2) . $testRoute . '});';
} else {
    $content .= $testRoute;
}

// Ghi lại file
file_put_contents($routesFile, $content);
echo "✅ Đã thêm route test RBAC: /api/v1/rbac-test\n\n";

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

echo "\n📋 Test với:\n";
echo "GET /api/v1/rbac-test\n";
echo "Header: Authorization: Bearer YOUR_JWT_TOKEN\n\n";

echo "🔍 Kiểm tra logs nếu có lỗi:\n";
echo "tail -f /Applications/XAMPP/xamppfiles/htdocs/zenamanage/storage/logs/laravel.log\n\n";

echo "🗑️  Restore nếu cần: cp $backupFile $routesFile\n";
echo "✅ Hoàn thành!\n";