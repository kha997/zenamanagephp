<?php declare(strict_types=1);

echo "=== COMPLETE LARAVEL FRAMEWORK REINSTALL ===\n";
echo "Fixing incomplete Laravel framework installation...\n\n";

// 1. Kiểm tra PHP version
echo "1. Checking PHP version...\n";
$phpVersion = phpversion();
echo "PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '8.2.0', '<')) {
    echo "❌ PHP version must be >= 8.2.0\n";
    exit(1);
}
echo "✓ PHP version is compatible\n\n";

// 2. Xóa hoàn toàn vendor và composer.lock
echo "2. Removing vendor and composer.lock completely...\n";
if (is_dir('vendor')) {
    exec('rm -rf vendor', $output, $returnCode);
    if ($returnCode === 0) {
        echo "✓ Removed vendor directory\n";
    } else {
        echo "❌ Failed to remove vendor directory\n";
    }
} else {
    echo "✓ Vendor directory already removed\n";
}

if (file_exists('composer.lock')) {
    unlink('composer.lock');
    echo "✓ Removed composer.lock\n";
} else {
    echo "✓ composer.lock already removed\n";
}

// 3. Clear tất cả Composer cache
echo "\n3. Clearing all Composer caches...\n";
exec('composer clear-cache 2>&1', $clearOutput, $clearCode);
foreach ($clearOutput as $line) {
    echo "$line\n";
}
exec('rm -rf ~/.composer/cache/* 2>/dev/null');
exec('rm -rf ~/Library/Caches/composer/* 2>/dev/null');
echo "✓ All Composer caches cleared\n\n";

// 4. Cài đặt lại với các tùy chọn khác nhau
echo "4. Reinstalling dependencies with multiple methods...\n";

$installMethods = [
    'composer install --no-cache --prefer-dist --optimize-autoloader',
    'composer install --no-cache --prefer-source',
    'composer install --no-dev --optimize-autoloader',
    'composer install'
];

$success = false;
foreach ($installMethods as $index => $method) {
    echo "\nMethod " . ($index + 1) . ": $method\n";
    exec("$method 2>&1", $installOutput, $installCode);
    
    if ($installCode === 0) {
        echo "✓ Installation successful with method " . ($index + 1) . "\n";
        $success = true;
        break;
    } else {
        echo "❌ Method " . ($index + 1) . " failed\n";
        // Show last few lines of error
        $errorLines = array_slice($installOutput, -5);
        foreach ($errorLines as $line) {
            echo "  $line\n";
        }
    }
    $installOutput = []; // Reset for next method
}

if (!$success) {
    echo "❌ All installation methods failed\n";
    exit(1);
}

// 5. Xác minh Laravel framework installation
echo "\n5. Verifying Laravel framework installation...\n";
$configServiceProvider = 'vendor/laravel/framework/src/Illuminate/Config/ConfigServiceProvider.php';
if (file_exists($configServiceProvider)) {
    echo "✓ ConfigServiceProvider.php exists\n";
    $fileSize = filesize($configServiceProvider);
    echo "✓ File size: $fileSize bytes\n";
    
    // Kiểm tra nội dung file
    $content = file_get_contents($configServiceProvider);
    if (strpos($content, 'class ConfigServiceProvider') !== false) {
        echo "✓ ConfigServiceProvider class found in file\n";
    } else {
        echo "❌ ConfigServiceProvider class not found in file\n";
    }
} else {
    echo "❌ ConfigServiceProvider.php still missing!\n";
    
    // List contents of Config directory
    echo "\nContents of vendor/laravel/framework/src/Illuminate/Config/:\n";
    $configDir = 'vendor/laravel/framework/src/Illuminate/Config';
    if (is_dir($configDir)) {
        $files = scandir($configDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - $file\n";
            }
        }
    } else {
        echo "  Directory does not exist\n";
    }
    exit(1);
}

// 6. Tạo lại autoloader
echo "\n6. Regenerating autoloader...\n";
exec('composer dump-autoload --optimize 2>&1', $autoloadOutput, $autoloadCode);
if ($autoloadCode === 0) {
    echo "✓ Autoloader regenerated successfully\n";
} else {
    echo "❌ Failed to regenerate autoloader\n";
    foreach ($autoloadOutput as $line) {
        echo "  $line\n";
    }
}

// 7. Test Laravel artisan
echo "\n7. Testing Laravel artisan...\n";
exec('php artisan --version 2>&1', $artisanOutput, $artisanCode);
if ($artisanCode === 0) {
    echo "✓ Laravel artisan working:\n";
    foreach ($artisanOutput as $line) {
        echo "  $line\n";
    }
} else {
    echo "❌ Laravel artisan failed:\n";
    foreach ($artisanOutput as $line) {
        echo "  $line\n";
    }
}

// 8. Test ConfigServiceProvider class loading
echo "\n8. Testing ConfigServiceProvider class loading...\n";
try {
    require_once 'vendor/autoload.php';
    
    if (class_exists('Illuminate\\Config\\ConfigServiceProvider')) {
        echo "✓ ConfigServiceProvider class can be loaded\n";
    } else {
        echo "❌ ConfigServiceProvider class cannot be loaded\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading ConfigServiceProvider: " . $e->getMessage() . "\n";
}

echo "\n=== COMPLETE LARAVEL FRAMEWORK REINSTALL FINISHED ===\n";