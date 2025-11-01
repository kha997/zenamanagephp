<?php
/**
 * Script đơn giản để clean up test files và clear cache
 */

echo "🧹 CLEANUP SCRIPT\n";
echo "================\n\n";

// Clean up test files
echo "1. Cleaning up test/bug files...\n";
$cleanedFiles = [];

$patterns = [
    'test_*.php',
    'debug_*.php', 
    'public/test_*.html',
    'public/debug_*.html',
    'public/*_test.html',
    'public/simple_*.html',
    'public/direct_*.html',
    'public/working_*.html',
];

foreach ($patterns as $pattern) {
    $files = glob(__DIR__ . '/../' . $pattern);
    foreach ($files as $file) {
        if (unlink($file)) {
            $cleanedFiles[] = basename($file);
            echo "   ✅ Deleted: " . basename($file) . "\n";
        }
    }
}

if (empty($cleanedFiles)) {
    echo "   ✅ No test/bug files to clean up\n";
}

// Clear Laravel caches
echo "\n2. Clearing Laravel caches...\n";
$commands = [
    'php artisan route:clear',
    'php artisan config:clear', 
    'php artisan cache:clear',
    'php artisan view:clear',
    'composer dump-autoload --quiet'
];

foreach ($commands as $command) {
    echo "   Running: $command\n";
    exec("cd " . __DIR__ . "/.. && $command", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✅ Success\n";
    } else {
        echo "   ❌ Failed\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY:\n";
echo "- Files cleaned: " . count($cleanedFiles) . "\n";
echo "- Caches cleared: " . count($commands) . "\n";
echo "\n🎉 Cleanup completed!\n";
