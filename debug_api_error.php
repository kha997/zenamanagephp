<?php declare(strict_types=1);

// Script để debug lỗi API HTTP 500
echo "=== DEBUG API ERROR ===\n";

// 1. Kiểm tra cú pháp routes/api.php
echo "1. Checking routes/api.php syntax...\n";
$output = [];
$return_code = 0;
exec('php -l /Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php 2>&1', $output, $return_code);

if ($return_code === 0) {
    echo "✅ routes/api.php syntax is OK\n";
} else {
    echo "❌ routes/api.php has syntax errors:\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
}

// 2. Restore routes/api.php từ backup nếu có lỗi
if ($return_code !== 0) {
    echo "\n2. Restoring routes/api.php from backup...\n";
    if (file_exists('/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php.backup')) {
        copy('/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php.backup', '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/routes/api.php');
        echo "✅ Restored routes/api.php from backup\n";
        
        // Clear cache
        echo "Clearing route cache...\n";
        exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan route:clear 2>&1');
        exec('cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage && php artisan config:clear 2>&1');
        echo "✅ Cache cleared\n";
    } else {
        echo "❌ No backup file found\n";
    }
}

// 3. Test basic Laravel bootstrap
echo "\n3. Testing Laravel bootstrap...\n";
try {
    require_once '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/vendor/autoload.php';
    $app = require_once '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/bootstrap/app.php';
    echo "✅ Laravel bootstrap successful\n";
} catch (Exception $e) {
    echo "❌ Laravel bootstrap failed: " . $e->getMessage() . "\n";
}

// 4. Test simple HTTP request
echo "\n4. Testing simple HTTP request...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/zenamanage/public/api/v1/test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($response) {
    $parts = explode("\r\n\r\n", $response, 2);
    $headers = $parts[0];
    $body = isset($parts[1]) ? $parts[1] : '';
    
    echo "Headers:\n$headers\n";
    echo "Body length: " . strlen($body) . "\n";
    if (strlen($body) > 0) {
        echo "Body preview: " . substr($body, 0, 200) . "\n";
    }
} else {
    echo "❌ No response received\n";
}

echo "\n=== DEBUG COMPLETE ===\n";