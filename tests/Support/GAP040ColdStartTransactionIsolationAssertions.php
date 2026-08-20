<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared GAP-040 cold-start transaction-isolation regression proof.
 * Consumed by one test class per Gate-1-approved real-MySQL surface.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    protected function assertColdStartInvariantHeld(): void
    {
        $this->assertSame(
            'mysql',
            config('database.default'),
            'This proof only exercises the GAP-040 invariant against a real MySQL connection.'
        );

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        $this->assertFalse(
            $probe['table_existed_before_bootstrap'],
            'zena_roles already existed before bootstrap ran — this run is not exercising the cold-start case. This test must be the first RefreshDatabase test executed in its process/job.'
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
