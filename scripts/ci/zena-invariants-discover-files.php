<?php declare(strict_types=1);

/**
 * GAP-050 Gate 3 correction (§1): deterministic test-file discovery
 * grounded in PHPUnit's own group-metadata resolution, not a source-text
 * `grep`.
 *
 * Why this replaced `grep -rl '@group zena-invariants' tests/`: PHPUnit's
 * doc-comment metadata (`@group`, `@depends`, etc.) is deprecated and will
 * be removed in PHPUnit 12 (already warned about on this repo's own live
 * CI). A file migrated from the doc-comment `@group zena-invariants`
 * annotation to the `#[Group('zena-invariants')]` attribute would silently
 * stop matching a `grep` for the literal string `@group zena-invariants`,
 * removing it from this job's coverage while the job continued reporting
 * green — a false-green regression in test-selection itself, not in the
 * tests. PHPUnit's own `--list-tests-xml` resolves group membership via its
 * internal metadata API, which reads BOTH the doc-comment and attribute
 * forms into the same unified concept — so this script's discovery can
 * never be fooled by which form a given file happens to use.
 *
 * Usage:
 *   php scripts/ci/zena-invariants-discover-files.php --group=<name> [--config=<phpunit.xml path>] [--phpunit-bin=<path>]
 *
 * Prints one absolute file path per line, sorted deterministically
 * (LC_ALL=C-equivalent byte-order sort), for every distinct file PHPUnit
 * itself says contains at least one test in the given group. Prints
 * nothing (exit 0) if zero tests match — deciding whether an empty result
 * is acceptable is the caller's responsibility (see
 * scripts/ci/zena-invariants-mysql's explicit zero-selection fail-closed
 * check), not this script's, so this stays a single-purpose primitive.
 *
 * Fails closed (non-zero exit, diagnostic on STDERR, nothing on STDOUT) if:
 *   - required arguments are missing/malformed;
 *   - the PHPUnit binary cannot be found or exits non-zero;
 *   - the requested `--list-tests-xml` output file is missing after the
 *     command claims success (this repo's own commit history documents
 *     evidence-computation code silently trusting an empty/missing
 *     artifact as if it were valid — never repeat that here);
 *   - the XML cannot be parsed, or contains a `<testClass>` element with no
 *     `file` attribute (would silently drop that class's tests from the
 *     returned inventory).
 */

function gap050_discover_fail(string $message): never
{
    fwrite(STDERR, 'zena-invariants-discover-files: ' . $message . "\n");
    exit(1);
}

$options = getopt('', ['group:', 'config::', 'phpunit-bin::']);

$group = $options['group'] ?? null;
if (!is_string($group) || $group === '') {
    gap050_discover_fail('--group=<name> is required and must be non-empty.');
}

$repoRoot = dirname(__DIR__, 2);

$configPath = $options['config'] ?? ($repoRoot . '/phpunit.xml');
if (!is_string($configPath) || $configPath === '' || !is_file($configPath)) {
    gap050_discover_fail("--config path does not exist: " . var_export($configPath, true));
}

$phpunitBin = $options['phpunit-bin'] ?? ($repoRoot . '/vendor/bin/phpunit');
if (!is_string($phpunitBin) || $phpunitBin === '' || !is_file($phpunitBin)) {
    gap050_discover_fail("phpunit binary not found: " . var_export($phpunitBin, true));
}

$listTestsXmlPath = tempnam(sys_get_temp_dir(), 'gap050-zena-invariants-list-');
if ($listTestsXmlPath === false) {
    gap050_discover_fail('could not create a temporary file for --list-tests-xml output.');
}

register_shutdown_function(static function () use ($listTestsXmlPath): void {
    if (is_file($listTestsXmlPath)) {
        @unlink($listTestsXmlPath);
    }
});

$command = [
    PHP_BINARY,
    $phpunitBin,
    '--configuration=' . $configPath,
    '--group=' . $group,
    '--list-tests-xml=' . $listTestsXmlPath,
];

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, dirname($configPath));
if (!is_resource($process)) {
    gap050_discover_fail('failed to start the PHPUnit subprocess (proc_open returned false).');
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    gap050_discover_fail(sprintf(
        "PHPUnit --list-tests-xml exited %d (command: %s).\n--- stdout ---\n%s\n--- stderr ---\n%s",
        $exitCode,
        implode(' ', array_map('escapeshellarg', $command)),
        $stdout,
        $stderr
    ));
}

if (!is_file($listTestsXmlPath) || filesize($listTestsXmlPath) === 0) {
    gap050_discover_fail(sprintf(
        "PHPUnit exited 0 but produced no --list-tests-xml output at %s. Refusing to treat a missing artifact as an empty-but-valid result.\n--- stdout ---\n%s\n--- stderr ---\n%s",
        $listTestsXmlPath,
        $stdout,
        $stderr
    ));
}

$xmlContents = file_get_contents($listTestsXmlPath);
if ($xmlContents === false) {
    gap050_discover_fail("could not read the --list-tests-xml output file at {$listTestsXmlPath}.");
}

$previousUseErrors = libxml_use_internal_errors(true);
$document = simplexml_load_string($xmlContents);
$xmlErrors = libxml_get_errors();
libxml_clear_errors();
libxml_use_internal_errors($previousUseErrors);

if ($document === false) {
    $errorSummary = implode('; ', array_map(static fn ($error) => trim($error->message), $xmlErrors));
    gap050_discover_fail("could not parse --list-tests-xml output as XML: {$errorSummary}");
}

$files = [];
foreach ($document->tests->testClass ?? [] as $testClass) {
    $attributes = $testClass->attributes();
    $file = isset($attributes['file']) ? (string) $attributes['file'] : '';
    $className = isset($attributes['name']) ? (string) $attributes['name'] : '(unknown class)';

    if ($file === '') {
        gap050_discover_fail("PHPUnit reported testClass '{$className}' with no file attribute — refusing to silently drop it from the discovered inventory.");
    }

    if (!is_file($file)) {
        gap050_discover_fail("PHPUnit reported testClass '{$className}' at file '{$file}', but that file does not exist on disk.");
    }

    $files[$file] = true;
}

$sortedFiles = array_keys($files);
sort($sortedFiles, SORT_STRING);

foreach ($sortedFiles as $file) {
    echo $file . "\n";
}
