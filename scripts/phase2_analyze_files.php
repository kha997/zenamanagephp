<?php

/**
 * PHASE 2: Script phân tích và cleanup file rác/trùng
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🗑️ PHASE 2: LIỆT KÊ & XÓA FILE RÁC/TRÙNG\n";
echo "==========================================\n\n";

// 1. Tìm file test/debug cũ
echo "1️⃣ Tìm file test/debug cũ...\n";
$testFiles = [];
$debugFiles = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        // Skip vendor và node_modules
        if (strpos($relativePath, 'vendor/') === 0 || strpos($relativePath, 'node_modules/') === 0) {
            continue;
        }
        
        $filename = $file->getFilename();
        
        // Test files
        if (preg_match('/test.*\.(php|html)$/i', $filename) || 
            preg_match('/.*test.*\.(php|html)$/i', $filename)) {
            $testFiles[] = $relativePath;
        }
        
        // Debug files
        if (preg_match('/debug.*\.(php|html)$/i', $filename) || 
            preg_match('/.*debug.*\.(php|html)$/i', $filename)) {
            $debugFiles[] = $relativePath;
        }
    }
}

echo "   📊 Tìm thấy " . count($testFiles) . " file test\n";
echo "   📊 Tìm thấy " . count($debugFiles) . " file debug\n\n";

// 2. Tìm file backup
echo "2️⃣ Tìm file backup...\n";
$backupFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        if (strpos($relativePath, 'vendor/') === 0 || strpos($relativePath, 'node_modules/') === 0) {
            continue;
        }
        
        $filename = $file->getFilename();
        
        if (preg_match('/.*backup.*/i', $filename) || 
            preg_match('/.*\.backup$/i', $filename) ||
            preg_match('/.*\.bak$/i', $filename)) {
            $backupFiles[] = $relativePath;
        }
    }
}

echo "   📊 Tìm thấy " . count($backupFiles) . " file backup\n\n";

// 3. Tìm file log cũ
echo "3️⃣ Tìm file log cũ...\n";
$logFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        if (strpos($relativePath, 'vendor/') === 0 || strpos($relativePath, 'node_modules/') === 0) {
            continue;
        }
        
        $filename = $file->getFilename();
        
        if (preg_match('/.*\.log$/i', $filename) && 
            strpos($relativePath, 'storage/logs/') !== 0) {
            $logFiles[] = $relativePath;
        }
    }
}

echo "   📊 Tìm thấy " . count($logFiles) . " file log ngoài storage/logs\n\n";

// 4. Tìm file HTML standalone
echo "4️⃣ Tìm file HTML standalone...\n";
$htmlFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        if (strpos($relativePath, 'vendor/') === 0 || strpos($relativePath, 'node_modules/') === 0) {
            continue;
        }
        
        $filename = $file->getFilename();
        
        if (preg_match('/.*\.html$/i', $filename) && 
            strpos($relativePath, 'resources/views/') !== 0 &&
            strpos($relativePath, 'public/') !== 0) {
            $htmlFiles[] = $relativePath;
        }
    }
}

echo "   📊 Tìm thấy " . count($htmlFiles) . " file HTML standalone\n\n";

// 5. Tìm thư mục trống
echo "5️⃣ Tìm thư mục trống...\n";
$emptyDirs = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) {
    if ($file->isDir()) {
        $relativePath = str_replace($basePath . '/', '', $file->getPathname());
        
        if (strpos($relativePath, 'vendor/') === 0 || strpos($relativePath, 'node_modules/') === 0) {
            continue;
        }
        
        // Check if directory is empty (only contains . and ..)
        $files = scandir($file->getPathname());
        if (count($files) <= 2) {
            $emptyDirs[] = $relativePath;
        }
    }
}

echo "   📊 Tìm thấy " . count($emptyDirs) . " thư mục trống\n\n";

// 6. Tạo báo cáo
echo "📋 BÁO CÁO CHI TIẾT:\n";
echo "==================\n\n";

if (!empty($testFiles)) {
    echo "🧪 FILE TEST:\n";
    foreach ($testFiles as $file) {
        echo "   - {$file}\n";
    }
    echo "\n";
}

if (!empty($debugFiles)) {
    echo "🐛 FILE DEBUG:\n";
    foreach ($debugFiles as $file) {
        echo "   - {$file}\n";
    }
    echo "\n";
}

if (!empty($backupFiles)) {
    echo "💾 FILE BACKUP:\n";
    foreach ($backupFiles as $file) {
        echo "   - {$file}\n";
    }
    echo "\n";
}

if (!empty($logFiles)) {
    echo "📝 FILE LOG:\n";
    foreach ($logFiles as $file) {
        echo "   - {$file}\n";
    }
    echo "\n";
}

if (!empty($htmlFiles)) {
    echo "🌐 FILE HTML STANDALONE:\n";
    foreach ($htmlFiles as $file) {
        echo "   - {$file}\n";
    }
    echo "\n";
}

if (!empty($emptyDirs)) {
    echo "📁 THƯ MỤC TRỐNG:\n";
    foreach ($emptyDirs as $dir) {
        echo "   - {$dir}\n";
    }
    echo "\n";
}

// 7. Tính tổng dung lượng có thể giải phóng
$totalSize = 0;
$allFiles = array_merge($testFiles, $debugFiles, $backupFiles, $logFiles, $htmlFiles);

foreach ($allFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $totalSize += filesize($fullPath);
    }
}

echo "💾 TỔNG DUNG LƯỢNG CÓ THỂ GIẢI PHÓNG: " . formatBytes($totalSize) . "\n";
echo "📊 TỔNG SỐ FILE CÓ THỂ XÓA: " . count($allFiles) . "\n\n";

echo "🎯 Hoàn thành phân tích PHASE 2!\n";

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
