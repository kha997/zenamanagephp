#!/usr/bin/env php
<?php

/**
 * Architecture Improvements Verification Script
 * 
 * Verifies that all architecture improvements have been properly implemented.
 * 
 * Usage:
 *   php scripts/verify-architecture-improvements.php
 */

$baseDir = __DIR__ . '/..';
$errors = [];
$warnings = [];
$success = [];

echo "🔍 Verifying architecture improvements...\n\n";

// 1. Check UnifiedSecurityMiddleware is used in Kernel.php
echo "1. Checking UnifiedSecurityMiddleware in Kernel.php...\n";
$kernelContent = file_get_contents($baseDir . '/app/Http/Kernel.php');
if (strpos($kernelContent, 'UnifiedSecurityMiddleware') !== false) {
    $success[] = "UnifiedSecurityMiddleware is used in Kernel.php";
    echo "   ✅ UnifiedSecurityMiddleware found in Kernel.php\n";
} else {
    $errors[] = "UnifiedSecurityMiddleware not found in Kernel.php";
    echo "   ❌ UnifiedSecurityMiddleware not found in Kernel.php\n";
}

// 2. Check deprecated middleware are marked @deprecated
echo "\n2. Checking deprecated middleware are marked @deprecated...\n";
$deprecatedMiddleware = [
    'app/Http/Middleware/SecurityHeadersMiddleware.php',
    'app/Http/Middleware/EnhancedSecurityHeadersMiddleware.php',
    'app/Http/Middleware/ProductionSecurityMiddleware.php',
    'app/Http/Middleware/AdvancedSecurityMiddleware.php',
];

foreach ($deprecatedMiddleware as $file) {
    $path = $baseDir . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (strpos($content, '@deprecated') !== false) {
            $success[] = "{$file} is marked @deprecated";
            echo "   ✅ {$file} is marked @deprecated\n";
        } else {
            $warnings[] = "{$file} exists but not marked @deprecated";
            echo "   ⚠️  {$file} exists but not marked @deprecated\n";
        }
    } else {
        $warnings[] = "{$file} not found (may have been removed)";
        echo "   ⚠️  {$file} not found\n";
    }
}

// 3. Check test suites quick/full exist in phpunit.xml
echo "\n3. Checking test suites quick/full in phpunit.xml...\n";
$phpunitContent = file_get_contents($baseDir . '/phpunit.xml');
if (strpos($phpunitContent, 'testsuite name="quick"') !== false) {
    $success[] = "Quick test suite exists in phpunit.xml";
    echo "   ✅ Quick test suite found\n";
} else {
    $errors[] = "Quick test suite not found in phpunit.xml";
    echo "   ❌ Quick test suite not found\n";
}

if (strpos($phpunitContent, 'testsuite name="full"') !== false) {
    $success[] = "Full test suite exists in phpunit.xml";
    echo "   ✅ Full test suite found\n";
} else {
    $errors[] = "Full test suite not found in phpunit.xml";
    echo "   ❌ Full test suite not found\n";
}

// 4. Check scripts exist and are executable
echo "\n4. Checking scripts exist...\n";
$scripts = [
    'scripts/check-deprecated-usage.php',
    'scripts/audit-blade-views.php',
    'scripts/check-secrets.php',
    'scripts/track-flaky-tests.php',
];

foreach ($scripts as $script) {
    $path = $baseDir . '/' . $script;
    if (file_exists($path)) {
        $success[] = "{$script} exists";
        echo "   ✅ {$script} exists\n";
        if (is_executable($path)) {
            echo "      ✅ {$script} is executable\n";
        } else {
            $warnings[] = "{$script} is not executable";
            echo "      ⚠️  {$script} is not executable\n";
        }
    } else {
        $errors[] = "{$script} not found";
        echo "   ❌ {$script} not found\n";
    }
}

// 5. Check workflows exist
echo "\n5. Checking workflows exist...\n";
$workflows = [
    '.github/workflows/dependency-review.yml',
    '.github/workflows/secret-scan.yml',
    '.github/workflows/architecture-lint.yml',
];

foreach ($workflows as $workflow) {
    $path = $baseDir . '/' . $workflow;
    if (file_exists($path)) {
        $success[] = "{$workflow} exists";
        echo "   ✅ {$workflow} exists\n";
    } else {
        $errors[] = "{$workflow} not found";
        echo "   ❌ {$workflow} not found\n";
    }
}

// 6. Check documentation exists
echo "\n6. Checking documentation exists...\n";
$docs = [
    'docs/ARCHITECTURE_LAYERING_GUIDE.md',
    'docs/MIDDLEWARE_CONSOLIDATION.md',
    'docs/WEBSOCKET_ARCHITECTURE.md',
    'docs/context/tasks/README.md',
    'docs/context/projects/README.md',
    'docs/context/documents/README.md',
    'docs/context/dashboard/README.md',
    'docs/context/auth/README.md',
];

foreach ($docs as $doc) {
    $path = $baseDir . '/' . $doc;
    if (file_exists($path)) {
        $success[] = "{$doc} exists";
        echo "   ✅ {$doc} exists\n";
    } else {
        $warnings[] = "{$doc} not found";
        echo "   ⚠️  {$doc} not found\n";
    }
}

// 7. Check WebSocket metrics controller exists
echo "\n7. Checking WebSocket metrics controller...\n";
$wsController = $baseDir . '/app/Http/Controllers/Api/V1/Metrics/WebSocketMetricsController.php';
if (file_exists($wsController)) {
    $success[] = "WebSocketMetricsController exists";
    echo "   ✅ WebSocketMetricsController exists\n";
} else {
    $errors[] = "WebSocketMetricsController not found";
    echo "   ❌ WebSocketMetricsController not found\n";
}

// 8. Check CacheInvalidationService has convenience methods
echo "\n8. Checking CacheInvalidationService...\n";
$cacheService = $baseDir . '/app/Services/CacheInvalidationService.php';
if (file_exists($cacheService)) {
    $content = file_get_contents($cacheService);
    $methods = ['forTaskUpdate', 'forProjectUpdate', 'forDocumentUpdate', 'forUserUpdate'];
    $foundMethods = [];
    foreach ($methods as $method) {
        if (strpos($content, "function {$method}") !== false) {
            $foundMethods[] = $method;
        }
    }
    if (count($foundMethods) === count($methods)) {
        $success[] = "CacheInvalidationService has all convenience methods";
        echo "   ✅ CacheInvalidationService has all convenience methods\n";
    } else {
        $missing = array_diff($methods, $foundMethods);
        $warnings[] = "CacheInvalidationService missing methods: " . implode(', ', $missing);
        echo "   ⚠️  CacheInvalidationService missing methods: " . implode(', ', $missing) . "\n";
    }
} else {
    $errors[] = "CacheInvalidationService not found";
    echo "   ❌ CacheInvalidationService not found\n";
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ Success: " . count($success) . " checks passed\n";
if (!empty($warnings)) {
    echo "⚠️  Warnings: " . count($warnings) . " issues found\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
}
if (!empty($errors)) {
    echo "❌ Errors: " . count($errors) . " critical issues found\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    exit(1);
}

if (empty($errors) && empty($warnings)) {
    echo "\n🎉 All architecture improvements verified successfully!\n";
    exit(0);
} elseif (empty($errors)) {
    echo "\n✅ All critical checks passed. Some warnings found.\n";
    exit(0);
} else {
    exit(1);
}

