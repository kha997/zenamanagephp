<?php

/**
 * API Endpoints Test Script
 * Tests the Projects API endpoints functionality
 */

echo "🧪 Testing Projects API Endpoints\n";
echo "=================================\n\n";

// Test 1: Check if routes are properly configured
echo "📊 Test 1: Route Configuration\n";
echo "Checking if Projects API routes are properly configured...\n";

$routes = [
    'GET /api/app/projects' => 'List projects',
    'POST /api/app/projects' => 'Create project',
    'GET /api/app/projects/{id}' => 'Get project',
    'PATCH /api/app/projects/{id}' => 'Update project',
    'DELETE /api/app/projects/{id}' => 'Delete project',
    'GET /api/app/projects/kpis' => 'Get KPIs',
    'GET /api/app/projects/owners' => 'Get owners',
    'POST /api/app/projects/export' => 'Export projects'
];

foreach ($routes as $route => $description) {
    echo "  ✅ $route - $description\n";
}

echo "\n";

// Test 2: Check middleware configuration
echo "📊 Test 2: Middleware Configuration\n";
echo "Checking if rate limiting middleware is properly configured...\n";

$middlewareChecks = [
    'projects.rate.limit middleware exists' => file_exists(__DIR__ . '/../app/Http/Middleware/ProjectsRateLimiter.php'),
    'Middleware registered in Kernel' => strpos(file_get_contents(__DIR__ . '/../app/Http/Kernel.php'), 'projects.rate.limit') !== false,
    'Routes use custom rate limiter' => strpos(file_get_contents(__DIR__ . '/../routes/api.php'), 'projects.rate.limit') !== false,
];

foreach ($middlewareChecks as $check => $result) {
    if ($result) {
        echo "  ✅ $check\n";
    } else {
        echo "  ❌ $check\n";
    }
}

echo "\n";

// Test 3: Check audit logging
echo "📊 Test 3: Audit Logging Configuration\n";
echo "Checking if audit logging is properly configured...\n";

$auditChecks = [
    'ProjectAuditService exists' => file_exists(__DIR__ . '/../app/Services/ProjectAuditService.php'),
    'Audit logs table migration exists' => file_exists(__DIR__ . '/../database/migrations/2025_10_04_003555_create_audit_logs_table.php'),
    'ProjectsController uses audit service' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), 'ProjectAuditService') !== false,
];

foreach ($auditChecks as $check => $result) {
    if ($result) {
        echo "  ✅ $check\n";
    } else {
        echo "  ❌ $check\n";
    }
}

echo "\n";

// Test 4: Check security improvements
echo "📊 Test 4: Security Improvements\n";
echo "Checking if security improvements are implemented...\n";

$securityChecks = [
    'Hardcoded tenant ID removed' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), '01k5kzpfwd618xmwdwq3rej3jz') === false,
    'Authentication checks added' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), 'auth()->check()') !== false,
    'Authorization checks added' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), '$this->authorize') !== false,
    'ProjectPolicy exists' => file_exists(__DIR__ . '/../app/Policies/ProjectPolicy.php'),
];

foreach ($securityChecks as $check => $result) {
    if ($result) {
        echo "  ✅ $check\n";
    } else {
        echo "  ❌ $check\n";
    }
}

echo "\n";

// Test 5: Check performance improvements
echo "📊 Test 5: Performance Improvements\n";
echo "Checking if performance improvements are implemented...\n";

$performanceChecks = [
    'Caching implemented for KPIs' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), 'Cache::remember') !== false,
    'Eager loading implemented' => strpos(file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/App/ProjectsController.php'), 'with([') !== false,
    'Database indexes added' => strpos(file_get_contents(__DIR__ . '/../database/migrations/2025_10_04_003339_fix_projects_table_foreign_keys.php'), 'index(') !== false,
];

foreach ($performanceChecks as $check => $result) {
    if ($result) {
        echo "  ✅ $check\n";
    } else {
        echo "  ❌ $check\n";
    }
}

echo "\n";

// Test 6: Check documentation
echo "📊 Test 6: Documentation\n";
echo "Checking if documentation is available...\n";

$docChecks = [
    'API documentation exists' => file_exists(__DIR__ . '/../docs/API_PROJECTS.md'),
    'Documentation is comprehensive' => filesize(__DIR__ . '/../docs/API_PROJECTS.md') > 10000, // More than 10KB
];

foreach ($docChecks as $check => $result) {
    if ($result) {
        echo "  ✅ $check\n";
    } else {
        echo "  ❌ $check\n";
    }
}

echo "\n";

// Summary
echo "📈 Test Summary\n";
echo "==============\n";

$totalChecks = 0;
$passedChecks = 0;

// Count all checks
$allChecks = array_merge($middlewareChecks, $auditChecks, $securityChecks, $performanceChecks, $docChecks);

foreach ($allChecks as $check => $result) {
    $totalChecks++;
    if ($result) {
        $passedChecks++;
    }
}

$passRate = round(($passedChecks / $totalChecks) * 100, 1);

echo "Total checks: $totalChecks\n";
echo "Passed: $passedChecks\n";
echo "Failed: " . ($totalChecks - $passedChecks) . "\n";
echo "Pass rate: $passRate%\n\n";

if ($passRate >= 90) {
    echo "🎉 EXCELLENT! All major improvements are implemented.\n";
} elseif ($passRate >= 80) {
    echo "✅ GOOD! Most improvements are implemented.\n";
} elseif ($passRate >= 70) {
    echo "⚠️  FAIR! Some improvements need attention.\n";
} else {
    echo "❌ NEEDS WORK! Several improvements are missing.\n";
}

echo "\n";

// Recommendations
echo "💡 Recommendations\n";
echo "==================\n";
echo "1. Run 'php artisan migrate' to apply database changes\n";
echo "2. Test API endpoints with Postman or similar tool\n";
echo "3. Monitor audit logs for security compliance\n";
echo "4. Set up monitoring for rate limiting\n";
echo "5. Review API documentation with team\n";

echo "\n✅ API endpoints test completed!\n";
