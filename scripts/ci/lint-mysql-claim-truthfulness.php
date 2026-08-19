<?php declare(strict_types=1);

/**
 * lint-mysql-claim-truthfulness — GAP-039 durable regression guard.
 *
 * Parses every targeted GitHub Actions workflow YAML file and flags any job
 * that provisions a `services:` entry with an `image: mysql:*` value UNLESS
 * that job satisfies one of:
 *   (a) at least one step's `run:` invokes a known fail-closed MySQL
 *       entrypoint (any script under scripts/ci/ matching *-mysql, or a
 *       step sourcing scripts/ci/lib/mysql-fail-closed.sh), or
 *   (b) the job never invokes PHPUnit at all (`php artisan test`,
 *       `vendor/bin/phpunit`, `php artisan dusk`) — i.e. MySQL is
 *       provisioned for some other real purpose (migrations for a
 *       Lighthouse run, etc.), which this guard is not concerned with.
 *
 * Usage: php scripts/ci/lint-mysql-claim-truthfulness.php [path-or-directory ...]
 * With no arguments, scans .github/workflows/*.yml.
 * Exit 0 = no violations. Exit 1 = at least one violation (printed to stdout).
 */

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/** @return string[] */
function mysql_lint_collect_targets(array $args): array
{
    if ($args === []) {
        $repoRoot = dirname(__DIR__, 2);
        return glob($repoRoot . '/.github/workflows/*.yml') ?: [];
    }

    $targets = [];
    foreach ($args as $arg) {
        if (is_dir($arg)) {
            $targets = array_merge($targets, glob(rtrim($arg, '/') . '/*.yml') ?: []);
        } elseif (is_file($arg)) {
            $targets[] = $arg;
        }
    }
    return $targets;
}

function mysql_lint_job_has_mysql_service(array $job): bool
{
    $services = $job['services'] ?? [];
    if (!is_array($services)) {
        return false;
    }
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $image = $service['image'] ?? '';
        if (is_string($image) && str_starts_with($image, 'mysql:')) {
            return true;
        }
    }
    return false;
}

/** @return string[] every `run:` string across every step, flattened */
function mysql_lint_job_run_commands(array $job): array
{
    $commands = [];
    $steps = $job['steps'] ?? [];
    if (!is_array($steps)) {
        return $commands;
    }
    foreach ($steps as $step) {
        if (is_array($step) && isset($step['run']) && is_string($step['run'])) {
            $commands[] = $step['run'];
        }
    }
    return $commands;
}

function mysql_lint_job_invokes_phpunit(array $job): bool
{
    foreach (mysql_lint_job_run_commands($job) as $run) {
        if (preg_match('/\bphp artisan test\b/', $run)
            || str_contains($run, 'vendor/bin/phpunit')
            || preg_match('/\bphp artisan dusk\b/', $run)) {
            return true;
        }
    }
    return false;
}

function mysql_lint_job_uses_fail_closed_entrypoint(array $job): bool
{
    foreach (mysql_lint_job_run_commands($job) as $run) {
        if (preg_match('#scripts/ci/[\w-]*-mysql\b#', $run)) {
            return true;
        }
        if (str_contains($run, 'scripts/ci/lib/mysql-fail-closed.sh')) {
            return true;
        }
    }
    return false;
}

$targets = mysql_lint_collect_targets(array_slice($argv, 1));
$violations = [];

foreach ($targets as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    try {
        $parsed = Yaml::parse($content);
    } catch (\Throwable $e) {
        $violations[] = basename($file) . ": (file): YAML parse error: " . $e->getMessage();
        continue;
    }
    if (!is_array($parsed) || !isset($parsed['jobs']) || !is_array($parsed['jobs'])) {
        continue;
    }

    foreach ($parsed['jobs'] as $jobName => $job) {
        if (!is_array($job)) {
            continue;
        }
        if (!mysql_lint_job_has_mysql_service($job)) {
            continue;
        }
        if (!mysql_lint_job_invokes_phpunit($job)) {
            continue; // MySQL provisioned for a non-PHPUnit purpose — not this guard's concern.
        }
        if (mysql_lint_job_uses_fail_closed_entrypoint($job)) {
            continue; // Routed through a known fail-closed entrypoint — trusted.
        }

        $violations[] = basename($file) . ": {$jobName}: provisions a mysql: service and invokes PHPUnit/Dusk, but no step routes through a fail-closed entrypoint (scripts/ci/*-mysql or scripts/ci/lib/mysql-fail-closed.sh) — this job will silently run SQLite instead of the MySQL it claims. See docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §5.";
    }
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        echo $violation . "\n";
        if (getenv('GITHUB_ACTIONS')) {
            echo "::error::" . $violation . "\n";
        }
    }
    echo "\n❌ lint-mysql-claim-truthfulness FAIL (" . count($violations) . " violation(s))\n";
    exit(1);
}

echo "✅ lint-mysql-claim-truthfulness PASS (" . count($targets) . " file(s) scanned)\n";
exit(0);
