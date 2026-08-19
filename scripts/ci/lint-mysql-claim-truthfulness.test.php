<?php declare(strict_types=1);

/**
 * Self-test for scripts/ci/lint-mysql-claim-truthfulness.php, using the
 * fixture workflows in scripts/ci/__fixtures__/mysql-claim-truthfulness/.
 * Run: php scripts/ci/lint-mysql-claim-truthfulness.test.php
 */

$root = dirname(__DIR__, 2);
$fixturesDir = $root . '/scripts/ci/__fixtures__/mysql-claim-truthfulness';
$lintScript = $root . '/scripts/ci/lint-mysql-claim-truthfulness.php';

$pass = 0;
$fail = 0;

function run_lint(string $lintScript, string $target): array
{
    $output = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg($lintScript) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $exitCode);
    return [$exitCode, implode("\n", $output)];
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/bad-unguarded-mysql-claim.yml');
if ($exitCode !== 0) {
    echo "PASS: bad-unguarded-mysql-claim.yml is flagged (exit $exitCode)\n";
    $pass++;
} else {
    echo "FAIL: bad-unguarded-mysql-claim.yml was NOT flagged (exit 0)\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-fail-closed.yml');
if ($exitCode === 0) {
    echo "PASS: good-fail-closed.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-fail-closed.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-non-phpunit-mysql-use.yml');
if ($exitCode === 0) {
    echo "PASS: good-non-phpunit-mysql-use.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-non-phpunit-mysql-use.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-no-mysql-service.yml');
if ($exitCode === 0) {
    echo "PASS: good-no-mysql-service.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-no-mysql-service.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/bad-bare-mysql-image-tag.yml');
if ($exitCode !== 0) {
    echo "PASS: bad-bare-mysql-image-tag.yml is flagged (exit $exitCode)\n";
    $pass++;
} else {
    echo "FAIL: bad-bare-mysql-image-tag.yml was NOT flagged (exit 0)\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-fail-closed-phpunit-mysql-config.yml');
if ($exitCode === 0) {
    echo "PASS: good-fail-closed-phpunit-mysql-config.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-fail-closed-phpunit-mysql-config.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/bad-phpunit-mysql-config-without-invariants-db.yml');
if ($exitCode !== 0) {
    echo "PASS: bad-phpunit-mysql-config-without-invariants-db.yml is flagged (exit $exitCode)\n";
    $pass++;
} else {
    echo "FAIL: bad-phpunit-mysql-config-without-invariants-db.yml was NOT flagged (exit 0)\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/bad-phpunit-mysql-config-empty-invariants-db.yml');
if ($exitCode !== 0) {
    echo "PASS: bad-phpunit-mysql-config-empty-invariants-db.yml is flagged (exit $exitCode)\n";
    $pass++;
} else {
    echo "FAIL: bad-phpunit-mysql-config-empty-invariants-db.yml was NOT flagged (exit 0)\nOutput:\n$output\n";
    $fail++;
}

echo "\nlint-mysql-claim-truthfulness.test.php: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
