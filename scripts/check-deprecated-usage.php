#!/usr/bin/env php
<?php

/**
 * Check Deprecated Usage Script
 * 
 * Scans codebase for usage of deprecated classes, methods, and services.
 * 
 * Usage:
 *   php scripts/check-deprecated-usage.php
 *   php scripts/check-deprecated-usage.php --strict  # Fail on any deprecated usage
 */

$strict = in_array('--strict', $argv);

$deprecatedItems = [
    // Deprecated Middleware
    'App\\Http\\Middleware\\SecurityHeadersMiddleware' => [
        'replacement' => 'App\\Http\\Middleware\\Unified\\UnifiedSecurityMiddleware',
        'since' => '2025-01-XX',
    ],
    'App\\Http\\Middleware\\EnhancedSecurityHeadersMiddleware' => [
        'replacement' => 'App\\Http\\Middleware\\Unified\\UnifiedSecurityMiddleware',
        'since' => '2025-01-XX',
    ],
    'App\\Http\\Middleware\\ProductionSecurityMiddleware' => [
        'replacement' => 'App\\Http\\Middleware\\Unified\\UnifiedSecurityMiddleware',
        'since' => '2025-01-XX',
    ],
    'App\\Http\\Middleware\\AdvancedSecurityMiddleware' => [
        'replacement' => 'App\\Http\\Middleware\\Unified\\UnifiedSecurityMiddleware (for basic security) or App\\Services\\AdvancedSecurityService (for advanced threat detection)',
        'since' => '2025-01-XX',
    ],
    
    // Deprecated Services (add as they are deprecated)
    'App\\Services\\KpiCacheService' => [
        'replacement' => 'App\\Services\\AdvancedCacheService',
        'since' => '2025-11-18',
    ],
];

$violations = [];
$baseDir = __DIR__ . '/..';

// Directories to scan
$scanDirs = [
    'app',
    'routes',
    'tests',
    'config',
];

// File extensions to check
$extensions = ['php', 'blade.php'];

echo "🔍 Scanning for deprecated usage...\n\n";

foreach ($deprecatedItems as $deprecated => $info) {
    $shortName = basename(str_replace('\\', '/', $deprecated));
    $found = false;
    
    foreach ($scanDirs as $dir) {
        $dirPath = $baseDir . '/' . $dir;
        if (!is_dir($dirPath)) {
            continue;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $ext = $file->getExtension();
            if (!in_array($ext, $extensions)) {
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
            
            // Escape backslashes for regex
            $deprecatedEscaped = preg_quote($deprecated, '/');
            $shortNameEscaped = preg_quote($shortName, '/');
            
            // Check for class usage
            $patterns = [
                "/use\s+{$deprecatedEscaped}/",
                "/{$deprecatedEscaped}::/",
                "/new\s+{$deprecatedEscaped}/",
                "/{$shortNameEscaped}::/",
                "/\\\\{$shortNameEscaped}/",
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $found = true;
                    $violations[] = [
                        'deprecated' => $deprecated,
                        'file' => $relativePath,
                        'replacement' => $info['replacement'],
                        'since' => $info['since'],
                    ];
                    break;
                }
            }
        }
    }
}

// Report results
if (empty($violations)) {
    echo "✅ No deprecated usage found!\n";
    exit(0);
}

echo "⚠️  Found " . count($violations) . " deprecated usage(s):\n\n";

$grouped = [];
foreach ($violations as $violation) {
    $key = $violation['deprecated'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = [];
    }
    $grouped[$key][] = $violation;
}

foreach ($grouped as $deprecated => $items) {
    $info = $deprecatedItems[$deprecated];
    echo "📦 {$deprecated}\n";
    echo "   Deprecated since: {$info['since']}\n";
    echo "   Replacement: {$info['replacement']}\n";
    echo "   Found in " . count($items) . " file(s):\n";
    
    foreach ($items as $item) {
        echo "   - {$item['file']}\n";
    }
    echo "\n";
}

if ($strict) {
    echo "❌ Strict mode: Failing due to deprecated usage.\n";
    exit(1);
}

echo "💡 Run with --strict to fail on deprecated usage.\n";
exit(0);

