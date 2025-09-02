<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

try {
    echo "Testing Laravel bootstrap without JWT...\n";
    
    // Tạo Laravel app instance
    $app = require_once __DIR__ . '/bootstrap/app.php';
    
    echo "✓ App instance created successfully\n";
    
    // Test config service
    $config = $app->make('config');
    echo "✓ Config service resolved: " . get_class($config) . "\n";
    
    
    // Test auth service
    $auth = $app->make('auth');
    echo "✓ Auth service resolved: " . get_class($auth) . "\n";
    
    echo "\n🎉 Laravel bootstrap test PASSED!\n";
    
} catch (Exception $e) {
    echo "\n❌ Laravel bootstrap test FAILED:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}