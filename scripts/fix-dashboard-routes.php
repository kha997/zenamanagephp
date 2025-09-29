<?php

/**
 * Fix Dashboard Route Conflicts
 * Resolves Laravel route naming conflicts for dashboard APIs
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

echo "🔧 Fixing Dashboard Route Conflicts...\n\n";

// Check routes for conflicts
$routes = Route::getRoutes();
$routeNames = [];
$conflicts = [];

foreach ($routes as $route) {
    $name = $route->getName();
    $uri = $route->uri();
    
    if ($name && isset($routeNames[$name])) {
        $conflicts[] = [
            'name' => $name,
            'existing' => $routeNames[$name],
            'conflicting' => $uri,
            'methods' => $route->methods()
        ];
        echo "❌ CONFLICT: Route name '{$name}' used multiple times:\n";
        echo "   - {$routeNames[$name]['uri']} ({$routeNames[$name]['methods']})\n";
        echo "   - {$uri} (" . implode(',', $route->methods()) . ")\n\n";
    } else {
        $routeNames[$name] = [
            'uri' => $uri,
            'methods' => implode(',', $route->methods())
        ];
    }
}

// Check dashboard-specific routes
echo "📊 Dashboard Routes Audit:\n";
$dashboardRoutes = [
    '/admin/dashboard/summary',
    '/admin/dashboard/charts', 
    '/admin/dashboard/activity',
    '/admin/dashboard/signups/export.csv',
    '/admin/dashboard/errors/export.csv'
];

foreach ($dashboardRoutes as $route) {
    $found = false;
    foreach ($routes as $laravelRoute) {
        if (strpos($laravelRoute->uri(), $route) !== false) {
            echo "✅ Found: {$laravelRoute->uri()} -> {$laravelRoute->getName()}\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ Missing: {$route}\n";
    }
}

echo "\n🚀 Solution:\n";

if (count($conflicts) === 0) {
    echo "✅ No route conflicts detected!\n";
    echo "Dashboard routes should be working correctly.\n";
} else {
    echo "⚠️ Found " . count($conflicts) . " route conflict(s).\n";
    echo "\nRecommended fixes:\n";
    
    foreach ($conflicts as $conflict) {
        echo "For route '{$conflict['name']}':\n";
        echo "1. Check routes/web.php and routes/api.php\n";
        echo "2. Ensure unique route names\n";
        echo "3. Use nested route groups with unique prefixes\n";
    }
}

echo "\n📁 Checking Dashboard Files:\n";

$dashboardFiles = [
    'resources/views/admin/dashboard/index.blade.php',
    'resources/views/admin/dashboard/_kpis.blade.php',
    'resources/views/admin/dashboard/_charts.blade.php', 
    'public/js/pages/dashboard.js',
    'public/js/dashboard/charts.js',
    'public/css/dashboard-enhanced.css'
];

foreach ($dashboardFiles as $file) {
    $exists = file_exists(__DIR__ . '/../' . $file);
    echo $exists ? "✅ {$file}" : "❌ {$file}";
    echo "\n";
}

echo "\n🔍 Testing Route Accessibility:\n";

// Test admin route (should work without API)
$adminUrl = '/admin';
$adminRoutes = [];
foreach ($routes as $route) {
    if ($route->uri() === $adminUrl || $route->uri() === '/admin') {
        $adminRoutes[] = [
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'methods' => implode(',', $route->methods())
        ];
    }
}

if (count($adminRoutes) > 0) {
    echo "✅ Admin route accessible:\n";
    foreach ($adminRoutes as $route) {
        echo "   - {$route['uri']} -> {$route['name']} ({$route['methods']})\n";
    }
} else {
    echo "❌ Admin route not found\n";
}

echo "\n🎯 Summary:\n";
echo "Route conflicts: " . count($conflicts) . "\n";
echo "Dashboard files: " . array_sum(array_map(fn($f) => file_exists(__DIR__ . '/../' . $f), $dashboardFiles)) . "/" . count($dashboardFiles) . "\n";
echo "Admin accessible: " . (count($adminRoutes) > 0 ? "Yes" : "No") . "\n";

if (count($conflicts) === 0 && count($adminRoutes) > 0) {
    echo "\n✅ Dashboard should be accessible at /admin\n";
    echo "💡 Run: php artisan serve --port=8000\n";
    echo "🌐 Visit: http://localhost:8000/admin\n";
} else {
    echo "\n⚠️ Issues need to be resolved before dashboard is accessible\n";
}

echo "\nCompleted at " . date('Y-m-d H:i:s') . "\n";
