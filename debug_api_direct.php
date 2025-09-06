<?php declare(strict_types=1);

// Script debug trực tiếp API endpoint
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Bootstrap Laravel application
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    // Tạo request giả lập
    $request = Illuminate\Http\Request::create(
        '/api/v1/auth/login',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode([
            'email' => 'admin@zena.local',
            'password' => 'password'
        ])
    );
    
    echo "=== DEBUG API DIRECT TEST ===\n";
    echo "Request URL: " . $request->getUri() . "\n";
    echo "Request Method: " . $request->getMethod() . "\n";
    echo "Request Data: " . $request->getContent() . "\n\n";
    
    // Xử lý request
    $response = $kernel->handle($request);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Headers: " . json_encode($response->headers->all(), JSON_PRETTY_PRINT) . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
    
    $kernel->terminate($request, $response);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}