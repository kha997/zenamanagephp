<?php declare(strict_types=1);

/**
 * Script để sửa config/auth.php - khắc phục lỗi AuthManager callable
 * 
 * Vấn đề: Default guard được đặt thành 'api' gây xung đột với AuthManager
 * Giải pháp: Đặt lại default guard thành 'web' và đảm bảo guard 'web' được định nghĩa
 */

echo "🔧 Đang sửa config/auth.php để khắc phục lỗi AuthManager callable...\n";

$configPath = __DIR__ . '/config/auth.php';

if (!file_exists($configPath)) {
    echo "❌ Không tìm thấy file config/auth.php\n";
    exit(1);
}

// Backup file gốc
$backupPath = $configPath . '.backup.' . date('Y-m-d_H-i-s');
copy($configPath, $backupPath);
echo "📋 Đã backup file gốc: $backupPath\n";

// Đọc nội dung file
$content = file_get_contents($configPath);

// 1. Thay đổi default guard từ 'api' về 'web'
$content = preg_replace(
    "/('defaults'\s*=>\s*\[\s*'guard'\s*=>\s*)'api'/",
    "$1'web'",
    $content
);

// 2. Đảm bảo guard 'web' được định nghĩa (thêm nếu chưa có)
if (!preg_match("/'web'\s*=>\s*\[/", $content)) {
    // Tìm vị trí guards array và thêm web guard
    $webGuardDefinition = "        'web' => [\n" .
                         "            'driver' => 'session',\n" .
                         "            'provider' => 'users',\n" .
                         "        ],\n\n";
    
    $content = preg_replace(
        "/(('guards'\s*=>\s*\[)\s*)/",
        "$1\n$webGuardDefinition",
        $content
    );
}

// 3. Đảm bảo provider 'users' được định nghĩa (thêm nếu chưa có)
if (!preg_match("/'users'\s*=>\s*\[/", $content)) {
    $usersProviderDefinition = "        'users' => [\n" .
                              "            'driver' => 'eloquent',\n" .
                              "            'model' => App\\Models\\User::class,\n" .
                              "        ],\n\n";
    
    $content = preg_replace(
        "/(('providers'\s*=>\s*\[)\s*)/",
        "$1\n$usersProviderDefinition",
        $content
    );
}

// Ghi lại file
file_put_contents($configPath, $content);

echo "✅ Đã sửa config/auth.php thành công:\n";
echo "   - Default guard: 'api' → 'web'\n";
echo "   - Đảm bảo guard 'web' được định nghĩa\n";
echo "   - Đảm bảo provider 'users' được định nghĩa\n";
echo "\n🔄 Tiếp theo, hãy chạy:\n";
echo "   php artisan config:clear\n";
echo "   php artisan optimize:clear\n";
echo "\n🧪 Sau đó test lại API để xác minh lỗi đã được khắc phục.\n";