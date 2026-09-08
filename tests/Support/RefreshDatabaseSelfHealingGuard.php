<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use RuntimeException;

/**
 * GAP-050 defense-in-depth (Gate 3, candidate C): fails loudly, at the
 * exact test boundary where it happens, if Laravel's own RefreshDatabase
 * self-healing mid-process re-migration fires unexpectedly.
 *
 * Mechanism this guards against (proven in
 * docs/owner-decisions/GAP-050/01-request.md and
 * docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md):
 * `Illuminate\Foundation\Testing\RefreshDatabase::beginDatabaseTransaction()`
 * resets `RefreshDatabaseState::$migrated` to `false` at a test's teardown
 * whenever `PDO::inTransaction()` unexpectedly reads `false`. The *next*
 * test in the same PHPUnit process that needs a refresh then silently
 * re-runs `migrate:fresh` mid-suite, on the shared live connection, with no
 * log line announcing it. GAP-050's primary Gate-3 remediation is per-file
 * PHPUnit process isolation (see scripts/ci/zena-invariants-mysql), which
 * is expected to prevent the volume/composition conditions Gate 2 §L found
 * necessary for this to occur. This guard exists so that IF it recurs
 * anyway — process isolation proving insufficient, or bypassed — the
 * failure is an immediate, named `RuntimeException` at the exact boundary,
 * not a confusing downstream symptom in an unrelated-looking test.
 *
 * Test-infrastructure only: lives under tests/, never referenced from app
 * code, and `enabled()` is gated on `GAP050_SELF_HEALING_GUARD=1`, which
 * only `scripts/ci/zena-invariants-mysql` exports — see `enabled()`'s own
 * docblock below for why this is narrower than `DB_CONNECTION=mysql`
 * (the original, too-broad gate this correction replaced after it broke
 * two unrelated real-MySQL jobs on live CI; see
 * docs/owner-decisions/GAP-050/03-release.md's "Live-CI correction"
 * section for that history, preserved there rather than erased).
 */
final class RefreshDatabaseSelfHealingGuard
{
    private static bool $everMigrated = false;

    /**
     * Reset process-local guard state. Only meaningful for the guard's own
     * unit tests, which simulate multiple "processes" within one PHPUnit
     * run and must not leak state between scenarios.
     */
    public static function reset(): void
    {
        self::$everMigrated = false;
    }

    /**
     * Call immediately before Laravel's own `refreshDatabase()` runs for a
     * test (i.e. before `parent::setUp()` in the base TestCase). If a
     * migration has already been observed once in this process and the
     * flag has since gone back to `false`, that is precisely the
     * self-healing signature — some earlier test's teardown reset it.
     *
     * @throws RuntimeException if self-healing is detected.
     */
    public static function beforeRefresh(string $testDescription): void
    {
        if (self::$everMigrated && RefreshDatabaseState::$migrated === false) {
            throw new RuntimeException(sprintf(
                'GAP-050 fail-loud guard: RefreshDatabase self-healing detected before %s — '
                . 'RefreshDatabaseState::$migrated was reset to false since an earlier test in '
                . 'this PHPUnit process, meaning Laravel is about to silently run another '
                . 'mid-process migrate:fresh on the shared connection. This is the exact '
                . 'mechanism documented in docs/owner-decisions/GAP-050/01-request.md and '
                . 'docs/superpowers/specs/2026-09-06-gap-050-gate2-mysql-transaction-isolation-design.md. '
                . 'Per-file test process isolation (scripts/ci/zena-invariants-mysql) is the '
                . 'primary Gate-3 containment for this — its recurrence inside one isolated '
                . 'process means the containment is insufficient here and must be investigated, '
                . 'not silenced.',
                $testDescription
            ));
        }
    }

    /**
     * Call immediately after Laravel's own `refreshDatabase()` has run for
     * a test (i.e. after `parent::setUp()`). Records that this process has
     * seen a genuine migration, so a later unexpected reset can be
     * detected by the next test's `beforeRefresh()` call.
     */
    public static function afterRefresh(): void
    {
        if (RefreshDatabaseState::$migrated === true) {
            self::$everMigrated = true;
        }
    }

    /**
     * Only active inside GAP-050's own per-file `zena-invariants-mysql`
     * invocation (which exports `GAP050_SELF_HEALING_GUARD=1`), never for
     * any other real-MySQL job (`mysql-parity`, Treasury,
     * `rfi-escalation-concurrency-mysql`, etc.). Those other jobs run
     * multiple files sharing ONE PHPUnit process and independently include
     * `ZenaTransactionIsolationColdStartTest`, whose
     * `GAP040ColdStartTransactionIsolationAssertions::forceGenuineColdStartForNextSetUp()`
     * *deliberately* resets `RefreshDatabaseState::$migrated` mid-process —
     * a legitimate, pre-existing test mechanism this guard must not
     * mistake for the self-healing bug. Scoping to this one explicit env
     * var (rather than the broader `DB_CONNECTION=mysql`) keeps the guard's
     * blast radius exactly at GAP-050's own job, per this Gate's explicit
     * instruction not to touch `mysql-parity` or Treasury.
     */
    public static function enabled(): bool
    {
        return getenv('GAP050_SELF_HEALING_GUARD') === '1';
    }
}
