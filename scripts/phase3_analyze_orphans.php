<?php

/**
 * PHASE 3: Script phân tích dependencies và code mồ côi
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "🔍 PHASE 3: TÌM CODE/DEPENDENCY MỒ CÔI\n";
echo "=====================================\n\n";

// 1. Phân tích composer.json để tìm dependencies có thể không cần
echo "1️⃣ Phân tích composer dependencies...\n";

$composerJson = json_decode(file_get_contents($basePath . '/composer.json'), true);
$requireDev = $composerJson['require-dev'] ?? [];
$require = $composerJson['require'] ?? [];

echo "   📊 Production dependencies: " . count($require) . "\n";
echo "   📊 Development dependencies: " . count($requireDev) . "\n\n";

// 2. Tìm các class không được sử dụng
echo "2️⃣ Tìm class không được sử dụng...\n";

$allClasses = [];
$usedClasses = [];

// Scan tất cả class trong app/
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Tìm class definition
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            $allClasses[] = $className;
        }
    }
}

// Scan tất cả file để tìm usage
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'])) {
        $content = file_get_contents($file->getPathname());
        
        // Tìm usage của class
        foreach ($allClasses as $className) {
            if (strpos($content, $className) !== false) {
                $usedClasses[$className] = true;
            }
        }
    }
}

$unusedClasses = array_diff($allClasses, array_keys($usedClasses));
echo "   📊 Total classes: " . count($allClasses) . "\n";
echo "   📊 Used classes: " . count($usedClasses) . "\n";
echo "   📊 Potentially unused: " . count($unusedClasses) . "\n\n";

// 3. Tìm các method không được sử dụng
echo "3️⃣ Tìm method không được sử dụng...\n";

$allMethods = [];
$usedMethods = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Tìm method definition (public, protected, private)
        if (preg_match_all('/(?:public|protected|private)\s+function\s+(\w+)/', $content, $matches)) {
            foreach ($matches[1] as $methodName) {
                if (!in_array($methodName, ['__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset', '__toString', '__invoke', '__set_state', '__clone', '__debugInfo'])) {
                    $allMethods[] = $methodName;
                }
            }
        }
    }
}

// Scan usage của methods
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'])) {
        $content = file_get_contents($file->getPathname());
        
        foreach ($allMethods as $methodName) {
            if (strpos($content, $methodName) !== false) {
                $usedMethods[$methodName] = true;
            }
        }
    }
}

$unusedMethods = array_diff($allMethods, array_keys($usedMethods));
echo "   📊 Total methods: " . count($allMethods) . "\n";
echo "   📊 Used methods: " . count($usedMethods) . "\n";
echo "   📊 Potentially unused: " . count($unusedMethods) . "\n\n";

// 4. Tìm các import không sử dụng
echo "4️⃣ Tìm import không sử dụng...\n";

$unusedImports = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath . '/app'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/^use\s+([^;]+);/', trim($line), $matches)) {
                $import = $matches[1];
                $className = basename(str_replace('\\', '/', $import));
                
                // Kiểm tra xem class có được sử dụng trong file không
                $remainingContent = implode("\n", array_slice($lines, $lineNum + 1));
                if (strpos($remainingContent, $className) === false) {
                    $unusedImports[] = [
                        'file' => str_replace($basePath . '/', '', $file->getPathname()),
                        'line' => $lineNum + 1,
                        'import' => $import
                    ];
                }
            }
        }
    }
}

echo "   📊 Potentially unused imports: " . count($unusedImports) . "\n\n";

// 5. Tìm các route không sử dụng
echo "5️⃣ Tìm route không sử dụng...\n";

$allRoutes = [];
$usedRoutes = [];

// Lấy tất cả routes
$routes = [];
$routeFiles = ['routes/web.php', 'routes/api.php', 'routes/console.php'];
foreach ($routeFiles as $routeFile) {
    if (file_exists($basePath . '/' . $routeFile)) {
        $content = file_get_contents($basePath . '/' . $routeFile);
        if (preg_match_all('/Route::(get|post|put|patch|delete|options|any)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            foreach ($matches[2] as $route) {
                $allRoutes[] = $route;
            }
        }
    }
}

// Scan usage của routes
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php', 'js'])) {
        $content = file_get_contents($file->getPathname());
        
        foreach ($allRoutes as $route) {
            if (strpos($content, $route) !== false) {
                $usedRoutes[$route] = true;
            }
        }
    }
}

$unusedRoutes = array_diff($allRoutes, array_keys($usedRoutes));
echo "   📊 Total routes: " . count($allRoutes) . "\n";
echo "   📊 Used routes: " . count($usedRoutes) . "\n";
echo "   📊 Potentially unused: " . count($unusedRoutes) . "\n\n";

// 6. Tạo báo cáo chi tiết
echo "📋 BÁO CÁO CHI TIẾT:\n";
echo "==================\n\n";

if (!empty($unusedClasses)) {
    echo "🏷️ CLASS CÓ THỂ KHÔNG SỬ DỤNG:\n";
    foreach (array_slice($unusedClasses, 0, 10) as $class) {
        echo "   - {$class}\n";
    }
    if (count($unusedClasses) > 10) {
        echo "   ... và " . (count($unusedClasses) - 10) . " class khác\n";
    }
    echo "\n";
}

if (!empty($unusedMethods)) {
    echo "🔧 METHOD CÓ THỂ KHÔNG SỬ DỤNG:\n";
    foreach (array_slice($unusedMethods, 0, 10) as $method) {
        echo "   - {$method}\n";
    }
    if (count($unusedMethods) > 10) {
        echo "   ... và " . (count($unusedMethods) - 10) . " method khác\n";
    }
    echo "\n";
}

if (!empty($unusedImports)) {
    echo "📦 IMPORT CÓ THỂ KHÔNG SỬ DỤNG:\n";
    foreach (array_slice($unusedImports, 0, 10) as $import) {
        echo "   - {$import['file']}:{$import['line']} - {$import['import']}\n";
    }
    if (count($unusedImports) > 10) {
        echo "   ... và " . (count($unusedImports) - 10) . " import khác\n";
    }
    echo "\n";
}

if (!empty($unusedRoutes)) {
    echo "🛣️ ROUTE CÓ THỂ KHÔNG SỬ DỤNG:\n";
    foreach (array_slice($unusedRoutes, 0, 10) as $route) {
        echo "   - {$route}\n";
    }
    if (count($unusedRoutes) > 10) {
        echo "   ... và " . (count($unusedRoutes) - 10) . " route khác\n";
    }
    echo "\n";
}

// 7. Đề xuất dependencies có thể xóa
echo "📦 DEPENDENCIES CÓ THỂ XÓA:\n";
$suggestedRemovals = [
    'laravel/dusk' => 'Chỉ cần cho testing browser',
    'laravel/tinker' => 'Chỉ cần cho development',
    'fakerphp/faker' => 'Chỉ cần cho testing',
    'mockery/mockery' => 'Chỉ cần cho testing',
    'phpunit/phpunit' => 'Chỉ cần cho testing'
];

foreach ($suggestedRemovals as $package => $reason) {
    if (isset($requireDev[$package]) || isset($require[$package])) {
        echo "   - {$package}: {$reason}\n";
    }
}

echo "\n🎯 Hoàn thành phân tích PHASE 3!\n";
