<?php

declare(strict_types=1);

/**
 * OWN-2026-024 — evaluates a Recovery-on-MySQL PHPUnit run (see scripts/ci/recovery-mysql). Fail-closed:
 *  - executed test cases must equal the --list-tests-xml inventory (no silent narrowing);
 *  - any skip, failure or error exits 1 (known failures are MAPPED in the summary, never excused);
 *  - a PHPUnit exit code other than 0 with no recorded failure also exits 1.
 *
 * Usage: php recovery-mysql-report.php <list.xml> <junit.xml> <known-failures.txt> <mysql-version> <phpunit-exit>
 */
[$self, $listPath, $junitPath, $knownPath, $mysqlVersion, $phpunitExit] = array_pad($argv, 6, '');

$fail = static function (string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
};

foreach ([$listPath, $junitPath, $knownPath] as $path) {
    if (!is_file($path)) {
        $fail("missing input file {$path}");
    }
}

// ---------------------------------------------------------------- inventory from PHPUnit's own metadata
$listed = [];
$list = new DOMDocument();
if (!@$list->load($listPath)) {
    $fail('cannot parse the --list-tests-xml inventory');
}
// PHPUnit 11: <testClass name=...><testMethod id="Class::method" name="method"/> (data-provider rows repeat the same id).
foreach ($list->getElementsByTagName('testMethod') as $node) {
    $id = (string) $node->getAttribute('id');
    if ($id === '' && $node->parentNode instanceof DOMElement) {
        $id = $node->parentNode->getAttribute('name') . '::' . $node->getAttribute('name');
    }
    $listed[$id] = true;
}
if ($listed === []) {
    $fail('the Recovery inventory is empty — refusing to report a false-green run');
}

// ---------------------------------------------------------------- executed results
$junit = new DOMDocument();
if (!@$junit->load($junitPath)) {
    $fail('cannot parse the JUnit report (did PHPUnit crash?)');
}
$executed = [];
$failed = [];
$skipped = [];
foreach ($junit->getElementsByTagName('testcase') as $case) {
    $class = str_replace('.', '\\', (string) $case->getAttribute('class'));
    // JUnit names a data-provider row `method with data set "label"` (or `#n`); --list-tests-xml names it `method#label`.
    $name = (string) preg_replace(['/ with data set "(.*)"$/s', '/ with data set #(\d+)$/'], ['#$1', '#$1'], (string) $case->getAttribute('name'));
    $id = $class . '::' . $name;
    $executed[$id] = true;
    foreach (['failure', 'error'] as $tag) {
        if ($case->getElementsByTagName($tag)->length > 0) {
            $failed[$id] = trim(strtok((string) $case->getElementsByTagName($tag)->item(0)?->textContent, "\n") ?: $tag);
        }
    }
    if ($case->getElementsByTagName('skipped')->length > 0) {
        $skipped[$id] = true;
    }
}

$known = [];
foreach (file($knownPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with(ltrim($line), '#')) {
        continue;
    }
    [$id, $why] = array_pad(explode("\t", $line, 2), 2, '');
    $known[trim($id)] = trim($why);
}

$notRun = array_diff_key($listed, $executed);
$baseId = static fn (string $id): string => (string) preg_replace('/#.*$/s', '', $id);
$unexpected = array_filter($failed, static fn (string $_m, string $id): bool => !isset($known[$baseId($id)]), ARRAY_FILTER_USE_BOTH);
$knownHit = array_filter($failed, static fn (string $_m, string $id): bool => isset($known[$baseId($id)]), ARRAY_FILTER_USE_BOTH);
$failedBase = array_fill_keys(array_map($baseId, array_keys($failed)), true);
$executedBase = array_fill_keys(array_map($baseId, array_keys($executed)), true);
$knownNowPassing = array_diff_key(array_intersect_key($known, $executedBase), $failedBase);

// ---------------------------------------------------------------- report (stdout + GitHub step summary)
$lines = [];
$lines[] = '## Recovery suite on MySQL';
$lines[] = '';
$lines[] = '- MySQL server version: `' . $mysqlVersion . '`';
$lines[] = '- Driver: `mysql` (ZENA_INVARIANTS_DB=mysql, migrate:fresh, phpunit.mysql.xml); excluded group: `uat-recovery-01-quarantine` only';
$lines[] = sprintf('- Inventory: %d listed, %d executed, %d not executed', count($listed), count($executed), count($notRun));
$lines[] = sprintf('- Failed/errored: %d (mapped to a documented baseline: %d, NEW: %d); skipped: %d', count($failed), count($knownHit), count($unexpected), count($skipped));
foreach ($knownHit as $id => $msg) {
    $lines[] = "  - KNOWN `{$id}` — {$known[$baseId($id)]}";
}
foreach ($unexpected as $id => $msg) {
    $lines[] = "  - **NEW** `{$id}` — {$msg}";
}
foreach (array_keys($skipped) as $id) {
    $lines[] = "  - SKIPPED `{$id}`";
}
foreach (array_keys($notRun) as $id) {
    $lines[] = "  - NOT EXECUTED `{$id}`";
}
foreach (array_keys($knownNowPassing) as $id) {
    $lines[] = "  - baseline entry now PASSES (remove it from recovery-mysql-known-failures.txt): `{$id}`";
}
$report = implode("\n", $lines) . "\n";
echo $report;
if (($summary = getenv('GITHUB_STEP_SUMMARY')) !== false && $summary !== '') {
    file_put_contents($summary, $report, FILE_APPEND);
}

// ---------------------------------------------------------------- verdict (no suppression)
if ($notRun !== []) {
    $fail(count($notRun) . ' listed Recovery tests were not executed');
}
if ($skipped !== []) {
    $fail(count($skipped) . ' Recovery tests were skipped although MySQL is reachable');
}
if ($failed !== []) {
    $fail(count($failed) . ' Recovery tests failed on MySQL (' . count($unexpected) . ' new, ' . count($knownHit) . ' documented baseline defects) — not suppressed');
}
if ((int) $phpunitExit !== 0) {
    $fail("PHPUnit exited {$phpunitExit} without a recorded failure");
}
echo "OK: the full Recovery inventory passed on MySQL {$mysqlVersion}.\n";
