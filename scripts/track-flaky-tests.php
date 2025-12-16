#!/usr/bin/env php
<?php

/**
 * Flaky Test Tracking Script
 * 
 * Parses test results from CI and identifies flaky tests.
 * 
 * Usage:
 *   php scripts/track-flaky-tests.php [test-results-file]
 *   php scripts/track-flaky-tests.php --update-docs
 */

$updateDocs = in_array('--update-docs', $argv);
$testResultsFile = $argv[1] ?? 'storage/app/junit.xml';

if (!file_exists($testResultsFile)) {
    echo "❌ Test results file not found: {$testResultsFile}\n";
    echo "💡 Run tests first: php artisan test --log-junit=storage/app/junit.xml\n";
    exit(1);
}

echo "🔍 Analyzing test results for flaky tests...\n\n";

// Parse JUnit XML
$xml = simplexml_load_file($testResultsFile);
if (!$xml) {
    echo "❌ Failed to parse test results XML\n";
    exit(1);
}

$flakyTests = [];
$testHistory = [];

// Load existing flaky test history
$historyFile = 'docs/FLAKY_TESTS.md';
if (file_exists($historyFile)) {
    $historyContent = file_get_contents($historyFile);
    // Parse existing flaky tests from markdown
    preg_match_all('/- `([^`]+)` - (.*)/', $historyContent, $matches);
    for ($i = 0; $i < count($matches[1]); $i++) {
        $testName = $matches[1][$i];
        $reason = $matches[2][$i];
        $testHistory[$testName] = [
            'reason' => $reason,
            'count' => 1,
        ];
    }
}

// Analyze test results
foreach ($xml->testsuite as $testsuite) {
    foreach ($testsuite->testcase as $testcase) {
        $testName = (string) $testcase['name'];
        $className = (string) $testcase['classname'];
        $fullName = "{$className}::{$testName}";
        
        // Check for failures
        $hasFailure = isset($testcase->failure) || isset($testcase->error);
        $hasSkipped = isset($testcase->skipped);
        
        if ($hasFailure && !$hasSkipped) {
            $failureMessage = '';
            if (isset($testcase->failure)) {
                $failureMessage = (string) $testcase->failure;
            } elseif (isset($testcase->error)) {
                $failureMessage = (string) $testcase->error;
            }
            
            // Check for flaky test patterns
            $isFlaky = $this->isFlakyPattern($failureMessage);
            
            if ($isFlaky) {
                if (!isset($flakyTests[$fullName])) {
                    $flakyTests[$fullName] = [
                        'class' => $className,
                        'method' => $testName,
                        'reason' => $this->extractFlakyReason($failureMessage),
                        'count' => 0,
                    ];
                }
                $flakyTests[$fullName]['count']++;
                
                // Update history
                if (!isset($testHistory[$fullName])) {
                    $testHistory[$fullName] = [
                        'reason' => $flakyTests[$fullName]['reason'],
                        'count' => 0,
                    ];
                }
                $testHistory[$fullName]['count']++;
            }
        }
    }
}

// Report results
if (empty($flakyTests)) {
    echo "✅ No flaky tests detected in this run!\n";
} else {
    echo "⚠️  Found " . count($flakyTests) . " flaky test(s):\n\n";
    
    foreach ($flakyTests as $testName => $info) {
        echo "📦 {$testName}\n";
        echo "   Reason: {$info['reason']}\n";
        echo "   Failures in this run: {$info['count']}\n";
        echo "   Total failures: " . ($testHistory[$testName]['count'] ?? $info['count']) . "\n\n";
    }
}

// Update documentation if requested
if ($updateDocs && !empty($testHistory)) {
    $this->updateFlakyTestsDoc($testHistory);
    echo "📝 Updated docs/FLAKY_TESTS.md\n";
}

exit(0);

/**
 * Check if failure message indicates flaky test
 */
function isFlakyPattern(string $message): bool
{
    $flakyPatterns = [
        '/timeout/i',
        '/timed out/i',
        '/connection.*refused/i',
        '/network.*error/i',
        '/socket.*error/i',
        '/deadlock/i',
        '/race condition/i',
        '/timing/i',
        '/random/i',
        '/non-deterministic/i',
        '/websocket.*not available/i',
        '/server.*not running/i',
    ];
    
    foreach ($flakyPatterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Extract reason for flaky test
 */
function extractFlakyReason(string $message): string
{
    if (preg_match('/timeout|timed out/i', $message)) {
        return 'Timing/Timeout issue';
    }
    if (preg_match('/connection|network|socket/i', $message)) {
        return 'Network/Connection issue';
    }
    if (preg_match('/websocket|server.*not running/i', $message)) {
        return 'WebSocket server not available';
    }
    if (preg_match('/deadlock|race condition/i', $message)) {
        return 'Concurrency issue';
    }
    
    return 'Unknown flaky pattern';
}

/**
 * Update flaky tests documentation
 */
function updateFlakyTestsDoc(array $testHistory): void
{
    $content = "# Flaky Tests Tracking\n\n";
    $content .= "**Last Updated**: " . date('Y-m-d H:i:s') . "\n\n";
    $content .= "This document tracks flaky tests that fail intermittently due to timing, network, or concurrency issues.\n\n";
    $content .= "## Flaky Tests\n\n";
    
    if (empty($testHistory)) {
        $content .= "No flaky tests currently tracked.\n";
    } else {
        // Sort by failure count (descending)
        uasort($testHistory, fn($a, $b) => $b['count'] <=> $a['count']);
        
        foreach ($testHistory as $testName => $info) {
            $content .= "- `{$testName}` - {$info['reason']} (Failed {$info['count']} time(s))\n";
        }
    }
    
    $content .= "\n## How to Fix Flaky Tests\n\n";
    $content .= "1. **Timing Issues**: Add proper waits/sleeps or use test doubles\n";
    $content .= "2. **Network Issues**: Mock external services or use test containers\n";
    $content .= "3. **Concurrency Issues**: Use locks or sequential execution\n";
    $content .= "4. **WebSocket Issues**: Mock WebSocket server or skip if not available\n";
    $content .= "5. **Random Data**: Use fixed seeds for reproducible tests\n\n";
    $content .= "## Tagging Flaky Tests\n\n";
    $content .= "Tag flaky tests with `@group flaky` to exclude from CI:\n\n";
    $content .= "```php\n/**\n * @group flaky\n */\npublic function test_something()\n{\n    // Test code\n}\n```\n";
    
    file_put_contents('docs/FLAKY_TESTS.md', $content);
}

