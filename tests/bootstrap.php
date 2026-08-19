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
    // Real MySQL: this is a hard contract, not just a convenience —
    // ZENA_INVARIANTS_DB=mysql must never be set in a process whose schema
    // hasn't already been migrated by an earlier, separate OS process. Every
    // current caller upholds it: the 4 scripts/ci/*-mysql entrypoints
    // (zena-invariants-mysql, rfi-escalation-concurrency-mysql,
    // document-workflow-concurrency-mysql, treasury-check-constraints-mysql)
    // each run migrate:fresh --force before invoking PHPUnit, and every
    // workflow step/job that sets ZENA_INVARIANTS_DB=mysql directly
    // (routes-guardrails.yml's mysql-parity step; automated-testing.yml's
    // performance-tests; ci-cd.yml's "Prove GAP-032 migrations" step;
    // a11y-perf-testing.yml's performance-budget/performance-heavy/
    // e2e-tests) runs its own "php artisan migrate[:fresh] --force" step
    // first. A future caller that sets this variable without migrating
    // first will get "table doesn't exist" failures instead of an
    // auto-migrate — that's intentional (see below), not a bug to route
    // around by re-adding a Schema::hasTable() check here.
    //
    // Tell RefreshDatabase not to re-run its own internal migrate:fresh on
    // the first test that uses it (GAP-039):
    // that redundant, in-process migrate:fresh would re-execute every
    // migration — including 2025_09_20_145756_disable_foreign_keys_for_testing's
    // MySQL branch — on the SAME live connection the test's own queries
    // then use, silently leaving FOREIGN_KEY_CHECKS=0 for at least that
    // first test method.
    //
    // This must be set here, once, before any test class boots — not from
    // a TestCase hook: every test class does its own `use RefreshDatabase`,
    // and PHP resolves a trait method declared at the child class over one
    // merely inherited from a parent class, so a parent-class override of
    // a RefreshDatabase hook method is silently never called (confirmed by
    // reproduction — the override never executes for any of this repo's
    // RefreshDatabase-using test classes).
    \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;
}
