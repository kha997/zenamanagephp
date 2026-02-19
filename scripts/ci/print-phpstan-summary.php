#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/ci/print-phpstan-summary.php <report.json> [limit]\n");
    exit(2);
}

$reportPath = $argv[1];
$limit = isset($argv[2]) ? max(1, (int) $argv[2]) : 50;

if (!is_file($reportPath)) {
    fwrite(STDERR, "PHPStan report not found: {$reportPath}\n");
    exit(2);
}

$raw = file_get_contents($reportPath);
if ($raw === false) {
    fwrite(STDERR, "Unable to read PHPStan report: {$reportPath}\n");
    exit(2);
}

$report = json_decode($raw, true);
if (!is_array($report)) {
    fwrite(STDERR, "Invalid JSON in PHPStan report: {$reportPath}\n");
    exit(2);
}

$totals = $report['totals'] ?? [];
$totalErrors = (int) ($totals['file_errors'] ?? 0);
echo "PHPStan file errors: {$totalErrors}\n";

$files = $report['files'] ?? [];
if (!is_array($files) || $files === []) {
    echo "No file-level errors found in report.\n";
    exit(0);
}

$rows = [];
foreach ($files as $file => $fileData) {
    if (!is_array($fileData)) {
        continue;
    }

    $messages = $fileData['messages'] ?? [];
    if (!is_array($messages)) {
        continue;
    }

    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }

        $rows[] = [
            'file' => (string) $file,
            'line' => (int) ($msg['line'] ?? 0),
            'identifier' => (string) ($msg['identifier'] ?? 'n/a'),
            'message' => (string) ($msg['message'] ?? ''),
        ];
    }
}

if ($rows === []) {
    echo "No parseable PHPStan messages found.\n";
    exit(0);
}

echo "Top {$limit} PHPStan errors:\n";
$shown = 0;
foreach ($rows as $row) {
    ++$shown;
    if ($shown > $limit) {
        break;
    }

    echo sprintf(
        "[%d] %s:%d [%s] %s\n",
        $shown,
        $row['file'],
        $row['line'],
        $row['identifier'],
        $row['message']
    );
}

if (count($rows) > $limit) {
    $remaining = count($rows) - $limit;
    echo "... and {$remaining} more.\n";
}
