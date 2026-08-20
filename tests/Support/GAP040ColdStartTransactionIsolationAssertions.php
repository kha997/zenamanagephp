<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared GAP-040 cold-start transaction-isolation regression proof.
 * Consumed by one test class per Gate-1-approved real-MySQL surface.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    /**
     * Forces the next parent::setUp() to genuinely re-run migrate:fresh
     * before opening this test's RefreshDatabase transaction, so the
     * cold-start case is deterministically observed regardless of whether
     * this class happens to be first in its process — rather than relying
     * on file/class discovery order. Safe to call unconditionally: it only
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

    protected function assertColdStartInvariantHeld(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('This proof only exercises the GAP-040 invariant against a real MySQL connection; this test also carries a group not excluded from the default SQLite suite, so it is reachable there too — skip rather than fail is correct here.');
        }

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        fwrite(STDERR, "\n[GAP-040 probe] " . json_encode($probe) . "\n");

        if ($probe['table_existed_before_bootstrap']) {
            $this->markTestSkipped('zena_roles already existed before bootstrap ran — an earlier test class in this process already captured the genuine cold-start moment. This is expected and does not indicate a problem: the fix keeps the main transaction genuinely open (see the routes-guardrails.yml run where this exact class observed and proved the cold-start case directly), so the RBAC compat table persists for the rest of the process instead of being torn down and rebuilt every test the way the pre-fix code did.');
        }

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

        // Only meaningful once the fix populates bootstrap_connection_id;
        // absent pre-fix, where no separate session exists yet to compare.
        if (array_key_exists('bootstrap_connection_id', $probe)) {
            $this->assertNotSame(
                $probe['main_connection_id'],
                $probe['bootstrap_connection_id'],
                'The RBAC compat bootstrap ran on the same MySQL session (CONNECTION_ID) as the main transacted connection — not a genuinely separate session.'
            );
        }
    }

    protected function writeMarkerRow(): string
    {
        $tenant = Tenant::factory()->create([
            'name' => 'gap040-cold-start-' . (string) Str::uuid(),
        ]);

        file_put_contents($this->coldStartMarkerFilePath(), $tenant->id);

        return $tenant->id;
    }

    protected function assertMarkerRowAbsentViaIndependentConnection(): void
    {
        $markerPath = $this->coldStartMarkerFilePath();

        if (!file_exists($markerPath)) {
            $this->markTestSkipped('No cold-start marker file found — the write-side test must run first, in the same process, before this verification test.');
        }

        $tenantId = trim((string) file_get_contents($markerPath));
        @unlink($markerPath);

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
            'A fresh, independent PDO connection (not reusing any Laravel-managed connection) still finds the cold-start test row — RefreshDatabase rollback did not take effect.'
        );
    }

    private function coldStartMarkerFilePath(): string
    {
        return storage_path('app/gap040-cold-start-marker.txt');
    }
}
