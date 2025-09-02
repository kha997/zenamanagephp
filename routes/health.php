<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\JsonResponse;

/**
 * Health check endpoint cho monitoring và load balancer
 * Kiểm tra tình trạng của các services quan trọng
 */
Route::get('/health', function (): JsonResponse {
    $checks = [];
    $overallStatus = 'healthy';
    
    // Kiểm tra database connection
    try {
        DB::connection()->getPdo();
        $checks['database'] = [
            'status' => 'healthy',
            'message' => 'Database connection successful'
        ];
    } catch (Exception $e) {
        $checks['database'] = [
            'status' => 'unhealthy',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
        $overallStatus = 'unhealthy';
    }
    
    // Kiểm tra Redis connection
    try {
        Redis::ping();
        $checks['redis'] = [
            'status' => 'healthy',
            'message' => 'Redis connection successful'
        ];
    } catch (Exception $e) {
        $checks['redis'] = [
            'status' => 'unhealthy',
            'message' => 'Redis connection failed: ' . $e->getMessage()
        ];
        $overallStatus = 'unhealthy';
    }
    
    // Kiểm tra storage permissions
    $storageWritable = is_writable(storage_path());
    $checks['storage'] = [
        'status' => $storageWritable ? 'healthy' : 'unhealthy',
        'message' => $storageWritable ? 'Storage is writable' : 'Storage is not writable'
    ];
    
    if (!$storageWritable) {
        $overallStatus = 'unhealthy';
    }
    
    // Kiểm tra WebSocket server
    try {
        $websocketHost = env('WEBSOCKET_HOST', 'localhost');
        $websocketPort = env('WEBSOCKET_PORT', 8080);
        
        $connection = @fsockopen($websocketHost, $websocketPort, $errno, $errstr, 5);
        if ($connection) {
            fclose($connection);
            $checks['websocket'] = [
                'status' => 'healthy',
                'message' => 'WebSocket server is running'
            ];
        } else {
            $checks['websocket'] = [
                'status' => 'unhealthy',
                'message' => 'WebSocket server is not accessible'
            ];
            $overallStatus = 'degraded'; // WebSocket không critical
        }
    } catch (Exception $e) {
        $checks['websocket'] = [
            'status' => 'unhealthy',
            'message' => 'WebSocket check failed: ' . $e->getMessage()
        ];
        $overallStatus = 'degraded';
    }
    
    $response = [
        'status' => $overallStatus,
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'checks' => $checks
    ];
    
    $httpStatus = match($overallStatus) {
        'healthy' => 200,
        'degraded' => 200, // Vẫn có thể phục vụ
        'unhealthy' => 503
    };
    
    return response()->json($response, $httpStatus);
});

/**
 * Readiness check - kiểm tra xem app đã sẵn sàng nhận traffic chưa
 */
Route::get('/ready', function (): JsonResponse {
    // Kiểm tra các điều kiện cần thiết để app có thể hoạt động
    $ready = true;
    $checks = [];
    
    // Kiểm tra database migrations
    try {
        // Kiểm tra bảng migrations tồn tại
        $migrationTable = DB::getSchemaBuilder()->hasTable('migrations');
        if (!$migrationTable) {
            $ready = false;
            $checks['migrations'] = 'Database not migrated';
        } else {
            $checks['migrations'] = 'Database migrated';
        }
    } catch (Exception $e) {
        $ready = false;
        $checks['migrations'] = 'Migration check failed: ' . $e->getMessage();
    }
    
    // Kiểm tra app key
    if (empty(config('app.key'))) {
        $ready = false;
        $checks['app_key'] = 'Application key not set';
    } else {
        $checks['app_key'] = 'Application key configured';
    }
    
    return response()->json([
        'ready' => $ready,
        'checks' => $checks,
        'timestamp' => now()->toISOString()
    ], $ready ? 200 : 503);
});