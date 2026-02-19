#!/usr/bin/env php
<?php declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

$options = [
    '--tests-dir' => 'tests',
    '--inventory-out' => null,
    '--violations-out' => null,
    '--sources-out' => null,
];

for ($i = 0; $i < count($args); $i++) {
    $arg = $args[$i];
    if (!array_key_exists($arg, $options)) {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(1);
    }
    $value = $args[$i + 1] ?? null;
    if ($value === null || str_starts_with($value, '--')) {
        fwrite(STDERR, "Missing value for argument: {$arg}\n");
        exit(1);
    }
    $options[$arg] = $value;
    $i++;
}

if ($options['--inventory-out'] === null || $options['--violations-out'] === null) {
    fwrite(STDERR, "Required: --inventory-out <path> --violations-out <path>\n");
    exit(1);
}

$testsDir = (string) $options['--tests-dir'];
$inventoryOut = (string) $options['--inventory-out'];
$violationsOut = (string) $options['--violations-out'];
$sourcesOut = $options['--sources-out'];

const ALLOWED_GROUPS = ['slow', 'load', 'stress', 'redis'];
const REASON_TOKENS = ['RUN_SLOW_TESTS', 'RUN_LOAD_TESTS', 'RUN_STRESS_TESTS', 'REDIS_', 'dependency:'];

$sourceRows = collectSkipSources($testsDir);
$sourceLines = array_map(
    static fn (array $row): string => sprintf('%s:%d:%s', $row['file'], $row['line'], trim($row['text'])),
    $sourceRows
);
sort($sourceLines);

if ($sourcesOut !== null) {
    file_put_contents($sourcesOut, implode(PHP_EOL, $sourceLines) . (count($sourceLines) ? PHP_EOL : ''));
}

$files = [];
foreach ($sourceRows as $row) {
    $files[$row['file']] = true;
}
$files = array_keys($files);
sort($files);

$fileMeta = [];
foreach ($files as $file) {
    $fileMeta[$file] = parsePhpStructure($file);
}

$inventory = [];
$violations = [];

foreach ($sourceRows as $row) {
    $file = $row['file'];
    $line = $row['line'];
    $meta = $fileMeta[$file] ?? null;
    if ($meta === null) {
        continue;
    }

    $method = resolveMethodAtLine($meta['methods'], $line);
    if ($method === null) {
        $fallback = resolveMethodByLineScan($file, $line);
        if ($fallback !== null) {
            $method = $fallback;
        }
    }
    $className = $meta['className'] ?? basename($file, '.php');
    $methodName = $method['name'] ?? 'unknown';
    $qualified = $className . '::' . $methodName;

    $groups = [];
    if ($method !== null && !empty($method['groups'])) {
        $groups = $method['groups'];
    } elseif (!empty($meta['classGroups'])) {
        $groups = $meta['classGroups'];
    }
    $groups = array_values(array_intersect($groups, ALLOWED_GROUPS));

    $statement = extractSkipStatement($file, $line);
    $reasonToken = detectReasonToken($statement);

    if (empty($groups)) {
        $violations[] = sprintf(
            '%s:%d | %s | missing @group (allowed: %s)',
            $file,
            $line,
            $qualified,
            implode(',', ALLOWED_GROUPS)
        );
    }

    if ($reasonToken === null) {
        $violations[] = sprintf(
            '%s:%d | %s | missing reason token (need one of: %s)',
            $file,
            $line,
            $qualified,
            implode(', ', REASON_TOKENS)
        );
    }

    if (!empty($groups) && $reasonToken !== null) {
        $group = chooseGroupForReason($groups, $reasonToken);
        $entry = sprintf('%s|group=%s|reason=%s', $qualified, $group, $reasonToken);
        $inventory[$entry] = true;
    }
}

$inventoryLines = array_keys($inventory);
sort($inventoryLines);
sort($violations);

file_put_contents($inventoryOut, implode(PHP_EOL, $inventoryLines) . (count($inventoryLines) ? PHP_EOL : ''));
file_put_contents($violationsOut, implode(PHP_EOL, $violations) . (count($violations) ? PHP_EOL : ''));

function collectSkipSources(string $testsDir): array
{
    $rows = [];
    if (!is_dir($testsDir)) {
        return $rows;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $item->getPathname());
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $index => $lineText) {
            $lineNo = $index + 1;
            if (
                str_contains($lineText, 'markTestSkipped(')
                || str_contains($lineText, '$this->markTestSkipped(')
                || str_contains($lineText, '->markTestSkipped(')
            ) {
                $rows[] = ['file' => $path, 'line' => $lineNo, 'text' => $lineText];
                continue;
            }

            if (
                str_contains($lineText, '->skip(')
                && preg_match('/\b(test|it)\s*\(|->group\(/i', $lineText) === 1
            ) {
                $rows[] = ['file' => $path, 'line' => $lineNo, 'text' => $lineText];
            }
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return [$a['file'], $a['line']] <=> [$b['file'], $b['line']];
    });

    $dedup = [];
    $seen = [];
    foreach ($rows as $row) {
        $key = $row['file'] . ':' . $row['line'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $dedup[] = $row;
    }

    return $dedup;
}

function parsePhpStructure(string $file): array
{
    $code = (string) @file_get_contents($file);
    $tokens = token_get_all($code);

    $tokenLines = [];
    $line = 1;
    foreach ($tokens as $index => $token) {
        $tokenLines[$index] = $line;
        $text = is_array($token) ? $token[1] : (string) $token;
        $line += substr_count($text, "\n");
    }

    $className = basename($file, '.php');
    $classGroups = [];
    $methods = [];
    $pendingDoc = null;

    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $pendingDoc = $token[1];
            continue;
        }

        if (is_array($token) && $token[0] === T_CLASS) {
            $prev = previousSignificantTokenId($tokens, $i - 1);
            if ($prev === T_DOUBLE_COLON || $prev === T_NEW) {
                continue;
            }

            $name = nextTokenString($tokens, $i + 1);
            if ($name !== null) {
                $className = $name;
            }
            $classGroups = extractGroups($pendingDoc);
            $pendingDoc = null;
            continue;
        }

        if (is_array($token) && $token[0] === T_FUNCTION) {
            $methodName = nextFunctionName($tokens, $i + 1);
            if ($methodName === null) {
                $pendingDoc = null;
                continue;
            }

            $startLine = $tokenLines[$i] ?? 1;
            $methodGroups = extractGroups($pendingDoc);
            $pendingDoc = null;

            $openBraceIndex = null;
            for ($j = $i; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === '{') {
                    $openBraceIndex = $j;
                    break;
                }
                if ($t === ';') {
                    break;
                }
            }

            if ($openBraceIndex === null) {
                continue;
            }

            $depth = 1;
            $endLine = $tokenLines[$openBraceIndex] ?? $startLine;
            for ($j = $openBraceIndex + 1; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === '{') {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $endLine = $tokenLines[$j] ?? $endLine;
                        break;
                    }
                }
            }

            $methods[] = [
                'name' => $methodName,
                'start' => $startLine,
                'end' => $endLine,
                'groups' => $methodGroups,
            ];
        }
    }

    return [
        'className' => $className,
        'classGroups' => array_values(array_intersect($classGroups, ALLOWED_GROUPS)),
        'methods' => $methods,
    ];
}

function nextTokenString(array $tokens, int $start): ?string
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            if (in_array($token, ['{', ';', '('], true)) {
                return null;
            }
            continue;
        }
        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_ABSTRACT, T_FINAL], true)) {
            continue;
        }
        if ($token[0] === T_STRING) {
            return $token[1];
        }
        return null;
    }

    return null;
}

function previousSignificantTokenId(array $tokens, int $start): ?int
{
    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            if (trim((string) $token) === '') {
                continue;
            }

            if ($token === '::') {
                return T_DOUBLE_COLON;
            }

            return null;
        }

        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $token[0];
    }

    return null;
}

function nextFunctionName(array $tokens, int $start): ?string
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];
        if ($token === '(') {
            return null;
        }
        if (is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }
    }

    return null;
}

function extractGroups(?string $doc): array
{
    if ($doc === null || $doc === '') {
        return [];
    }

    preg_match_all('/@group\s+([A-Za-z0-9_-]+)/', $doc, $matches);
    $groups = array_map('strtolower', $matches[1] ?? []);
    $groups = array_values(array_unique($groups));

    return $groups;
}

function resolveMethodAtLine(array $methods, int $line): ?array
{
    foreach ($methods as $method) {
        if ($line >= $method['start'] && $line <= $method['end']) {
            return $method;
        }
    }

    return null;
}

function resolveMethodByLineScan(string $file, int $line): ?array
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return null;
    }

    $index = min(max(1, $line), count($lines)) - 1;
    for ($i = $index; $i >= 0; $i--) {
        if (preg_match('/\bfunction\s+([A-Za-z0-9_]+)\s*\(/', $lines[$i], $match) !== 1) {
            continue;
        }

        $methodGroups = [];
        for ($j = $i - 1; $j >= max(0, $i - 25); $j--) {
            if (preg_match('/^\s*(public|protected|private)\s+function\b/', $lines[$j]) === 1) {
                break;
            }
            if (str_contains($lines[$j], '@group')) {
                preg_match_all('/@group\s+([A-Za-z0-9_-]+)/', $lines[$j], $groups);
                foreach ($groups[1] ?? [] as $group) {
                    $group = strtolower($group);
                    if (in_array($group, ALLOWED_GROUPS, true)) {
                        $methodGroups[] = $group;
                    }
                }
            }
        }

        return [
            'name' => $match[1],
            'groups' => array_values(array_unique($methodGroups)),
            'start' => $i + 1,
            'end' => $line,
        ];
    }

    return null;
}

function extractSkipStatement(string $file, int $line): string
{
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || !isset($lines[$line - 1])) {
        return '';
    }

    $maxLine = count($lines);
    $buffer = [];
    $end = min($maxLine, $line + 8);
    for ($i = $line; $i <= $end; $i++) {
        $buffer[] = $lines[$i - 1];
        if (str_contains($lines[$i - 1], ');')) {
            break;
        }
    }

    return implode("\n", $buffer);
}

function detectReasonToken(string $statement): ?string
{
    if ($statement === '') {
        return null;
    }

    if (str_contains($statement, 'RUN_SLOW_TESTS')) {
        return 'RUN_SLOW_TESTS';
    }
    if (str_contains($statement, 'SLOW_TESTS_ENV')) {
        return 'RUN_SLOW_TESTS';
    }
    if (str_contains($statement, 'RUN_LOAD_TESTS')) {
        return 'RUN_LOAD_TESTS';
    }
    if (str_contains($statement, 'LOAD_TESTS_ENV')) {
        return 'RUN_LOAD_TESTS';
    }
    if (str_contains($statement, 'RUN_STRESS_TESTS')) {
        return 'RUN_STRESS_TESTS';
    }
    if (str_contains($statement, 'STRESS_TESTS_ENV')) {
        return 'RUN_STRESS_TESTS';
    }
    if (preg_match('/REDIS_[A-Z_]+/', $statement) === 1) {
        return 'REDIS_';
    }
    if (stripos($statement, 'dependency:') !== false) {
        return 'dependency:';
    }

    return null;
}

function chooseGroupForReason(array $groups, string $reason): string
{
    $reasonToGroup = [
        'RUN_SLOW_TESTS' => 'slow',
        'RUN_LOAD_TESTS' => 'load',
        'RUN_STRESS_TESTS' => 'stress',
        'REDIS_' => 'redis',
    ];

    if (isset($reasonToGroup[$reason]) && in_array($reasonToGroup[$reason], $groups, true)) {
        return $reasonToGroup[$reason];
    }

    $priority = ['slow', 'load', 'stress', 'redis'];
    foreach ($priority as $candidate) {
        if (in_array($candidate, $groups, true)) {
            return $candidate;
        }
    }

    return $groups[0];
}
