<?php declare(strict_types=1);

echo "=== XAMPP PHP Upgrade Script ===\n";
echo "Current PHP Version: " . PHP_VERSION . "\n";
echo "Required PHP Version: >= 8.2.0\n\n";

// Kiểm tra hệ điều hành
if (PHP_OS_FAMILY !== 'Darwin') {
    echo "❌ Script này chỉ hỗ trợ macOS. Vui lòng upgrade PHP thủ công.\n";
    exit(1);
}

// Kiểm tra Homebrew
echo "🔍 Checking Homebrew installation...\n";
$brewCheck = shell_exec('which brew 2>/dev/null');
if (empty($brewCheck)) {
    echo "❌ Homebrew không được cài đặt. Vui lòng cài đặt Homebrew trước:\n";
    echo "   /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\"\n";
    exit(1);
}
echo "✅ Homebrew đã được cài đặt\n";

// Backup XAMPP config
echo "\n📦 Creating backup of XAMPP configuration...\n";
$xamppPath = '/Applications/XAMPP';
$backupPath = $xamppPath . '/backup_' . date('Y-m-d_H-i-s');

if (!is_dir($xamppPath)) {
    echo "❌ XAMPP không được tìm thấy tại /Applications/XAMPP\n";
    exit(1);
}

// Tạo backup directory
if (!mkdir($backupPath, 0755, true)) {
    echo "❌ Không thể tạo backup directory\n";
    exit(1);
}

// Backup important configs
$configFiles = [
    '/Applications/XAMPP/xamppfiles/etc/httpd.conf',
    '/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf',
    '/Applications/XAMPP/xamppfiles/etc/my.cnf'
];

foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        $backupFile = $backupPath . '/' . basename($configFile);
        if (copy($configFile, $backupFile)) {
            echo "✅ Backed up: " . basename($configFile) . "\n";
        } else {
            echo "⚠️  Warning: Could not backup " . basename($configFile) . "\n";
        }
    }
}

echo "\n🔄 Installing PHP 8.2 via Homebrew...\n";
echo "This may take several minutes...\n\n";

// Install PHP 8.2
$commands = [
    'brew update',
    'brew install php@8.2',
    'brew link php@8.2 --force --overwrite'
];

foreach ($commands as $command) {
    echo "Executing: $command\n";
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "❌ Command failed: $command\n";
        echo "Output: " . implode("\n", $output) . "\n";
        echo "\n⚠️  Please run the command manually and check for errors.\n";
    } else {
        echo "✅ Command completed successfully\n";
    }
    echo "\n";
}

// Update PATH
echo "🔧 Updating PATH configuration...\n";
$shellProfile = $_SERVER['HOME'] . '/.zshrc';
if (!file_exists($shellProfile)) {
    $shellProfile = $_SERVER['HOME'] . '/.bash_profile';
}

$pathLine = 'export PATH="/opt/homebrew/bin:/opt/homebrew/sbin:$PATH"';
$phpPathLine = 'export PATH="/opt/homebrew/bin/php:$PATH"';

if (file_exists($shellProfile)) {
    $content = file_get_contents($shellProfile);
    if (strpos($content, $pathLine) === false) {
        file_put_contents($shellProfile, "\n# Added by XAMPP PHP upgrade script\n" . $pathLine . "\n" . $phpPathLine . "\n", FILE_APPEND);
        echo "✅ Updated shell profile: $shellProfile\n";
    } else {
        echo "✅ PATH already configured in shell profile\n";
    }
}

// Verify installation
echo "\n🔍 Verifying PHP installation...\n";
$newPhpVersion = shell_exec('/opt/homebrew/bin/php -v 2>/dev/null');
if ($newPhpVersion) {
    echo "New PHP Version:\n$newPhpVersion\n";
    
    // Check if version is 8.2+
    preg_match('/PHP (\d+\.\d+)/', $newPhpVersion, $matches);
    if (isset($matches[1]) && version_compare($matches[1], '8.2', '>=')) {
        echo "✅ PHP 8.2+ installed successfully!\n";
    } else {
        echo "⚠️  PHP version may not be 8.2+. Please check manually.\n";
    }
} else {
    echo "⚠️  Could not verify new PHP installation\n";
}

// Instructions for XAMPP integration
echo "\n📋 Next Steps:\n";
echo "1. Restart your terminal to apply PATH changes\n";
echo "2. Verify PHP version: php -v\n";
echo "3. Navigate to your project: cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage\n";
echo "4. Reinstall Composer dependencies: composer install\n";
echo "5. Clear Laravel cache: php artisan optimize:clear\n";
echo "6. Test your application\n";

echo "\n⚠️  Important Notes:\n";
echo "- You may need to restart XAMPP after PHP upgrade\n";
echo "- If you encounter issues, restore from backup: $backupPath\n";
echo "- Consider updating XAMPP to a newer version that includes PHP 8.2+\n";

echo "\n🎉 PHP upgrade script completed!\n";
?>