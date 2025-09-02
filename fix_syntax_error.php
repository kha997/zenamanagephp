<?php declare(strict_types=1);

echo "🔧 Sửa lỗi cú pháp trong routes/api.php...\n\n";

try {
    $routesFile = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php';
    
    // Backup file
    $backupFile = $routesFile . '.backup_' . date('Y-m-d_H-i-s');
    copy($routesFile, $backupFile);
    echo "📁 Backup: $backupFile\n";
    
    // Đọc nội dung file
    $content = file_get_contents($routesFile);
    
    // Sửa lỗi cú pháp: loại bỏ dấu phẩy trước comment
    $content = str_replace(
        "'middleware' => ['auth:api', // 'rbac:project.view,projectId']",
        "'middleware' => ['auth:api'] // 'rbac:project.view,projectId'",
        $content
    );
    
    // Ghi lại file
    file_put_contents($routesFile, $content);
    
    echo "✅ Đã sửa lỗi cú pháp\n";
    echo "\n🔄 Clearing cache...\n";
    
    // Clear cache
    exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan route:clear 2>&1', $output, $return);
    if ($return === 0) {
        echo "✅ Route cache cleared\n";
    }
    
    echo "\n📋 Bây giờ bạn có thể chạy lại script test:\n";
    echo "php test_rbac_simple.php\n";
    
    echo "\n🗑️  Restore nếu cần: cp $backupFile $routesFile\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}

echo "\n✅ Hoàn thành!\n";