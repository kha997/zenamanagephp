<?php declare(strict_types=1);

echo "=== Check and Fix Vendor Directory ===\n";

// 1. Kiểm tra vendor directory structure
echo "\n1. Checking vendor directory structure...\n";
$vendorPath = 'vendor';
if (!is_dir($vendorPath)) {
    echo "❌ vendor directory does not exist!\n";
    echo "Running composer install...\n";
    exec('composer install --no-scripts 2>&1', $output, $return);
    if ($return === 0) {
        echo "✅ Composer install completed\n";
    } else {
        echo "❌ Composer install failed: " . implode('\n', $output) . "\n";
        exit(1);
    }
} else {
    echo "✅ vendor directory exists\n";
}

// 2. Kiểm tra Laravel framework files
echo "\n2. Checking Laravel framework files...\n";
$laravelPaths = [
    'vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php'
];

$missingFiles = [];
foreach ($laravelPaths as $path) {
    if (file_exists($path)) {
        echo "✅ $path exists\n";
    } else {
        echo "❌ $path missing\n";
        $missingFiles[] = $path;
    }
}

if (!empty($missingFiles)) {
    echo "\n⚠️ Laravel framework files are missing. Reinstalling...\n";
    
    // Remove vendor and reinstall
    exec('rm -rf vendor 2>&1', $rmOutput, $rmReturn);
    if ($rmReturn === 0) {
        echo "✅ Removed corrupted vendor directory\n";
    }
    
    exec('composer install --no-scripts --no-dev 2>&1', $installOutput, $installReturn);
    if ($installReturn === 0) {
        echo "✅ Reinstalled vendor directory\n";
    } else {
        echo "❌ Failed to reinstall: " . implode('\n', $installOutput) . "\n";
        exit(1);
    }
}

// 3. Kiểm tra autoloader
echo "\n3. Checking autoloader...\n";
if (file_exists('vendor/autoload.php')) {
    echo "✅ vendor/autoload.php exists\n";
    
    try {
        require_once 'vendor/autoload.php';
        echo "✅ Autoloader can be loaded\n";
        
        // Test critical Laravel classes
        $testClasses = [
            'Illuminate\\Config\\ConfigServiceProvider',
            'Illuminate\\Foundation\\Application',
            'Illuminate\\Support\\ServiceProvider'
        ];
        
        foreach ($testClasses as $class) {
            if (class_exists($class)) {
                echo "✅ $class exists\n";
            } else {
                echo "❌ $class does not exist\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error loading autoloader: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ vendor/autoload.php does not exist\n";
}

// 4. Test composer dump-autoload
echo "\n4. Testing composer dump-autoload...\n";
exec('composer dump-autoload 2>&1', $dumpOutput, $dumpReturn);
if ($dumpReturn === 0) {
    echo "✅ Composer dump-autoload successful\n";
    echo "Output: " . implode('\n', $dumpOutput) . "\n";
} else {
    echo "❌ Composer dump-autoload failed\n";
    echo "Error: " . implode('\n', $dumpOutput) . "\n";
}

// 5. Test basic Laravel bootstrap
echo "\n5. Testing basic Laravel bootstrap...\n";
try {
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        
        if (class_exists('Illuminate\\Foundation\\Application')) {
            $app = new Illuminate\Foundation\Application(realpath(__DIR__));
            echo "✅ Laravel Application can be instantiated\n";
            
            if (class_exists('Illuminate\\Config\\ConfigServiceProvider')) {
                $configProvider = new Illuminate\Config\ConfigServiceProvider($app);
                $configProvider->register();
                echo "✅ ConfigServiceProvider can be registered\n";
                
                if ($app->bound('config')) {
                    echo "✅ Config service is bound\n";
                } else {
                    echo "❌ Config service is not bound\n";
                }
            } else {
                echo "❌ ConfigServiceProvider class still does not exist\n";
            }
        } else {
            echo "❌ Laravel Application class does not exist\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error in Laravel bootstrap test: " . $e->getMessage() . "\n";
}

// 6. Test artisan command
echo "\n6. Testing artisan command...\n";
exec('php artisan --version 2>&1', $artisanOutput, $artisanReturn);
if ($artisanReturn === 0) {
    echo "✅ Artisan command works\n";
    echo "Version: " . implode('\n', $artisanOutput) . "\n";
} else {
    echo "❌ Artisan command failed\n";
    echo "Error: " . implode('\n', $artisanOutput) . "\n";
}

echo "\n=== Check and Fix Vendor Directory Complete ===\n";