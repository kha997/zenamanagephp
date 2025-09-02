<?php declare(strict_types=1);

echo "=== DEBUG HTTP 500 DETAILED ===\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Test Laravel application trong CLI context
echo "1. Test Laravel application trong CLI context...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    
    echo "✅ Laravel app loaded thành công\n";
    
    // Test từng service provider quan trọng
    $criticalProviders = [
        'config' => 'Illuminate\\Config\\ConfigServiceProvider',
        'log' => 'Illuminate\\Log\\LogServiceProvider',
        'router' => 'Illuminate\\Routing\\RoutingServiceProvider',
        'auth' => 'Illuminate\\Auth\\AuthServiceProvider'
    ];
    
    foreach ($criticalProviders as $service => $providerClass) {
        if ($app->bound($service)) {
            echo "✅ Service '$service' đã được bound\n";
            try {
                $instance = $app->make($service);
                echo "✅ Service '$service' có thể được resolved\n";
            } catch (Exception $e) {
                echo "❌ Lỗi resolve service '$service': " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ Service '$service' chưa được bound\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi load Laravel app: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// 2. Test HTTP kernel và middleware
echo "\n2. Test HTTP kernel và middleware...\n";
try {
    $kernel = $app->make('Illuminate\\Contracts\\Http\\Kernel');
    echo "✅ HTTP Kernel loaded thành công\n";
    
    // Test tạo request cơ bản
    $request = \Illuminate\Http\Request::create('/api/test', 'GET');
    echo "✅ Request object tạo thành công\n";
    
    // Test middleware stack
    $middlewareGroups = $kernel->getMiddlewareGroups();
    echo "✅ Middleware groups: " . implode(', ', array_keys($middlewareGroups)) . "\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi HTTP kernel: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// 3. Test route resolution
echo "\n3. Test route resolution...\n";
try {
    $router = $app->make('router');
    echo "✅ Router loaded thành công\n";
    
    // Load routes
    require_once __DIR__ . '/routes/api.php';
    echo "✅ API routes loaded\n";
    
    $routes = $router->getRoutes();
    echo "✅ Tổng số routes: " . $routes->count() . "\n";
    
    // Tìm route /api/test
    $testRoute = $routes->match($request);
    if ($testRoute) {
        echo "✅ Route /api/test được tìm thấy\n";
        echo "Action: " . $testRoute->getActionName() . "\n";
    } else {
        echo "❌ Route /api/test không được tìm thấy\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi route resolution: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// 4. Test xử lý request hoàn chỉnh
echo "\n4. Test xử lý request hoàn chỉnh...\n";
try {
    // Tạo request mới
    $request = \Illuminate\Http\Request::create('/api/test', 'GET');
    
    // Xử lý request qua kernel
    $response = $kernel->handle($request);
    
    echo "✅ Request được xử lý thành công\n";
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content: " . substr($response->getContent(), 0, 200) . "\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi xử lý request: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// 5. Test cấu hình logging
echo "\n5. Test cấu hình logging...\n";
try {
    $logConfig = config('logging');
    if ($logConfig) {
        echo "✅ Logging config loaded\n";
        echo "Default channel: " . ($logConfig['default'] ?? 'không xác định') . "\n";
        
        // Test write log
        \Illuminate\Support\Facades\Log::info('Test log message from debug script');
        echo "✅ Log message written thành công\n";
    } else {
        echo "❌ Logging config không load được\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi logging: " . $e->getMessage() . "\n";
}

// 6. Kiểm tra error logs
echo "\n6. Kiểm tra error logs...\n";
$logPath = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logPath)) {
    echo "✅ Log file tồn tại\n";
    
    // Đọc 20 dòng cuối của log
    $logContent = file($logPath);
    $recentLogs = array_slice($logContent, -20);
    
    echo "20 dòng log gần nhất:\n";
    foreach ($recentLogs as $line) {
        echo $line;
    }
} else {
    echo "❌ Log file không tồn tại\n";
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Nếu vẫn có lỗi, hãy kiểm tra chi tiết trong error logs.\n";
?>