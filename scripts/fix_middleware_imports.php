<?php

/**
 * Script sửa lỗi middleware thiếu import Request
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔧 SỬA LỖI MIDDLEWARE THIẾU IMPORT REQUEST\n";
echo "========================================\n\n";

$middlewareFiles = [
    'app/Http/Middleware/TenantIsolationMiddleware.php',
    'app/Http/Middleware/CheckProjectPermission.php',
    'app/Http/Middleware/ProjectStatusMiddleware.php',
    'app/Http/Middleware/ComponentAccessMiddleware.php',
    'app/Http/Middleware/InvitationAuth.php',
    'app/Http/Middleware/CacheMiddleware.php',
    'app/Http/Middleware/ProductionSecurityMiddleware.php',
    'app/Http/Middleware/AdvancedRateLimitMiddleware.php',
    'app/Http/Middleware/RedirectIfAuthenticated.php',
    'app/Http/Middleware/InputSanitizationMiddleware.php',
    'app/Http/Middleware/ProjectOwnershipMiddleware.php',
    'app/Http/Middleware/SecurityHeadersMiddleware.php',
    'app/Http/Middleware/ProjectAccessMiddleware.php',
    'app/Http/Middleware/RolePermission.php',
    'app/Http/Middleware/APIRateLimitMiddleware.php',
    'app/Http/Middleware/RBACMiddleware.php',
    'app/Http/Middleware/PerformanceMonitoringMiddleware.php',
    'app/Http/Middleware/MetricsMiddleware.php',
    'app/Http/Middleware/TaskAccessMiddleware.php',
    'app/Http/Middleware/SimpleJwtAuth.php',
    'app/Http/Middleware/EnhancedRateLimitMiddleware.php'
];

$fixedFiles = 0;
$errors = 0;

foreach ($middlewareFiles as $filePath) {
    $fullPath = $basePath . '/' . $filePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$filePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Kiểm tra xem đã có import Request chưa
    if (strpos($content, 'use Illuminate\Http\Request;') !== false) {
        echo "  ✅ Already fixed: {$filePath}\n";
        continue;
    }
    
    // Thêm import Request
    if (strpos($content, 'use Closure;') !== false) {
        $content = str_replace('use Closure;', "use Closure;\nuse Illuminate\\Http\\Request;", $content);
    } elseif (strpos($content, 'use Illuminate\\Http\\Response;') !== false) {
        $content = str_replace('use Illuminate\\Http\\Response;', "use Illuminate\\Http\\Request;\nuse Illuminate\\Http\\Response;", $content);
    } else {
        // Thêm sau namespace
        $content = preg_replace('/namespace App\\\\Http\\\\Middleware;(\s*)/', "namespace App\\Http\\Middleware;\n\nuse Illuminate\\Http\\Request;\n", $content);
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
