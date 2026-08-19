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
        // A bare `image: mysql` (no tag) is valid GitHub Actions syntax and
        // implicitly pulls `:latest` — must be treated identically to a
        // tagged `mysql:8.0`, otherwise a job can silently evade this guard
        // just by dropping its tag.
        if (is_string($image) && ($image === 'mysql' || str_starts_with($image, 'mysql:'))) {
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

// NOTE: this matches by *name pattern* only (any `run:` substring ending in
// `-mysql` at a word boundary, or the shared library path) — it does not
// verify that the referenced script's contents are actually fail-closed. A
// script merely named `*-mysql` that isn't genuinely fail-closed would be
// incorrectly trusted. This is a deliberate scope tradeoff (static text
// analysis of workflow YAML, not the referenced shell scripts) — acceptable
// because the recognized names are a short, deliberately curated list
// (scripts/ci/*-mysql, scripts/ci/lib/mysql-fail-closed.sh), not attacker-
// controlled or externally contributed.
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

    // A step that pairs ZENA_INVARIANTS_DB (set via the step's `env:`, or
    // inline in its `run:`) with an explicit phpunit.mysql.xml configuration
    // is equivalently fail-closed: that config has no
    // <env name="DB_CONNECTION" value="sqlite"/> override, so it is only
    // safe when paired with ZENA_INVARIANTS_DB=mysql — same underlying
    // guarantee as the scripts/ci/*-mysql entrypoints, just expressed as an
    // env var + PHPUnit config pairing inline in the workflow step instead
    // of a wrapper script.
    $steps = $job['steps'] ?? [];
    if (is_array($steps)) {
        foreach ($steps as $step) {
            if (!is_array($step) || !isset($step['run']) || !is_string($step['run'])) {
                continue;
            }
            $run = $step['run'];
            $usesMysqlConfig = str_contains($run, '--configuration phpunit.mysql.xml')
                || str_contains($run, '-c phpunit.mysql.xml');
            if (!$usesMysqlConfig) {
                continue;
            }
            $hasZenaInvariantsDb = str_contains($run, 'ZENA_INVARIANTS_DB');
            if (!$hasZenaInvariantsDb) {
                $env = $step['env'] ?? [];
                $hasZenaInvariantsDb = is_array($env) && array_key_exists('ZENA_INVARIANTS_DB', $env);
            }
            if ($hasZenaInvariantsDb) {
                return true;
            }
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

        $violations[] = basename($file) . ": {$jobName}: provisions a mysql: service and invokes PHPUnit/Dusk, but no step routes through a fail-closed entrypoint (scripts/ci/*-mysql or scripts/ci/lib/mysql-fail-closed.sh) — this job will silently run SQLite instead of the MySQL it claims. See docs/superpowers/plans/2026-08-19-gap-039-mysql-testing-integrity-implementation.md (Task 4).";
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
