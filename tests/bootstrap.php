<?php declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
require $autoloadPath;

$testingDirectory = dirname(__DIR__) . '/storage/framework/testing';
if (!is_dir($testingDirectory)) {
    mkdir($testingDirectory, 0775, true);
}
foreach (
    [
        dirname(__DIR__) . '/resources/views',
        dirname(__DIR__) . '/storage/framework/views',
        dirname(__DIR__) . '/bootstrap/cache',
    ] as $requiredDirectory
) {
    if (!is_dir($requiredDirectory)) {
        mkdir($requiredDirectory, 0775, true);
    }
}

$setEnv = static function (string $key, string $value): void {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

$setEnv('APP_ENV', 'testing');
$invariantsMode = strtolower((string) (getenv('ZENA_INVARIANTS_DB') ?: ($_ENV['ZENA_INVARIANTS_DB'] ?? $_SERVER['ZENA_INVARIANTS_DB'] ?? '')));

if ($invariantsMode !== 'mysql') {
    $runToken = sprintf('%d_%s', getmypid(), str_replace('.', '', sprintf('%.6f', microtime(true))));
    $sqlitePath = $testingDirectory . '/phpunit_' . $runToken . '.sqlite';

    if (!file_exists($sqlitePath)) {
        touch($sqlitePath);
    }

    $setEnv('DB_CONNECTION', 'sqlite');
    $setEnv('DB_DATABASE', $sqlitePath);

    register_shutdown_function(static function () use ($sqlitePath): void {
        if (is_file($sqlitePath)) {
            @unlink($sqlitePath);
        }
    });
} else {
    // Real MySQL: ZENA_INVARIANTS_DB=mysql must never be reached in a
    // process whose schema hasn't already been migrated by an earlier,
    // separate OS process. Every current caller upholds it: the 4
    // scripts/ci/*-mysql entrypoints (zena-invariants-mysql,
    // rfi-escalation-concurrency-mysql, document-workflow-concurrency-mysql,
    // treasury-check-constraints-mysql) each run migrate:fresh --force
    // before invoking PHPUnit, and every workflow step/job that sets
    // ZENA_INVARIANTS_DB=mysql directly (routes-guardrails.yml's
    // mysql-parity step; automated-testing.yml's performance-tests;
    // ci-cd.yml's "Prove GAP-032 migrations" step; a11y-perf-testing.yml's
    // performance-budget/performance-heavy/e2e-tests) runs its own
    // "php artisan migrate[:fresh] --force" step first.
    //
    // This precondition is now enforced, not just documented: both
    // in-repo call sites that could otherwise reset
    // RefreshDatabaseState::$migrated back to false —
    // TestCase::ensureTestingSchema() and
    // LegacyRouteRollbackTest::setUpBeforeClass() — are guarded by the
    // same ZENA_INVARIANTS_DB check, so a future test class cannot
    // silently re-arm RefreshDatabase's in-process migrate:fresh on this
    // path (found during final review: both previously reset
    // unconditionally, which would have re-executed
    // 2025_09_20_145756_disable_foreign_keys_for_testing's MySQL branch —
    // SET FOREIGN_KEY_CHECKS=0 — on the live test connection, silently
    // reintroducing the exact bug this file exists to prevent).
    //
    // Tell RefreshDatabase not to re-run its own internal migrate:fresh on
    // the first test that uses it (GAP-039): that redundant, in-process
    // migrate:fresh would re-execute every migration — including the FK
    // one above — on the SAME live connection the test's own queries then
    // use, silently leaving FOREIGN_KEY_CHECKS=0 for at least that first
    // test method.
    //
    // This must be set here, once, before any test class boots — not from
    // a TestCase hook: every test class does its own `use RefreshDatabase`,
    // and PHP resolves a trait method declared at the child class over one
    // merely inherited from a parent class, so a parent-class override of
    // a RefreshDatabase hook method is silently never called (confirmed by
    // reproduction — the override never executes for any of this repo's
    // RefreshDatabase-using test classes).
    //
    // Self-verify rather than trust the caller: phpunit.xml's
    // <env name="DB_CONNECTION" value="sqlite"/> has no force="true", so it
    // only fills an UNSET var — but a caller could set ZENA_INVARIANTS_DB=mysql
    // while forgetting to also export DB_CONNECTION=mysql as a real process
    // env var, which would run PHPUnit on SQLite with RefreshDatabase's
    // migration disabled. Fail loudly instead of letting that happen
    // silently.
    $mysqlDbConnection = $_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
    if ($mysqlDbConnection !== 'mysql') {
        fwrite(
            STDERR,
            "GAP-039: ZENA_INVARIANTS_DB=mysql requires DB_CONNECTION=mysql to be set as a real process env var (job env:, not just phpunit.xml's <env>). Got DB_CONNECTION=" . var_export($mysqlDbConnection, true) . ". Refusing to run — this would otherwise silently execute on SQLite with RefreshDatabase's migrate:fresh disabled.\n"
        );
        exit(1);
    }

    \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
}
