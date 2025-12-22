#!/usr/bin/env php
<?php

/**
 * Blade Views Audit Script
 * 
 * Scans Blade views for prohibited patterns:
 * - Service calls (App\Services\*)
 * - Model queries (App\Models\*::query())
 * - Business logic (calculations, validations)
 * - Database writes (Model::create(), Model::update())
 * 
 * Usage:
 *   php scripts/audit-blade-views.php
 *   php scripts/audit-blade-views.php --fix  # Attempt to fix violations (not implemented)
 */

$fix = in_array('--fix', $argv);
$baseDir = __DIR__ . '/..';
$viewsDir = $baseDir . '/resources/views';

$violations = [];

// Patterns to detect
$patterns = [
    'service_calls' => [
        'pattern' => '/App\\\\Services\\\\\w+/',
        'description' => 'Direct service calls',
        'severity' => 'error',
    ],
    'model_queries' => [
        'pattern' => '/App\\\\Models\\\\\w+::query\(\)/',
        'description' => 'Direct model queries',
        'severity' => 'error',
    ],
    'model_static_calls' => [
        'pattern' => '/(\w+)::(create|update|delete|where|find|all|get|first)\(/',
        'description' => 'Model static method calls (create, update, delete, etc.)',
        'severity' => 'error',
    ],
    'business_logic' => [
        'patterns' => [
            '/if\s*\([^)]*\$[^)]*(?:count|sum|avg|max|min|total)/',
            '/\$[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*[^=]*(?:\+|\-|\*|\/|\%)/',
            '/function\s+\w+\s*\([^)]*\)\s*\{[^}]*return/',
        ],
        'description' => 'Business logic (calculations, validations)',
        'severity' => 'warning',
    ],
    'event_dispatching' => [
        'pattern' => '/(event\(|Event::dispatch\(|dispatch\(|broadcast\()/',
        'description' => 'Event dispatching',
        'severity' => 'error',
    ],
    'database_writes' => [
        'pattern' => '/->(create|update|delete|save|destroy)\(/',
        'description' => 'Database write operations',
        'severity' => 'error',
    ],
];

echo "🔍 Auditing Blade views...\n\n";

if (!is_dir($viewsDir)) {
    echo "❌ Views directory not found: {$viewsDir}\n";
    exit(1);
}

// Scan app and admin views
$scanDirs = ['app', 'admin'];

foreach ($scanDirs as $dir) {
    $dirPath = $viewsDir . '/' . $dir;
    if (!is_dir($dirPath)) {
        continue;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath)
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        
        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
        
        // Skip if it's a component (components are allowed to have some logic)
        if (str_contains($relativePath, 'components/')) {
            continue;
        }
        
        // Check each pattern
        foreach ($patterns as $type => $config) {
            if (isset($config['pattern'])) {
                // Single pattern
                if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = [
                            'file' => $relativePath,
                            'type' => $type,
                            'description' => $config['description'],
                            'severity' => $config['severity'],
                            'line' => $line,
                            'match' => $match[0],
                        ];
                    }
                }
            } elseif (isset($config['patterns'])) {
                // Multiple patterns
                foreach ($config['patterns'] as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                            $violations[] = [
                                'file' => $relativePath,
                                'type' => $type,
                                'description' => $config['description'],
                                'severity' => $config['severity'],
                                'line' => $line,
                                'match' => $match[0],
                            ];
                        }
                    }
                }
            }
        }
    }
}

// Report results
if (empty($violations)) {
    echo "✅ No violations found in Blade views!\n";
    exit(0);
}

echo "⚠️  Found " . count($violations) . " violation(s):\n\n";

// Group by file
$grouped = [];
foreach ($violations as $violation) {
    $file = $violation['file'];
    if (!isset($grouped[$file])) {
        $grouped[$file] = [];
    }
    $grouped[$file][] = $violation;
}

// Count by severity
$errorCount = 0;
$warningCount = 0;

foreach ($grouped as $file => $items) {
    echo "📄 {$file}\n";
    
    foreach ($items as $item) {
        $icon = $item['severity'] === 'error' ? '❌' : '⚠️';
        echo "   {$icon} Line {$item['line']}: {$item['description']}\n";
        echo "      Match: {$item['match']}\n";
        
        if ($item['severity'] === 'error') {
            $errorCount++;
        } else {
            $warningCount++;
        }
    }
    echo "\n";
}

echo "Summary:\n";
echo "  Errors: {$errorCount}\n";
echo "  Warnings: {$warningCount}\n";
echo "  Total: " . count($violations) . "\n\n";

if ($errorCount > 0) {
    echo "❌ Found {$errorCount} error(s). Blade views should not contain:\n";
    echo "   - Service calls\n";
    echo "   - Model queries\n";
    echo "   - Database writes\n";
    echo "   - Event dispatching\n\n";
    echo "💡 All business logic should be moved to API endpoints.\n";
    exit(1);
}

exit(0);

