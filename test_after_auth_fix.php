<?php declare(strict_types=1);

/**
 * Script để clear cache và test API sau khi sửa config/auth.php
 */

echo "🔄 Clearing Laravel cache sau khi sửa config/auth.php...\n";

// Clear config cache
echo "📋 Clearing config cache...\n";
exec('php artisan config:clear 2>&1', $output1, $return1);
if ($return1 === 0) {
    echo "✅ Config cache cleared successfully\n";
} else {
    echo "❌ Failed to clear config cache: " . implode("\n", $output1) . "\n";
}

// Clear all optimization cache
echo "🧹 Clearing optimization cache...\n";
exec('php artisan optimize:clear 2>&1', $output2, $return2);
if ($return2 === 0) {
    echo "✅ Optimization cache cleared successfully\n";
} else {
    echo "❌ Failed to clear optimization cache: " . implode("\n", $output2) . "\n";
}

echo "\n🧪 Testing API endpoint sau khi sửa default guard...\n";

// Test API endpoint
$testUrl = 'http://localhost/zenamanage/api/v1/user';
$headers = [
    'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3plbmFtYW5hZ2UvYXBpL3YxL2F1dGgvbG9naW4iLCJpYXQiOjE3NTY2NTU4NzQsImV4cCI6MTc1NjY1OTQ3NCwibmJmIjoxNzU2NjU1ODc0LCJqdGkiOiJhVGNOZGNGVGNqVGNOZGNGIiwic3ViIjoiMSIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjcifQ.abc123',
    'Content-Type: application/json',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: $error\n";
} else {
    echo "📡 HTTP Status: $httpCode\n";
    echo "📄 Response: $response\n";
    
    if ($httpCode === 200) {
        echo "\n✅ API test thành công! Lỗi AuthManager callable đã được khắc phục.\n";
    } else {
        echo "\n⚠️  API vẫn có vấn đề. Cần kiểm tra thêm.\n";
    }
}

echo "\n📋 Tóm tắt các thay đổi đã thực hiện:\n";
echo "   ✅ Default guard: 'api' → 'web' trong config/auth.php\n";
echo "   ✅ Tất cả $request->user() đã được sửa thành $request->user('api')\n";
echo "   ✅ Auth facade đã được sửa thành Auth::guard('api')\n";
echo "   ✅ Config cache đã được cleared\n";
echo "\n🔄 Tiếp theo: Kích hoạt lại rbac middleware nếu API hoạt động bình thường.\n";