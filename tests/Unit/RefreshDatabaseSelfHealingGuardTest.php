<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\RefreshDatabaseSelfHealingGuard;

/**
 * GAP-050 Gate 3: isolated, no-database unit coverage for
 * Tests\Support\RefreshDatabaseSelfHealingGuard's own detection logic,
 * simulating RefreshDatabaseState::$migrated transitions directly rather
 * than depending on a real multi-hundred-second MySQL suite run to
 * exercise the boundary conditions. This is intentionally a plain
 * PHPUnit\Framework\TestCase (not Tests\TestCase / RefreshDatabase) — it
 * must not itself boot the app or touch a database.
 */
final class RefreshDatabaseSelfHealingGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RefreshDatabaseSelfHealingGuard::reset();
        RefreshDatabaseState::$migrated = false;
    }

    protected function tearDown(): void
    {
        RefreshDatabaseSelfHealingGuard::reset();
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_first_migration_in_a_process_does_not_trip_the_guard(): void
    {
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1');
        RefreshDatabaseState::$migrated = true;
        RefreshDatabaseSelfHealingGuard::afterRefresh();

        $this->addToAssertionCount(1);
    }

    public function test_a_stable_migrated_flag_across_many_tests_does_not_trip_the_guard(): void
    {
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1');
        RefreshDatabaseState::$migrated = true;
        RefreshDatabaseSelfHealingGuard::afterRefresh();

        for ($i = 2; $i <= 10; $i++) {
            // RefreshDatabase leaves $migrated === true across ordinary
            // tests once the first migration has happened; only the
            // self-healing reset flips it back to false mid-process.
            RefreshDatabaseSelfHealingGuard::beforeRefresh("test {$i}");
            RefreshDatabaseSelfHealingGuard::afterRefresh();
        }

        $this->addToAssertionCount(1);
    }

    public function test_self_healing_reset_between_tests_trips_the_guard(): void
    {
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1');
        RefreshDatabaseState::$migrated = true;
        RefreshDatabaseSelfHealingGuard::afterRefresh();

        // Simulate Laravel's own self-healing: some earlier test's
        // teardown detected PDO::inTransaction() === false and reset the
        // static flag — this is the exact mechanism GAP-050 Gate 1/2
        // proved happens mid-suite on real MySQL.
        RefreshDatabaseState::$migrated = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/self-healing detected before test 2/');

        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 2');
    }

    public function test_guard_is_a_no_op_before_any_migration_has_happened_in_the_process(): void
    {
        // $migrated starts false in a fresh process; that must not itself
        // be treated as a self-healing reset (there is nothing to heal
        // from yet).
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1');

        $this->addToAssertionCount(1);
    }

    public function test_reset_clears_process_local_state_between_scenarios(): void
    {
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1');
        RefreshDatabaseState::$migrated = true;
        RefreshDatabaseSelfHealingGuard::afterRefresh();

        RefreshDatabaseSelfHealingGuard::reset();
        RefreshDatabaseState::$migrated = false;

        // After reset(), this must behave like a fresh process again, not
        // report a phantom self-healing reset from the previous scenario.
        RefreshDatabaseSelfHealingGuard::beforeRefresh('test 1 (new process)');

        $this->addToAssertionCount(1);
    }
}
