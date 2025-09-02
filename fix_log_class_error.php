<?php declare(strict_types=1);

echo "=== FIX LOG CLASS ERROR SCRIPT ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Kiểm tra config/app.php có alias Log không
echo "1. Kiểm tra Log alias trong config/app.php...\n";
$configPath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/config/app.php';

if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    
    if (strpos($configContent, "'Log' => Illuminate\\Support\\Facades\\Log::class") !== false) {
        echo "✅ Log alias đã tồn tại trong config/app.php\n";
    } else {
        echo "❌ Log alias KHÔNG tồn tại, đang thêm...\n";
        
        // Tìm vị trí aliases array và thêm Log alias
        $pattern = "/(\s*'aliases'\s*=>\s*\[.*?)(\s*\],)/s";
        
        if (preg_match($pattern, $configContent, $matches)) {
            // Kiểm tra xem đã có Log alias chưa
            if (strpos($matches[1], "'Log'") === false) {
                $newAlias = "\n        'Log' => Illuminate\\Support\\Facades\\Log::class,";
                $replacement = $matches[1] . $newAlias . $matches[2];
                $configContent = preg_replace($pattern, $replacement, $configContent);
                
                file_put_contents($configPath, $configContent);
                echo "✅ Đã thêm Log alias vào config/app.php\n";
            }
        } else {
            echo "❌ Không tìm thấy aliases array trong config/app.php\n";
        }
    }
} else {
    echo "❌ Không tìm thấy file config/app.php\n";
}

echo "\n2. Kiểm tra bootstrap/app.php...\n";
$bootstrapPath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/bootstrap/app.php';

if (file_exists($bootstrapPath)) {
    $bootstrapContent = file_get_contents($bootstrapPath);
    echo "✅ File bootstrap/app.php tồn tại\n";
    
    // Kiểm tra có return $app không
    if (strpos($bootstrapContent, 'return $app') !== false) {
        echo "✅ bootstrap/app.php có return \$app\n";
    } else {
        echo "❌ bootstrap/app.php THIẾU return \$app\n";
    }
} else {
    echo "❌ Không tìm thấy file bootstrap/app.php\n";
}

echo "\n3. Clear tất cả cache và optimize...\n";

// Chạy các lệnh artisan để clear cache
$commands = [
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan config:clear',
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan cache:clear', 
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan route:clear',
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan view:clear',
    'cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && composer dump-autoload'
];

foreach ($commands as $cmd) {
    echo "Chạy: $cmd\n";
    $output = shell_exec($cmd . ' 2>&1');
    echo "Kết quả: " . trim($output) . "\n\n";
}

echo "4. Test lại route cơ bản...\n";

// Test route /api/test
$testUrl = 'http://localhost/zenamanage/public/api/test';
echo "Testing: $testUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    echo "✅ Route /api/test hoạt động bình thường\n";
} else {
    echo "❌ Route /api/test vẫn lỗi\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Vui lòng chạy lại test curl để kiểm tra:\n";
echo "curl -v \"http://localhost/zenamanage/public/api/test\"\n";
?>