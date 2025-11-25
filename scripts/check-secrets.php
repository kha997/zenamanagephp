#!/usr/bin/env php
<?php

/**
 * Secret Scanning Script
 * 
 * Scans codebase for potential secrets and sensitive data.
 * 
 * Usage:
 *   php scripts/check-secrets.php
 *   php scripts/check-secrets.php --strict  # Fail on any secrets found
 */

$strict = in_array('--strict', $argv);
$baseDir = __DIR__ . '/..';

$violations = [];
$patterns = [
    'api_key' => [
        'pattern' => '/(api[_-]?key|apikey)\s*[=:]\s*["\']?([a-zA-Z0-9]{20,})["\']?/i',
        'severity' => 'error',
        'description' => 'API key found',
    ],
    'secret' => [
        'pattern' => '/(secret|password|pwd|passwd)\s*[=:]\s*["\']?([^"\'\s]{8,})["\']?/i',
        'severity' => 'error',
        'description' => 'Secret/password found',
    ],
    'token' => [
        'pattern' => '/(token|bearer)\s*[=:]\s*["\']?([a-zA-Z0-9]{32,})["\']?/i',
        'severity' => 'warning',
        'description' => 'Potential token found',
    ],
    'private_key' => [
        'pattern' => '/-----BEGIN\s+(RSA\s+)?PRIVATE\s+KEY-----/i',
        'severity' => 'error',
        'description' => 'Private key found',
    ],
    'aws_key' => [
        'pattern' => '/(aws[_-]?(access[_-]?key|secret[_-]?key))\s*[=:]\s*["\']?([A-Z0-9]{20,})["\']?/i',
        'severity' => 'error',
        'description' => 'AWS credentials found',
    ],
    'database_password' => [
        'pattern' => '/DB_PASSWORD\s*[=:]\s*["\']?([^"\'\s]{8,})["\']?/i',
        'severity' => 'error',
        'description' => 'Database password found',
    ],
    'env_file' => [
        'pattern' => '/\.env$/',
        'severity' => 'error',
        'description' => '.env file should not be committed',
    ],
];

$scanDirs = ['app', 'config', 'routes', 'database', 'tests'];
$excludeDirs = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache'];

echo "🔍 Scanning for secrets and sensitive data...\n\n";

foreach ($scanDirs as $dir) {
    $dirPath = $baseDir . '/' . $dir;
    if (!is_dir($dirPath)) {
        continue;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        
        $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
        
        // Skip excluded directories
        $shouldExclude = false;
        foreach ($excludeDirs as $exclude) {
            if (str_contains($relativePath, $exclude)) {
                $shouldExclude = true;
                break;
            }
        }
        if ($shouldExclude) {
            continue;
        }
        
        // Check for .env files
        if (preg_match('/\.env$/', $relativePath)) {
            $violations[] = [
                'file' => $relativePath,
                'type' => 'env_file',
                'description' => '.env file should not be committed',
                'severity' => 'error',
                'line' => 1,
            ];
            continue;
        }
        
        // Skip binary files
        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'js', 'ts', 'json', 'yaml', 'yml', 'env', 'sh'])) {
            continue;
        }
        
        $content = file_get_contents($file->getPathname());
        
        // Check each pattern
        foreach ($patterns as $type => $config) {
            if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    // Skip if it's a comment or example
                    $lineContent = explode("\n", $content)[$line - 1] ?? '';
                    if (preg_match('/^\s*(\/\/|\#|\*)/', $lineContent)) {
                        continue;
                    }
                    
                    // Skip if it's a variable name (not a value)
                    if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*\s*[=:]/', $lineContent)) {
                        continue;
                    }
                    
                    $violations[] = [
                        'file' => $relativePath,
                        'type' => $type,
                        'description' => $config['description'],
                        'severity' => $config['severity'],
                        'line' => $line,
                        'match' => substr($match[0], 0, 50) . '...',
                    ];
                }
            }
        }
    }
}

// Report results
if (empty($violations)) {
    echo "✅ No secrets found!\n";
    exit(0);
}

echo "⚠️  Found " . count($violations) . " potential secret(s):\n\n";

$errorCount = 0;
$warningCount = 0;

foreach ($violations as $violation) {
    $icon = $violation['severity'] === 'error' ? '❌' : '⚠️';
    echo "{$icon} {$violation['file']}:{$violation['line']}\n";
    echo "   {$violation['description']}\n";
    echo "   Match: {$violation['match']}\n\n";
    
    if ($violation['severity'] === 'error') {
        $errorCount++;
    } else {
        $warningCount++;
    }
}

echo "Summary:\n";
echo "  Errors: {$errorCount}\n";
echo "  Warnings: {$warningCount}\n";
echo "  Total: " . count($violations) . "\n\n";

if ($errorCount > 0) {
    echo "❌ Found {$errorCount} error(s). Secrets should not be committed to repository.\n";
    echo "💡 Use environment variables or secrets manager instead.\n";
    
    if ($strict) {
        exit(1);
    }
}

exit(0);

