<?php declare(strict_types=1);

/**
 * Lightweight guardrail for Phase 2 ownership rollout.
 *
 * Scope (initial):
 * - Project controllers
 * - Document controllers
 *
 * Rule:
 * - When canonical src service exists, app controllers must not import
 *   non-canonical App models/services for that module.
 * - Existing debt is tracked in a temporary allowlist so CI only blocks new drift.
 */

$root = dirname(__DIR__, 2);
$controllersRoot = $root . '/app/Http/Controllers';

if (!is_dir($controllersRoot)) {
    fwrite(STDERR, "Domain ownership lint failed: missing {$controllersRoot}\n");
    exit(1);
}

$moduleRules = [
    'project' => [
        'canonical_service_file' => $root . '/src/CoreProject/Services/ProjectService.php',
        'forbidden_tokens' => [
            'use App\\Models\\Project;',
            'use App\\Services\\ProjectService;',
        ],
    ],
    'document' => [
        'canonical_service_file' => $root . '/src/DocumentManagement/Services/DocumentService.php',
        'forbidden_tokens' => [
            'use App\\Models\\Document;',
            'use App\\Services\\DocumentService;',
        ],
    ],
];

$legacyAllowlist = [];

$canonicalModules = [];
foreach ($moduleRules as $module => $rule) {
    $canonicalModules[$module] = is_file($rule['canonical_service_file']);
}

$targets = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($controllersRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (!preg_match('/(Project|Document).*Controller\.php$/', $path)) {
        continue;
    }

    $targets[] = $path;
}

sort($targets);

$violations = [];
$legacyDebt = [];

foreach ($targets as $absolutePath) {
    $relativePath = ltrim(str_replace($root, '', $absolutePath), '/');
    $contents = file_get_contents($absolutePath);
    if ($contents === false) {
        $violations[] = "{$relativePath}: cannot read file";
        continue;
    }

    foreach ($moduleRules as $module => $rule) {
        if (!$canonicalModules[$module]) {
            continue;
        }

        foreach ($rule['forbidden_tokens'] as $token) {
            if (strpos($contents, $token) === false) {
                continue;
            }

            $entry = "{$relativePath}: {$token}";
            if (in_array($relativePath, $legacyAllowlist, true)) {
                $legacyDebt[] = $entry;
            } else {
                $violations[] = $entry;
            }
        }
    }
}

if (!empty($legacyDebt)) {
    echo "Domain ownership lint: legacy allowlisted debt (informational)\n";
    foreach ($legacyDebt as $debt) {
        echo "  - {$debt}\n";
    }
    echo "\n";
}

if (!empty($violations)) {
    echo "Domain ownership lint: violations (new drift blocked)\n";
    foreach ($violations as $violation) {
        echo "  - {$violation}\n";
    }
    echo "\nSee docs/engineering/domain-ownership.md for canonical ownership.\n";
    exit(1);
}

echo "Domain ownership lint passed.\n";
exit(0);
