<?php

/**
 * Script sửa lỗi middleware extends Middleware
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI MIDDLEWARE EXTENDS MIDDLEWARE\n";
echo "=======================================\n\n";

$middlewareFiles = [
    'app/Http/Middleware/VerifyCsrfToken.php' => 'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
    'app/Http/Middleware/TrimStrings.php' => 'Illuminate\\Foundation\\Http\\Middleware\\TrimStrings',
    'app/Http/Middleware/Authenticate.php' => 'Illuminate\\Auth\\Middleware\\Authenticate',
    'app/Http/Middleware/TrustProxies.php' => 'Illuminate\\Http\\Middleware\\TrustProxies',
    'app/Http/Middleware/ValidateSignature.php' => 'Illuminate\\Routing\\Middleware\\ValidateSignature',
    'app/Http/Middleware/PreventRequestsDuringMaintenance.php' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance',
    'app/Http/Middleware/EncryptCookies.php' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
    'app/Http/Middleware/TrustHosts.php' => 'Illuminate\\Http\\Middleware\\TrustHosts'
];

$fixedFiles = 0;
$errors = 0;

foreach ($middlewareFiles as $filePath => $correctClass) {
    $fullPath = $basePath . '/' . $filePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$filePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Kiểm tra xem đã có import đúng chưa
    $importStatement = "use {$correctClass} as Middleware;";
    if (strpos($content, $importStatement) !== false) {
        echo "  ✅ Already fixed: {$filePath}\n";
        continue;
    }
    
    // Thêm import
    if (strpos($content, 'use Illuminate\\Http\\Request;') !== false) {
        $content = str_replace('use Illuminate\\Http\\Request;', "use Illuminate\\Http\\Request;\n{$importStatement}", $content);
    } elseif (strpos($content, 'use Illuminate\\Support\\Facades\\Auth;') !== false) {
        $content = str_replace('use Illuminate\\Support\\Facades\\Auth;', "use Illuminate\\Support\\Facades\\Auth;\n{$importStatement}", $content);
    } else {
        // Thêm sau namespace
        $content = preg_replace('/namespace App\\\\Http\\\\Middleware;(\s*)/', "namespace App\\Http\\Middleware;\n\n{$importStatement}\n", $content);
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "  ✅ Fixed: {$filePath}\n";
            $fixedFiles++;
        } else {
            echo "  ❌ Failed: {$filePath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No changes needed: {$filePath}\n";
    }
}

echo "\n📊 KẾT QUẢ:\n";
echo "===========\n";
echo "  ✅ Files fixed: {$fixedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành sửa lỗi middleware!\n";
