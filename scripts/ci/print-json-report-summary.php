#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/ci/print-json-report-summary.php <report.json> <label> [limit]\n");
    exit(2);
}

$reportPath = $argv[1];
$label = $argv[2];
$limit = isset($argv[3]) ? max(1, (int) $argv[3]) : 10;

echo "{$label}\n";

if (!is_file($reportPath)) {
    echo "Report not found: {$reportPath}\n";
    exit(0);
}

$raw = file_get_contents($reportPath);
if ($raw === false || trim($raw) === '') {
    echo "Report is empty: {$reportPath}\n";
    exit(0);
}

$report = json_decode($raw, true);
if (!is_array($report)) {
    echo "Report is not valid JSON: {$reportPath}\n";
    $lines = preg_split('/\R/', trim($raw)) ?: [];
    foreach (array_slice($lines, 0, $limit) as $line) {
        echo $line . "\n";
    }
    exit(0);
}

if (isset($report['totals'], $report['files']) && is_array($report['files'])) {
    $totalErrors = (int) ($report['totals']['file_errors'] ?? 0);
    echo "PHPStan file errors: {$totalErrors}\n";

    $shown = 0;
    foreach ($report['files'] as $file => $fileData) {
        if (!is_array($fileData) || !isset($fileData['messages']) || !is_array($fileData['messages'])) {
            continue;
        }

        foreach ($fileData['messages'] as $message) {
            if (!is_array($message)) {
                continue;
            }

            ++$shown;
            echo sprintf(
                "[%d] %s:%d %s\n",
                $shown,
                (string) $file,
                (int) ($message['line'] ?? 0),
                (string) ($message['message'] ?? 'Unknown PHPStan error')
            );

            if ($shown >= $limit) {
                exit(0);
            }
        }
    }

    if ($shown === 0) {
        echo "No parseable PHPStan messages found.\n";
    }

    exit(0);
}

if (isset($report['vulnerabilities']) && is_array($report['vulnerabilities'])) {
    $vulnerabilities = array_values($report['vulnerabilities']);
    echo "Vulnerabilities: " . count($vulnerabilities) . "\n";

    foreach (array_slice($vulnerabilities, 0, $limit) as $index => $vulnerability) {
        if (!is_array($vulnerability)) {
            echo sprintf("[%d] %s\n", $index + 1, (string) $vulnerability);
            continue;
        }

        $package = (string) ($vulnerability['package'] ?? $vulnerability['packageName'] ?? 'unknown-package');
        $title = (string) ($vulnerability['title'] ?? $vulnerability['cve'] ?? 'Untitled vulnerability');
        echo sprintf("[%d] %s - %s\n", $index + 1, $package, $title);
    }

    exit(0);
}

if (isset($report['advisories']) && is_array($report['advisories'])) {
    $rows = [];
    foreach ($report['advisories'] as $package => $advisories) {
        if (!is_array($advisories)) {
            $rows[] = [$package, (string) $advisories];
            continue;
        }

        foreach ($advisories as $advisory) {
            if (!is_array($advisory)) {
                $rows[] = [$package, (string) $advisory];
                continue;
            }

            $rows[] = [
                (string) ($advisory['packageName'] ?? $package),
                (string) ($advisory['title'] ?? $advisory['cve'] ?? 'Untitled advisory'),
            ];
        }
    }

    echo "Advisories: " . count($rows) . "\n";
    foreach (array_slice($rows, 0, $limit) as $index => [$package, $title]) {
        echo sprintf("[%d] %s - %s\n", $index + 1, $package, $title);
    }
    exit(0);
}

if (isset($report['installed']) && is_array($report['installed'])) {
    echo "Installed entries: " . count($report['installed']) . "\n";
    foreach (array_slice($report['installed'], 0, $limit) as $index => $package) {
        if (!is_array($package)) {
            echo sprintf("[%d] %s\n", $index + 1, (string) $package);
            continue;
        }

        echo sprintf(
            "[%d] %s %s -> %s\n",
            $index + 1,
            (string) ($package['name'] ?? 'unknown-package'),
            (string) ($package['version'] ?? 'unknown'),
            (string) ($package['latest'] ?? 'unknown')
        );
    }
    exit(0);
}

if (isset($report['violations']) && is_array($report['violations'])) {
    echo "Violations: " . count($report['violations']) . "\n";
    foreach (array_slice($report['violations'], 0, $limit) as $index => $violation) {
        if (!is_array($violation)) {
            echo sprintf("[%d] %s\n", $index + 1, (string) $violation);
            continue;
        }

        echo sprintf(
            "[%d] %s - %s\n",
            $index + 1,
            (string) ($violation['package'] ?? 'unknown-package'),
            (string) ($violation['license'] ?? 'unknown-license')
        );
    }
    exit(0);
}

echo "Top-level keys: " . implode(', ', array_keys($report)) . "\n";
foreach (array_slice($report, 0, $limit, true) as $key => $value) {
    if (is_scalar($value) || $value === null) {
        echo "{$key}: " . var_export($value, true) . "\n";
        continue;
    }

    echo "{$key}: " . substr(json_encode($value, JSON_UNESCAPED_SLASHES) ?: '', 0, 200) . "\n";
}
