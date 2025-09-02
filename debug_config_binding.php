<?php

// Script debug để kiểm tra config service binding
echo "=== DEBUG CONFIG SERVICE BINDING ===\n";

try {
    // 1. Kiểm tra autoloader
    echo "1. Checking autoloader...\n";
    require_once __DIR__.'/vendor/autoload.php';
    echo "   ✓ Autoloader loaded\n";
    
    // 2. Tạo Laravel Application
    echo "2. Creating Laravel Application...\n";
    $app = require_once __DIR__.'/bootstrap/app.php';
    echo "   ✓ Application created\n";
    
    // 3. Kiểm tra config service có được bound không
    echo "3. Checking config service binding...\n";
    $isBound = $app->bound('config');
    echo "   Config bound: " . ($isBound ? 'YES' : 'NO') . "\n";
    
    if (!$isBound) {
        echo "   ❌ Config service NOT bound!\n";
        
        // Kiểm tra các service provider đã được load
        echo "4. Checking loaded service providers...\n";
        $providers = $app->getLoadedProviders();
        $configProviders = array_filter(array_keys($providers), function($provider) {
            return strpos($provider, 'Config') !== false;
        });
        echo "   Config-related providers: " . implode(', ', $configProviders) . "\n";
        
        // Thử manually register ConfigServiceProvider
        echo "5. Manually registering ConfigServiceProvider...\n";
        $configProvider = new \Illuminate\Foundation\Bootstrap\LoadConfiguration($app);
        $configProvider->bootstrap($app);
        echo "   ✓ ConfigServiceProvider registered\n";
        
        // Kiểm tra lại
        $isBoundAfter = $app->bound('config');
        echo "   Config bound after manual registration: " . ($isBoundAfter ? 'YES' : 'NO') . "\n";
    } else {
        echo "   ✓ Config service is bound\n";
        
        // Thử sử dụng config
        echo "4. Testing config usage...\n";
        $config = $app->make('config');
        echo "   Config class: " . get_class($config) . "\n";
        
        // Thử load một config value
        $appName = $config->get('app.name', 'default');
        echo "   App name from config: $appName\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (\Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETED ===\n";