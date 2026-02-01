<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaMySqlMigrationRiskInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_gate_respects_migration_meta_column(): void
    {
        $this->assertTestingDatabaseMode();

        $mode = $this->getInvariantsDatabaseMode();
        $gateConnection = $mode === 'mysql' ? 'mysql' : 'sqlite';

        $this->assertSame(
            $gateConnection,
            config('database.default'),
            "The {$gateConnection} gate must remain on the {$gateConnection} connection during invariants mode '{$mode}'."
        );

        $this->assertTrue(
            Schema::hasColumn('audit_logs', 'meta'),
            'The audit_logs schema must include a meta column before we rely on JSON auditing in production.'
        );

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $metaPayload = ['migration_check' => ['state' => 'ok', 'timestamp' => now()->toDateTimeString()]];

        $log = AuditLog::create([
            'user_id' => (string) $user->id,
            'action' => 'zena.migration.risk.test',
            'entity_type' => 'migration_test',
            'tenant_id' => (string) $tenant->id,
            'meta' => $metaPayload,
        ]);

        $this->assertSame(
            $metaPayload,
            $log->meta,
            'Casting should return the same meta array immediately after creation.'
        );

        $fresh = AuditLog::findOrFail($log->id);
        $this->assertSame(
            $metaPayload,
            $fresh->meta,
            'Re-fetching the row must keep the meta JSON cast intact.'
        );
    }
}
