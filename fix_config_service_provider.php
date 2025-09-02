<?php declare(strict_types=1);

echo "=== Fix ConfigServiceProvider Registration ===\n";

// 1. Kiểm tra config/app.php
echo "\n1. Checking config/app.php...\n";
$configPath = 'config/app.php';
if (!file_exists($configPath)) {
    echo "❌ config/app.php not found!\n";
    exit(1);
}

$configContent = file_get_contents($configPath);
if (strpos($configContent, 'Illuminate\\Config\\ConfigServiceProvider') === false) {
    echo "❌ ConfigServiceProvider not found in config/app.php!\n";
    
    // Backup original
    copy($configPath, $configPath . '.backup.' . date('Y-m-d_H-i-s'));
    
    // Add ConfigServiceProvider to providers array
    $pattern = "/'providers'\s*=>\s*\[([^\]]+)\]/s";
    if (preg_match($pattern, $configContent, $matches)) {
        $providers = $matches[1];
        if (strpos($providers, 'Illuminate\\Config\\ConfigServiceProvider') === false) {
            // Add at the beginning of providers array
            $newProviders = str_replace(
                "'providers' => [",
                "'providers' => [\n        Illuminate\\Config\\ConfigServiceProvider::class,",
                $configContent
            );
            file_put_contents($configPath, $newProviders);
            echo "✅ Added ConfigServiceProvider to config/app.php\n";
        }
    }
} else {
    echo "✅ ConfigServiceProvider found in config/app.php\n";
}

// 2. Kiểm tra bootstrap/app.php
echo "\n2. Checking bootstrap/app.php...\n";
$bootstrapPath = 'bootstrap/app.php';
if (file_exists($bootstrapPath)) {
    $bootstrapContent = file_get_contents($bootstrapPath);
    echo "✅ bootstrap/app.php exists\n";
    
    // Kiểm tra xem có custom service provider registration không
    if (strpos($bootstrapContent, 'registerConfiguredProviders') !== false) {
        echo "✅ registerConfiguredProviders found\n";
    }
} else {
    echo "❌ bootstrap/app.php not found!\n";
}

// 3. Test ConfigServiceProvider trực tiếp
echo "\n3. Testing ConfigServiceProvider directly...\n";
try {
    require_once 'vendor/autoload.php';
    
    // Test class exists
    if (class_exists('Illuminate\\Config\\ConfigServiceProvider')) {
        echo "✅ ConfigServiceProvider class exists\n";
        
        // Test instantiation
        $app = new Illuminate\Foundation\Application(realpath(__DIR__));
        $provider = new Illuminate\Config\ConfigServiceProvider($app);
        echo "✅ ConfigServiceProvider can be instantiated\n";
        
        // Test registration
        $provider->register();
        echo "✅ ConfigServiceProvider register() method works\n";
        
        // Check if config service is bound
        if ($app->bound('config')) {
            echo "✅ Config service is bound after registration\n";
        } else {
            echo "❌ Config service is NOT bound after registration\n";
        }
        
    } else {
        echo "❌ ConfigServiceProvider class does not exist\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing ConfigServiceProvider: " . $e->getMessage() . "\n";
}

// 4. Clear all caches
echo "\n4. Clearing caches...\n";
exec('composer dump-autoload 2>&1', $output, $return);
if ($return === 0) {
    echo "✅ Composer autoload refreshed\n";
} else {
    echo "❌ Failed to refresh composer autoload\n";
}

// Clear Laravel caches
$commands = [
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan route:clear',
    'php artisan view:clear'
];

foreach ($commands as $cmd) {
    exec($cmd . ' 2>&1', $cmdOutput, $cmdReturn);
    if ($cmdReturn === 0) {
        echo "✅ $cmd completed\n";
    } else {
        echo "❌ $cmd failed: " . implode('\n', $cmdOutput) . "\n";
    }
}

// 5. Test HTTP request
echo "\n5. Testing HTTP request...\n";
$testUrl = 'http://localhost/zenamanage/public/api/test';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($testUrl, false, $context);
if ($response !== false) {
    echo "✅ HTTP request successful\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
} else {
    echo "❌ HTTP request failed\n";
    $headers = $http_response_header ?? [];
    echo "Headers: " . implode(', ', $headers) . "\n";
}

echo "\n=== Fix ConfigServiceProvider Registration Complete ===\n";