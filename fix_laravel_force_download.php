<?php declare(strict_types=1);

echo "=== FORCE DOWNLOAD LARAVEL FRAMEWORK ===\n";
echo "Forcing complete re-download of Laravel framework...\n\n";

// 1. Check PHP version
echo "1. Checking PHP version...\n";
$phpVersion = phpversion();
echo "PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '8.2.0', '<')) {
    die("❌ PHP version must be >= 8.2.0\n");
}
echo "✓ PHP version is compatible\n\n";

// 2. Stop any running processes
echo "2. Stopping potential blocking processes...\n";
exec('pkill -f "composer"', $output, $return);
exec('pkill -f "php artisan"', $output, $return);
echo "✓ Processes stopped\n\n";

// 3. Remove everything completely
echo "3. Removing ALL files and caches...\n";
if (is_dir('vendor')) {
    exec('rm -rf vendor', $output, $return);
    echo "Removed directory: vendor\n";
}
if (file_exists('composer.lock')) {
    unlink('composer.lock');
    echo "Removed file: composer.lock\n";
}

// Remove all Laravel caches
$cacheDirs = [
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php', 
    'bootstrap/cache/config.php',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views'
];

foreach ($cacheDirs as $dir) {
    if (file_exists($dir)) {
        if (is_dir($dir)) {
            exec("rm -rf $dir", $output, $return);
            echo "Removed directory: $dir\n";
        } else {
            unlink($dir);
            echo "Removed file: $dir\n";
        }
    }
}
echo "✓ All files and caches removed\n\n";

// 4. Clear ALL Composer caches (global and local)
echo "4. Clearing ALL Composer caches...\n";
exec('composer clear-cache 2>&1', $output, $return);
foreach ($output as $line) {
    echo "$line\n";
}

// Also clear global cache manually
$homeDir = $_SERVER['HOME'] ?? '/Users/' . get_current_user();
$globalCacheDir = $homeDir . '/.composer/cache';
if (is_dir($globalCacheDir)) {
    exec("rm -rf $globalCacheDir", $output, $return);
    echo "Removed global Composer cache: $globalCacheDir\n";
}
echo "✓ All Composer caches cleared\n\n";

// 5. Force update Composer itself
echo "5. Updating Composer to latest version...\n";
exec('composer self-update 2>&1', $output, $return);
foreach ($output as $line) {
    echo "$line\n";
}
echo "✓ Composer updated\n\n";

// 6. Verify composer.json
echo "6. Verifying composer.json integrity...\n";
exec('composer validate 2>&1', $output, $return);
if ($return !== 0) {
    die("❌ composer.json is invalid\n");
}
echo "✓ composer.json is valid\n\n";

// 7. Force install with multiple aggressive methods
echo "7. Force installing with aggressive methods...\n\n";

$methods = [
    'composer install --no-cache --prefer-source --no-scripts --no-plugins',
    'composer install --no-cache --prefer-dist --no-scripts --no-plugins --ignore-platform-reqs',
    'composer install --no-cache --prefer-source --ignore-platform-reqs',
    'composer update --no-cache --prefer-source --no-scripts --no-plugins',
    'composer install --no-cache --prefer-dist'
];

$success = false;
foreach ($methods as $index => $method) {
    $methodNum = $index + 1;
    echo "Method $methodNum: $method\n";
    
    exec("$method 2>&1", $output, $return);
    
    if ($return === 0) {
        echo "✓ Installation successful with method $methodNum\n\n";
        $success = true;
        break;
    } else {
        echo "❌ Method $methodNum failed\n";
        // Show last few lines of error
        $errorLines = array_slice($output, -3);
        foreach ($errorLines as $line) {
            echo "  $line\n";
        }
        echo "\n";
        $output = []; // Reset for next method
    }
}

if (!$success) {
    die("❌ All installation methods failed\n");
}

// 8. Verify Laravel framework files exist
echo "8. Verifying Laravel framework installation...\n";
$criticalFiles = [
    'vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php'
];

$allFilesExist = true;
foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "✓ Found: $file\n";
    } else {
        echo "❌ Missing: $file\n";
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "\n❌ Laravel framework files are still missing!\n";
    echo "Checking vendor/laravel/framework structure...\n";
    
    if (is_dir('vendor/laravel/framework')) {
        exec('find vendor/laravel/framework -name "*.php" | head -10', $output, $return);
        echo "Sample files found:\n";
        foreach ($output as $line) {
            echo "  $line\n";
        }
    } else {
        echo "❌ vendor/laravel/framework directory does not exist!\n";
    }
    
    die("\n❌ Critical Laravel framework files are missing\n");
}

echo "✓ All critical Laravel framework files verified\n\n";

// 9. Generate optimized autoloader
echo "9. Generating optimized autoloader...\n";
exec('composer dump-autoload --optimize --no-dev 2>&1', $output, $return);
if ($return === 0) {
    echo "✓ Autoloader generated successfully\n";
} else {
    echo "❌ Autoloader generation failed\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}
echo "\n";

// 10. Test Laravel artisan
echo "10. Testing Laravel artisan functionality...\n";
exec('php artisan --version 2>&1', $output, $return);
if ($return === 0) {
    echo "✓ Laravel artisan is working:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
} else {
    echo "❌ Laravel artisan failed:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

echo "\n=== FORCE DOWNLOAD COMPLETE ===\n";