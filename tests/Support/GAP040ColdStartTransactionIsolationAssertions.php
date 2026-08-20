<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared GAP-040 cold-start transaction-isolation regression proof.
 * Consumed by one test class per Gate-1-approved real-MySQL surface.
 *
 * Corrected per Owner Gate 3 review: the writer test (proveColdStartAndWriteMarker)
 * is the ONLY method that forces a genuine cold start
 * (RefreshDatabaseState::$migrated = false). The verifier
 * (assertMarkerRowAbsentViaIndependentConnection) must NOT force one — doing so
 * would trigger another migrate:fresh between the writer's teardown and the
 * verifier's read, wiping the marker row regardless of whether RefreshDatabase's
 * rollback actually worked, producing a false green. Ordering and value-passing
 * between the two are guaranteed by PHPUnit's #[Depends] attribute on the
 * consuming test classes, not by method-name convention or discovery order.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    /**
     * Forces the next parent::setUp() to genuinely re-run migrate:fresh
     * before opening this test's RefreshDatabase transaction, so the
     * cold-start case is deterministically observed. Call this ONLY from
     * the writer test's setUp() — never from the verifier's — per the
     * class-level doc comment above. Safe to call unconditionally: it only
     * has an effect when the active connection is MySQL, and reads the
     * connection via getenv() (set by tests/bootstrap.php before any test
     * runs) rather than config(), since the app container does not exist
     * yet at the point this must run (before parent::setUp()).
     */
    protected function forceGenuineColdStartForNextSetUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    /**
     * Writer half of the proof. Must run under a setUp() that called
     * forceGenuineColdStartForNextSetUp() first. Proves the cold-start
     * invariant with hard, non-skippable assertions on real MySQL — a
     * failure to observe genuine cold start is a real defect (the forcing
     * mechanism itself broken), not a legitimate alternate state, so it is
     * NOT swallowed as a skip. Returns the written row's id for the
     * verifier to consume via #[Depends].
     *
     * @group stress
     */
    protected function proveColdStartAndWriteMarker(): string
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('dependency: this proof only exercises the GAP-040 invariant against a real MySQL connection; this test class is also reachable from the default SQLite suite (no excluded @group), so a legitimate skip is correct there.');
        }

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        fwrite(STDERR, "\n[GAP-040 probe] " . json_encode($probe) . "\n");

        $this->assertFalse(
            $probe['table_existed_before_bootstrap'],
            'zena_roles already existed before bootstrap ran despite forceGenuineColdStartForNextSetUp() — the deterministic cold-start forcing mechanism itself is broken. This must fail, not skip: a genuine cold start was required and did not occur.'
        );

        $this->assertSame(
            1,
            $probe['transaction_level_before_bootstrap'],
            'RefreshDatabase transaction was not open before the RBAC compat bootstrap ran.'
        );

        $this->assertSame(
            1,
            $probe['transaction_level_after_bootstrap'],
            'Transaction level changed across the RBAC compat bootstrap — the bootstrap DDL affected the main transacted connection (implicit-commit defect present).'
        );

        $this->assertTrue(
            $probe['pdo_in_transaction_before_bootstrap'],
            'PDO::inTransaction() was already false before the bootstrap ran — RefreshDatabase never actually had an open transaction on this connection.'
        );

        $this->assertTrue(
            $probe['pdo_in_transaction_after_bootstrap'],
            'PDO::inTransaction() is false after the RBAC compat bootstrap — this is direct, server-reported proof of the GAP-040 implicit-commit defect.'
        );

        $this->assertArrayHasKey(
            'bootstrap_connection_id',
            $probe,
            'No separate bootstrap session was recorded — the isolated-connection mechanism did not run.'
        );

        $this->assertNotSame(
            $probe['main_connection_id'],
            $probe['bootstrap_connection_id'],
            'The RBAC compat bootstrap ran on the same MySQL session (CONNECTION_ID) as the main transacted connection — not a genuinely separate session.'
        );

        $tenant = Tenant::factory()->create([
            'name' => 'gap040-cold-start-' . (string) Str::uuid(),
        ]);

        return $tenant->id;
    }

    /**
     * Verifier half of the proof. Must run under a setUp() that did NOT
     * call forceGenuineColdStartForNextSetUp() — no migrate:fresh, no
     * truncate, no reset of any kind may occur between the writer's
     * teardown and this read. The only mechanism that can make $tenantId
     * disappear is the rollback being proved. Queries via a brand-new,
     * non-persistent PDO connection — never a Laravel-managed connection —
     * so the read cannot be satisfied by in-process transaction visibility
     * artifacts. A missing/empty $tenantId is a hard failure (fail closed),
     * never a skip: PHPUnit's #[Depends] on the consuming test class is
     * what supplies it, and a broken dependency there is itself a defect
     * worth surfacing loudly, not hiding.
     *
     * @group stress
     */
    protected function assertMarkerRowAbsentViaIndependentConnection(string $tenantId): void
    {
        $this->assertNotSame('', $tenantId, 'No marker tenant id was supplied by the writer test — the #[Depends] value-passing itself is broken.');

        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.database')
            ),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            [\PDO::ATTR_PERSISTENT => false, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE id = ?');
        $stmt->execute([$tenantId]);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(
            0,
            $count,
            'A fresh, independent PDO connection (not reusing any Laravel-managed connection, and with no migrate:fresh/truncate/reset having run since the writer test wrote this row) still finds the cold-start test row — RefreshDatabase rollback did not take effect.'
        );
    }
}
