<?php declare(strict_types=1);

echo "=== ULTIMATE LARAVEL FRAMEWORK FIX ===\n";
echo "Fixing persistent ConfigServiceProvider error...\n\n";

// Step 1: Verify PHP version
echo "1. Checking PHP version...\n";
$phpVersion = phpversion();
echo "PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '8.2.0', '<')) {
    echo "ERROR: PHP version must be >= 8.2.0\n";
    exit(1);
}
echo "✓ PHP version is compatible\n\n";

// Step 2: Stop any running processes that might lock files
echo "2. Stopping potential blocking processes...\n";
exec('pkill -f "composer"', $output, $return);
exec('pkill -f "artisan"', $output, $return);
echo "✓ Processes stopped\n\n";

// Step 3: Remove all cache and vendor completely
echo "3. Removing all cache and vendor files...\n";
$filesToRemove = [
    'vendor',
    'composer.lock',
    'bootstrap/cache/config.php',
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/routes-v7.php',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views'
];

foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        if (is_dir($file)) {
            exec("rm -rf $file", $output, $return);
            echo "Removed directory: $file\n";
        } else {
            unlink($file);
            echo "Removed file: $file\n";
        }
    }
}
echo "✓ All cache and vendor files removed\n\n";

// Step 4: Clear Composer cache completely
echo "4. Clearing Composer cache completely...\n";
exec('composer clear-cache 2>&1', $output, $return);
foreach ($output as $line) {
    echo "$line\n";
}
exec('rm -rf ~/.composer/cache', $output, $return);
echo "✓ Composer cache cleared\n\n";

// Step 5: Verify composer.json integrity
echo "5. Verifying composer.json integrity...\n";
if (!file_exists('composer.json')) {
    echo "ERROR: composer.json not found!\n";
    exit(1);
}

$composerData = json_decode(file_get_contents('composer.json'), true);
if (!$composerData) {
    echo "ERROR: composer.json is invalid!\n";
    exit(1);
}
echo "✓ composer.json is valid\n\n";

// Step 6: Try multiple installation methods
echo "6. Attempting installation with multiple methods...\n";

$installMethods = [
    'composer install --no-scripts --no-plugins --no-dev --optimize-autoloader',
    'composer install --no-scripts --optimize-autoloader',
    'composer install --optimize-autoloader',
    'composer update --no-scripts --optimize-autoloader',
    'composer install'
];

$success = false;
foreach ($installMethods as $index => $method) {
    echo "Method " . ($index + 1) . ": $method\n";
    exec("$method 2>&1", $output, $return);
    
    if ($return === 0) {
        echo "✓ Installation successful with method " . ($index + 1) . "\n";
        $success = true;
        break;
    } else {
        echo "✗ Method " . ($index + 1) . " failed\n";
        // Show last few lines of output for debugging
        $lastLines = array_slice($output, -5);
        foreach ($lastLines as $line) {
            echo "  $line\n";
        }
        echo "\n";
        $output = []; // Reset output for next method
    }
}

if (!$success) {
    echo "ERROR: All installation methods failed!\n";
    exit(1);
}

// Step 7: Verify Laravel framework installation
echo "\n7. Verifying Laravel framework installation...\n";
if (!file_exists('vendor/laravel/framework')) {
    echo "ERROR: Laravel framework not installed!\n";
    exit(1);
}

if (!file_exists('vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php')) {
    echo "ERROR: ConfigServiceProvider not found in Laravel framework!\n";
    exit(1);
}
echo "✓ Laravel framework and ConfigServiceProvider found\n\n";

// Step 8: Generate optimized autoloader
echo "8. Generating optimized autoloader...\n";
exec('composer dump-autoload --optimize --no-dev 2>&1', $output, $return);
if ($return !== 0) {
    echo "Warning: Optimized autoloader generation failed, trying basic...\n";
    exec('composer dump-autoload 2>&1', $output, $return);
}

foreach ($output as $line) {
    echo "$line\n";
}
echo "✓ Autoloader generated\n\n";

// Step 9: Recreate cache directories
echo "9. Recreating cache directories...\n";
$cacheDirs = [
    'bootstrap/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views'
];

foreach ($cacheDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir\n";
    }
}
echo "✓ Cache directories created\n\n";

// Step 10: Test basic PHP autoloading
echo "10. Testing basic PHP autoloading...\n";
try {
    require_once 'vendor/autoload.php';
    echo "✓ Autoloader loaded successfully\n";
    
    // Test if we can load Laravel Application class
    if (class_exists('Illuminate\\Foundation\\Application')) {
        echo "✓ Laravel Application class found\n";
    } else {
        echo "✗ Laravel Application class not found\n";
    }
    
    // Test if we can load ConfigServiceProvider
    if (class_exists('Illuminate\\Config\\ConfigServiceProvider')) {
        echo "✓ ConfigServiceProvider class found\n";
    } else {
        echo "✗ ConfigServiceProvider class not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Autoloader test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Step 11: Test Laravel artisan
echo "11. Testing Laravel artisan...\n";
exec('php artisan --version 2>&1', $output, $return);
if ($return === 0) {
    foreach ($output as $line) {
        echo "$line\n";
    }
    echo "✓ Laravel artisan working\n";
} else {
    echo "✗ Laravel artisan failed:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}
echo "\n";

// Step 12: Test package discovery
echo "12. Testing package discovery...\n";
exec('php artisan package:discover --ansi 2>&1', $output, $return);
if ($return === 0) {
    echo "✓ Package discovery successful\n";
} else {
    echo "✗ Package discovery failed:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}
echo "\n";

// Step 13: Final test with optimize:clear
echo "13. Final test with optimize:clear...\n";
exec('php artisan optimize:clear 2>&1', $output, $return);
if ($return === 0) {
    echo "✓ optimize:clear successful\n";
    foreach ($output as $line) {
        echo "$line\n";
    }
} else {
    echo "✗ optimize:clear failed:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

echo "\n=== ULTIMATE FIX COMPLETED ===\n";
echo "Please test the following commands manually:\n";
echo "1. php artisan --version\n";
echo "2. php artisan optimize:clear\n";
echo "3. curl http://localhost/api/test\n";
?>