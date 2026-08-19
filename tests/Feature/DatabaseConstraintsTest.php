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
 * making it permanently dead code (see docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md
 * §5/§6). Split into two independent methods so both are reachable.
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

        Dashboard::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Unique Dashboard',
        ]);

        $this->expectException(QueryException::class);

        Dashboard::create([
            'user_id' => $this->user->id,
            'name' => 'Unique Dashboard', // duplicate name -> unique constraint violation
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

        Widget::create([
            'tenant_id' => $this->user->tenant_id,
            'dashboard_id' => 999999, // non-existent dashboard -> FK constraint violation
            'type' => 'chart',
            'name' => 'Test Widget',
        ]);
    }
}
