<?php declare(strict_types=1);

echo "=== Force Fix Laravel Framework ===\n\n";

// 1. Clear toàn bộ Composer cache
echo "1. Clearing all Composer cache...\n";
exec('composer clear-cache 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Composer cache cleared\n";
} else {
    echo "❌ Failed to clear composer cache: " . implode("\n", $output) . "\n";
}

// 2. Remove vendor và composer.lock hoàn toàn
echo "\n2. Removing vendor directory and composer.lock...\n";
if (is_dir('vendor')) {
    exec('rm -rf vendor', $output, $returnCode);
    echo "✅ Removed vendor directory\n";
}
if (file_exists('composer.lock')) {
    unlink('composer.lock');
    echo "✅ Removed composer.lock\n";
}

// 3. Backup composer.json và disable package discovery
echo "\n3. Disabling package discovery temporarily...\n";
copy('composer.json', 'composer.json.backup');
$composerData = json_decode(file_get_contents('composer.json'), true);
$composerData['config']['discover-packages'] = false;
$composerData['config']['optimize-autoloader'] = false;
file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "✅ Package discovery disabled\n";

// 4. Install với flags đặc biệt
echo "\n4. Installing with special flags...\n";
exec('composer install --no-scripts --no-plugins --no-dev --ignore-platform-reqs 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Composer install successful\n";
} else {
    echo "❌ Composer install failed:\n" . implode("\n", $output) . "\n";
}

// 5. Kiểm tra file Laravel framework quan trọng
echo "\n5. Checking critical Laravel framework files...\n";
$criticalFiles = [
    'vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php',
    'vendor/laravel/framework/src/Illuminate/Container/Container.php',
    'vendor/laravel/framework/src/Illuminate/Config/Repository.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} exists\n";
    } else {
        echo "❌ {$file} missing\n";
    }
}

// 6. Test autoloader cơ bản
echo "\n6. Testing basic autoloader...\n";
try {
    require_once 'vendor/autoload.php';
    echo "✅ Autoloader loaded successfully\n";
    
    // Test từng class quan trọng
    $testClasses = [
        'Illuminate\\Foundation\\Application',
        'Illuminate\\Config\\ConfigServiceProvider',
        'Illuminate\\Support\\ServiceProvider',
        'Illuminate\\Container\\Container'
    ];
    
    foreach ($testClasses as $class) {
        if (class_exists($class)) {
            echo "✅ {$class} exists\n";
        } else {
            echo "❌ {$class} does not exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Autoloader failed: " . $e->getMessage() . "\n";
}

// 7. Restore composer.json và enable package discovery
echo "\n7. Restoring composer.json...\n";
copy('composer.json.backup', 'composer.json');
unlink('composer.json.backup');
echo "✅ composer.json restored\n";

// 8. Test với package discovery enabled
echo "\n8. Testing with package discovery enabled...\n";
exec('composer dump-autoload --optimize 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Composer dump-autoload successful\n";
} else {
    echo "❌ Composer dump-autoload failed:\n" . implode("\n", $output) . "\n";
    
    // Nếu vẫn lỗi, thử disable package discovery vĩnh viễn
    echo "\n9. Permanently disabling package discovery...\n";
    $composerData = json_decode(file_get_contents('composer.json'), true);
    $composerData['config']['discover-packages'] = false;
    file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    exec('composer dump-autoload --optimize 2>&1', $output2, $returnCode2);
    if ($returnCode2 === 0) {
        echo "✅ Composer dump-autoload successful with package discovery disabled\n";
    } else {
        echo "❌ Still failing: " . implode("\n", $output2) . "\n";
    }
}

// 9. Test Laravel bootstrap
echo "\n10. Testing Laravel bootstrap...\n";
try {
    require_once 'vendor/autoload.php';
    
    // Test tạo Application instance
    $app = new \Illuminate\Foundation\Application(realpath(__DIR__));
    echo "✅ Laravel Application created successfully\n";
    
    // Test ConfigServiceProvider
    if (class_exists('\Illuminate\Config\ConfigServiceProvider')) {
        $configProvider = new \Illuminate\Config\ConfigServiceProvider($app);
        echo "✅ ConfigServiceProvider can be instantiated\n";
    } else {
        echo "❌ ConfigServiceProvider still missing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Laravel bootstrap failed: " . $e->getMessage() . "\n";
}

// 10. Test Artisan command
echo "\n11. Testing Artisan command...\n";
exec('php artisan --version 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Artisan command works: " . implode("\n", $output) . "\n";
} else {
    echo "❌ Artisan command failed: " . implode("\n", $output) . "\n";
}

// 11. Test HTTP request
echo "\n12. Testing HTTP request...\n";
$testUrl = 'http://localhost/zenamanage/public/api/test';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($testUrl, false, $context);
if ($response !== false) {
    echo "✅ HTTP request successful\n";
    echo "Response: " . $response . "\n";
} else {
    echo "❌ HTTP request failed\n";
    if (isset($http_response_header)) {
        echo "Headers: " . implode("\n", $http_response_header) . "\n";
    }
}

echo "\n=== Force Fix Laravel Framework Complete ===\n";