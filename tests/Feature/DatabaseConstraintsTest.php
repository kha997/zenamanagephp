<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-039: extracted from QualityAssuranceTest::test_database_constraints,
 * which combined a unique-constraint assertion and a foreign-key-constraint
 * assertion in one method with two sequential expectException() calls — the
 * first exception ended the method before the FK assertion ever executed,
 * making it permanently dead code (see GAP-039 Gate 1 evidence §5/§6).
 * Split into two independent methods so both are reachable.
 */
class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'role' => 'user',
            'tenant_id' => $tenant->id,
        ]);
        $this->user->assignRole('client');
    }

    public function test_unique_constraint_violation_throws(): void
    {
        $this->actingAs($this->user);

        // dashboards has exactly one unique index: slug (see
        // database/migrations/2026_02_10_000001_create_dashboards_table.php).
        // There is no unique constraint on (user_id, name), so both rows
        // must carry a valid tenant_id or the second create() would throw
        // on an unrelated NOT NULL violation instead.
        Dashboard::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'slug' => 'gap-039-unique-constraint-test-slug',
        ]);

        $this->expectException(QueryException::class);
        // SQLSTATE 23000 alone doesn't discriminate a unique violation from
        // a NOT NULL/FK violation (all three share it on both SQLite and
        // MySQL) — that ambiguity is exactly what let this test mask an
        // unrelated NOT NULL failure before. Require the driver's own
        // unique-violation wording too.
        $this->expectExceptionMessageMatches('/23000.*(unique constraint|duplicate entry)/is');

        Dashboard::create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'name' => 'Another Dashboard',
            'slug' => 'gap-039-unique-constraint-test-slug', // duplicate slug -> unique constraint violation
        ]);
    }

    /**
     * @group mysql-parity
     *
     * Requires real MySQL: the disable-foreign-keys-for-testing migration's
     * SQLite branch does not reliably survive to the test connection in
     * this repo (GAP-039 Gate 1 evidence §4), so this assertion is only
     * meaningful against a real MySQL connection where the widgets.dashboard_id
     * foreign key is genuinely enforced.
     */
    public function test_foreign_key_constraint_violation_throws(): void
    {
        $this->actingAs($this->user);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/23000.*foreign key constraint/is');

        Widget::create([
            'tenant_id' => $this->user->tenant_id,
            'dashboard_id' => 999999, // non-existent dashboard -> FK constraint violation
            'type' => 'chart',
            'name' => 'Test Widget',
        ]);
    }
}
