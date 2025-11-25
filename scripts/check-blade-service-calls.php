#!/usr/bin/env php
<?php

/**
 * Blade Service Call Checker
 * 
 * Checks Blade views for prohibited service calls (part of Blade deprecation plan - ADR-002).
 * 
 * Prohibited patterns:
 * - App\Services\*::method()
 * - app(App\Services\*::class)
 * - resolve(App\Services\*::class)
 * - Direct model queries: Model::query(), Model::find(), etc.
 * 
 * Usage: php scripts/check-blade-service-calls.php [--fix]
 */

$viewsPath = __DIR__ . '/../resources/views';
$errors = [];
$warnings = [];

// Patterns to check (regex)
$prohibitedPatterns = [
    // Service calls
    '/App\\\\Services\\\\[A-Za-z]+Service::/',
    '/app\(App\\\\Services\\\\[A-Za-z]+Service::class\)/',
    '/resolve\(App\\\\Services\\\\[A-Za-z]+Service::class\)/',
    
    // Model queries (direct)
    '/App\\\\Models\\\\[A-Za-z]+::query\(\)/',
    '/App\\\\Models\\\\[A-Za-z]+::find\(/',
    '/App\\\\Models\\\\[A-Za-z]+::findOrFail\(/',
    '/App\\\\Models\\\\[A-Za-z]+::create\(/',
    '/App\\\\Models\\\\[A-Za-z]+::update\(/',
    '/App\\\\Models\\\\[A-Za-z]+::delete\(/',
    '/App\\\\Models\\\\[A-Za-z]+::save\(/',
    
    // Event dispatching
    '/event\(/',
    '/Event::dispatch\(/',
    '/Illuminate\\\\Support\\\\Facades\\\\Event::dispatch\(/',
];

// Allowed patterns (exceptions)
$allowedPatterns = [
    // HeaderService is allowed (it's a view helper, not business logic)
    '/App\\\\Services\\\\HeaderService::class/',
    
    // Auth facade is allowed (authentication, not business logic)
    '/Auth::/',
    
    // Config, Route, etc. are allowed (framework helpers)
    '/config\(/',
    '/route\(/',
    '/url\(/',
    '/asset\(/',
];

function checkFile($filePath, $patterns, $allowedPatterns) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $issues = [];
    
    foreach ($lines as $lineNum => $line) {
        // Skip comments
        if (preg_match('/^\s*{{--.*--}}\s*$/', $line)) {
            continue;
        }
        
        // Check each prohibited pattern
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                // Check if it's an allowed exception
                $isAllowed = false;
                foreach ($allowedPatterns as $allowed) {
                    if (preg_match($allowed, $line)) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    $issues[] = [
                        'line' => $lineNum + 1,
                        'content' => trim($line),
                        'pattern' => $pattern,
                    ];
                }
            }
        }
    }
    
    return $issues;
}

function scanDirectory($dir, $patterns, $allowedPatterns) {
    $issues = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), '.blade.php') !== false) {
            $filePath = $file->getPathname();
            $fileIssues = checkFile($filePath, $patterns, $allowedPatterns);
            
            if (!empty($fileIssues)) {
                $issues[$filePath] = $fileIssues;
            }
        }
    }
    
    return $issues;
}

// Main execution
echo "🔍 Checking Blade views for prohibited service calls...\n\n";

$allIssues = scanDirectory($viewsPath, $prohibitedPatterns, $allowedPatterns);

if (empty($allIssues)) {
    echo "✅ No prohibited service calls found in Blade views.\n";
    exit(0);
}

// Report issues
$totalIssues = 0;
foreach ($allIssues as $filePath => $issues) {
    $relativePath = str_replace(__DIR__ . '/../', '', $filePath);
    echo "❌ {$relativePath}\n";
    
    foreach ($issues as $issue) {
        echo "   Line {$issue['line']}: {$issue['content']}\n";
        $totalIssues++;
    }
    echo "\n";
}

echo "⚠️  Found {$totalIssues} prohibited service call(s) in Blade views.\n";
echo "📖 See ADR-002 (docs/architecture/decisions/002-blade-deprecation.md) for details.\n";
echo "\n";
echo "Allowed patterns:\n";
echo "  - HeaderService (view helper)\n";
echo "  - Auth facade (authentication)\n";
echo "  - Config, Route, URL helpers (framework)\n";
echo "\n";

exit(1);

