<?php declare(strict_types=1);

$root = dirname(__DIR__, 2);

$options = [
    'routes' => $root . '/storage/app/ssot/routes.json',
    'tests' => $root . '/tests',
    'allow' => $root . '/scripts/ssot/allow_orphan_routes.txt',
    'baseline' => $root . '/scripts/ssot/baselines/orphan_routes.txt',
    'allow_baseline' => $root . '/scripts/ssot/allow_orphan_baseline.txt',
    'report_file' => null,
    'report_only' => false,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--routes=')) {
        $options['routes'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--tests=')) {
        $options['tests'] = substr($arg, 8);
    } elseif (str_starts_with($arg, '--allow=')) {
        $options['allow'] = substr($arg, 8);
    } elseif (str_starts_with($arg, '--baseline=')) {
        $options['baseline'] = substr($arg, 11);
    } elseif (str_starts_with($arg, '--allow-baseline=')) {
        $options['allow_baseline'] = substr($arg, 17);
    } elseif (str_starts_with($arg, '--report-file=')) {
        $options['report_file'] = substr($arg, 14);
    } elseif ($arg === '--report-only') {
        $options['report_only'] = true;
    }
}

$routeJson = @file_get_contents($options['routes']);
if ($routeJson === false) {
    fwrite(STDERR, "Missing route map: {$options['routes']}\n");
    exit(1);
}

$routeRows = json_decode($routeJson, true);
if (!is_array($routeRows)) {
    fwrite(STDERR, "Invalid route map JSON: {$options['routes']}\n");
    exit(1);
}

$routePatterns = [];
foreach ($routeRows as $route) {
    $uri = trim((string)($route['uri'] ?? ''));
    if ($uri === '' || !str_starts_with($uri, 'api/')) {
        continue;
    }
    $routePatterns[] = [
        'uri' => normalizePath($uri),
        'regex' => routeUriToRegex(normalizePath($uri)),
    ];
}

$allowedReasonTokens = [
    'NEGATIVE_PROBE_NONEXISTENT_ENDPOINT',
    'NEGATIVE_PROBE_UNSUPPORTED_ENDPOINT',
    'NEGATIVE_PROBE_LEGACY_SURFACE',
    'NEGATIVE_PROBE_SECURITY_TRAVERSAL',
];

$allowConfig = loadAllowRules($options['allow'], $allowedReasonTokens);
if ($allowConfig['errors'] !== []) {
    fwrite(STDERR, "Invalid allow-orphan route declarations:\n");
    foreach ($allowConfig['errors'] as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

$allowRules = $allowConfig['rules'];
$baseline = loadSet($options['baseline']);
$allowBaselinePaths = loadPathSet($options['allow_baseline']);
$updateBaseline = getenv('SSOT_UPDATE_BASELINES') === '1';
$strictMode = getenv('SSOT_STRICT') === '1';

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($options['tests'], FilesystemIterator::SKIP_DOTS)
);

$allLines = [];
$allowLines = [];
$orphanKeys = [];
$newOrphans = [];
$newAllowNotAllowlisted = [];
$allowDeclarationIssues = [];
$invalidInlineReasons = [];
$counts = ['ok' => 0, 'orphan' => 0, 'legacy' => 0, 'allow' => 0, 'suppressed' => 0];

/** @var SplFileInfo $file */
foreach ($iter as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $absPath = $file->getPathname();
    $relative = ltrim(str_replace($root, '', $absPath), '/');
    $isLegacy = str_contains($relative, '/Legacy/') || str_starts_with($relative, 'tests/Legacy/');

    $contents = file_get_contents($absPath);
    if ($contents === false) {
        continue;
    }

    $lines = preg_split('/\R/', $contents) ?: [];
    foreach ($lines as $lineNumber => $line) {
        if (!preg_match_all('/(["\'])(\/??api\/[^"\']+?)\1/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $m) {
            $raw = trim($m[2]);
            $normalized = normalizePath($raw);
            if (!str_starts_with($normalized, 'api/')) {
                continue;
            }

            $fileLine = $relative . ':' . ($lineNumber + 1);
            $key = $fileLine . '|' . $normalized;

            if (matchesAnyRoute($normalized, $routePatterns)) {
                $counts['ok']++;
                $allLines[] = "{$fileLine} -> /{$normalized} => OK";
                continue;
            }

            $inlineAllowReason = extractInlineAllowReason($line);
            if ($inlineAllowReason !== null && !in_array($inlineAllowReason, $allowedReasonTokens, true)) {
                $invalidInlineReasons[] = "INLINE_REASON_INVALID: {$fileLine} /{$normalized} reason={$inlineAllowReason}";
            }
            $allowReason = getAllowReason($normalized, $allowRules);

            if ($allowReason !== null) {
                if ($inlineAllowReason === null) {
                    $allowDeclarationIssues[] = "ALLOW_MISSING_TOKEN: {$fileLine} /{$normalized} expected SSOT_ALLOW_ORPHAN(reason={$allowReason})";
                } elseif ($inlineAllowReason !== $allowReason) {
                    $allowDeclarationIssues[] = "ALLOW_REASON_MISMATCH: {$fileLine} /{$normalized} expected={$allowReason} got={$inlineAllowReason}";
                }

                $counts['allow']++;
                $allowLines[] = "{$fileLine} -> /{$normalized} -> reason={$allowReason}";
                $allLines[] = "{$fileLine} -> /{$normalized} => ALLOW reason={$allowReason}";
                continue;
            }

            if ($inlineAllowReason !== null) {
                $newAllowNotAllowlisted[] = "NEW_ALLOW_NOT_ALLOWLISTED: {$fileLine} /{$normalized} reason={$inlineAllowReason}";
            }

            if ($isLegacy) {
                $counts['legacy']++;
                $allLines[] = "{$fileLine} -> /{$normalized} => LEGACY";
                continue;
            }

            $counts['orphan']++;
            $allLines[] = "{$fileLine} -> /{$normalized} => ORPHAN";
            $orphanKeys[] = $key;

            if (isset($baseline[$key])) {
                $counts['suppressed']++;
                continue;
            }

            $newOrphans[] = [$fileLine, $normalized];
        }
    }
}

sort($allLines);
$report = [];
$report[] = 'SSOT Orphan Route Report';
$report[] = '========================';
$report[] = 'Scanned routes: ' . count($routePatterns);
$report[] = 'OK: ' . $counts['ok'];
$report[] = 'ALLOW: ' . $counts['allow'];
$report[] = 'LEGACY: ' . $counts['legacy'];
$report[] = 'ORPHAN: ' . $counts['orphan'];
$report[] = 'BASELINE_SUPPRESSED: ' . $counts['suppressed'];
$report[] = 'STRICT_MODE: ' . ($strictMode ? 'ON' : 'OFF');
$report[] = '';
$report[] = 'ALLOW LIST';
$report[] = '----------';
if ($allowLines === []) {
    $report[] = '(none)';
} else {
    sort($allowLines);
    $report = array_merge($report, $allowLines);
}
$report[] = '';
$report = array_merge($report, $allLines);

if ($updateBaseline) {
    $dir = dirname($options['baseline']);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $unique = array_values(array_unique($orphanKeys));
    sort($unique);
    file_put_contents($options['baseline'], implode(PHP_EOL, $unique) . ($unique ? PHP_EOL : ''));
    $report[] = '';
    $report[] = 'Baseline updated at: ' . $options['baseline'];
}

$out = implode(PHP_EOL, $report) . PHP_EOL;
echo $out;

if (is_string($options['report_file']) && $options['report_file'] !== '') {
    $dir = dirname($options['report_file']);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($options['report_file'], $out);
}

if ($options['report_only'] || $updateBaseline) {
    exit(0);
}

if ($invalidInlineReasons !== []) {
    fwrite(STDERR, "\nInvalid inline ALLOW reason tokens:\n");
    foreach ($invalidInlineReasons as $msg) {
        fwrite(STDERR, "- {$msg}\n");
    }
    exit(1);
}

if ($newAllowNotAllowlisted !== []) {
    fwrite(STDERR, "\nUnallowlisted ALLOW probes detected:\n");
    foreach ($newAllowNotAllowlisted as $msg) {
        fwrite(STDERR, "- {$msg}\n");
    }
    exit(1);
}

if ($allowDeclarationIssues !== []) {
    fwrite(STDERR, "\nInvalid ALLOW declarations:\n");
    foreach ($allowDeclarationIssues as $msg) {
        fwrite(STDERR, "- {$msg}\n");
    }
    exit(1);
}

if ($strictMode) {
    $strictViolations = [];
    $allowPaths = array_keys($allowRules);
    sort($allowPaths);
    $baselinePaths = array_keys($allowBaselinePaths);
    sort($baselinePaths);

    $newAllowPaths = array_values(array_diff($allowPaths, $baselinePaths));
    if ($newAllowPaths !== []) {
        $strictViolations[] = 'new allow paths: ' . implode(', ', array_map(fn(string $p): string => "/{$p}", $newAllowPaths));
    }

    if (count($allowPaths) > count($baselinePaths)) {
        $strictViolations[] = 'allowlist path count increased: current=' . count($allowPaths) . ' baseline=' . count($baselinePaths);
    }

    if ($strictViolations !== []) {
        fwrite(STDERR, "\nSSOT_STRICT=1 violations:\n");
        foreach ($strictViolations as $violation) {
            fwrite(STDERR, "- {$violation}\n");
        }
        exit(1);
    }
}

if ($newOrphans !== []) {
    fwrite(STDERR, "\nNew orphan routes detected (not in baseline):\n");
    foreach ($newOrphans as [$fileLine, $path]) {
        fwrite(STDERR, "- {$fileLine} -> /{$path}\n");
    }
    exit(1);
}

exit(0);

function normalizePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://[^/]+(/.*)$#', $path, $m)) {
        $path = $m[1];
    }

    $path = preg_replace('/\?.*$/', '', $path) ?? $path;
    $path = trim($path, '/');

    return $path;
}

function routeUriToRegex(string $uri): string
{
    $segments = explode('/', $uri);
    $regex = '#^';

    foreach ($segments as $segment) {
        if (preg_match('/^\{[^}]+\?\}$/', $segment)) {
            $regex .= '(?:/[^/]+)?';
            continue;
        }

        if (preg_match('/^\{[^}]+\}$/', $segment)) {
            $regex .= '/[^/]+';
            continue;
        }

        $regex .= '/' . preg_quote($segment, '#');
    }

    return $regex . '$#';
}

/**
 * @param array<int, array{uri:string,regex:string}> $routePatterns
 */
function matchesAnyRoute(string $path, array $routePatterns): bool
{
    $candidate = '/' . trim($path, '/');

    foreach ($routePatterns as $route) {
        if ($path === $route['uri']) {
            return true;
        }

        if (preg_match($route['regex'], $candidate) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int, string> $allowedReasonTokens
 * @return array{rules: array<string, string>, errors: array<int, string>}
 */
function loadAllowRules(string $file, array $allowedReasonTokens): array
{
    if (!is_file($file)) {
        return ['rules' => [], 'errors' => []];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $rules = [];
    $errors = [];

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('|', $line);
        if (count($parts) !== 2) {
            $errors[] = 'line ' . ($index + 1) . ": expected '<path>|reason=<TOKEN>'";
            continue;
        }

        $path = normalizePath(trim($parts[0]));
        $reasonPart = trim($parts[1]);

        if (!str_starts_with($path, 'api/')) {
            $errors[] = 'line ' . ($index + 1) . ": path must begin with /api (got '{$parts[0]}')";
            continue;
        }

        if (preg_match('/^reason=([A-Za-z0-9_]+)$/', $reasonPart, $m) !== 1) {
            $errors[] = 'line ' . ($index + 1) . ": invalid reason token '{$reasonPart}'";
            continue;
        }

        if (!in_array($m[1], $allowedReasonTokens, true)) {
            $errors[] = 'line ' . ($index + 1) . ": reason token '{$m[1]}' is not in enum";
            continue;
        }

        if (isset($rules[$path])) {
            $errors[] = 'line ' . ($index + 1) . ": duplicate path '/{$path}'";
            continue;
        }

        $rules[$path] = $m[1];
    }

    return ['rules' => $rules, 'errors' => $errors];
}

function getAllowReason(string $path, array $allowRules): ?string
{
    return $allowRules[$path] ?? null;
}

/**
 * @return array<string, bool>
 */
function loadSet(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $set = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $set[$line] = true;
    }

    return $set;
}

/**
 * @return array<string, bool>
 */
function loadPathSet(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $set = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $path = normalizePath($line);
        if ($path === '') {
            continue;
        }

        $set[$path] = true;
    }

    return $set;
}

function extractInlineAllowReason(string $line): ?string
{
    if (preg_match('/SSOT_ALLOW_ORPHAN\s*\(\s*reason=([A-Za-z0-9_]+)\s*\)/', $line, $m) === 1) {
        return $m[1];
    }

    return null;
}
