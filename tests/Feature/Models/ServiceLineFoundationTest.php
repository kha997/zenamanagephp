<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Project;
use App\Models\ProjectServiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ServiceLineFoundationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function makeOpportunity(Tenant $tenant): Opportunity
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        return Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Opportunity for Service Line',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);
    }

    private function makeProject(Tenant $tenant): Project
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        return Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
    }

    // --- A: accepts exactly the three canonical values ---
    public function test_service_line_accepts_exactly_the_three_canonical_values(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        foreach (ServiceLine::VALUES as $line) {
            $row = $opportunity->serviceLines()->create([
                'service_line' => $line,
                'provenance' => ServiceLineProvenance::INFERRED,
            ]);
            $this->assertSame($line, $row->service_line);
        }

        $this->assertSame(3, $opportunity->serviceLines()->count());
    }

    // --- B: invalid service_line rejected ---
    public function test_invalid_service_line_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(InvalidArgumentException::class);

        try {
            $opportunity->serviceLines()->create([
                'service_line' => 'UNKNOWN',
                'provenance' => ServiceLineProvenance::INFERRED,
            ]);
        } finally {
            $this->assertSame(0, OpportunityServiceLine::query()->count());
        }
    }

    public function test_invalid_provenance_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(InvalidArgumentException::class);

        try {
            $opportunity->serviceLines()->create([
                'service_line' => ServiceLine::DESIGN,
                'provenance' => 'BOGUS',
            ]);
        } finally {
            $this->assertSame(0, OpportunityServiceLine::query()->count());
        }
    }

    public function test_opportunity_service_lines_relation_returns_seeded_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunityA = $this->makeOpportunity($tenant);
        $opportunityB = $this->makeOpportunity($tenant);

        $opportunityA->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $opportunityA->serviceLines()->create([
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);

        $this->assertEqualsCanonicalizing(
            [ServiceLine::DESIGN, ServiceLine::CONSTRUCTION],
            $opportunityA->serviceLines()->pluck('service_line')->all()
        );
        $this->assertSame(0, $opportunityB->serviceLines()->count());
    }

    public function test_project_service_lines_relation_returns_seeded_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $projectA = $this->makeProject($tenant);
        $projectB = $this->makeProject($tenant);

        $projectA->serviceLines()->create([
            'service_line' => ServiceLine::INSPECTION,
            'provenance' => ServiceLineProvenance::NEEDS_REVIEW,
        ]);

        $this->assertSame(
            [ServiceLine::INSPECTION],
            $projectA->serviceLines()->pluck('service_line')->all()
        );
        $this->assertSame(0, $projectB->serviceLines()->count());
    }

    public function test_tenant_scoped_visibility(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $opportunityA = $this->makeOpportunity($tenantA);
        $opportunityB = $this->makeOpportunity($tenantB);

        $opportunityA->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $opportunityB->serviceLines()->create([
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);

        app()->instance('tenant', $tenantA);
        try {
            $this->assertSame(1, OpportunityServiceLine::query()->count());
            $this->assertSame(ServiceLine::DESIGN, OpportunityServiceLine::query()->first()->service_line);
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    public function test_duplicate_membership_is_rejected_by_unique_constraint(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);

        $this->expectException(QueryException::class);
        $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);
    }

    public function test_tenant_id_is_derived_from_opportunity_parent_not_caller_input(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $row = $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);

        $this->assertSame((string) $tenant->id, (string) $row->tenant_id);
    }

    // --- I: cross-tenant write rejected (Opportunity side) ---
    public function test_cross_tenant_write_is_rejected_for_opportunity(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $opportunityOfTenantB = $this->makeOpportunity($tenantB);

        $row = new OpportunityServiceLine();
        $row->tenant_id = (string) $tenantA->id;
        $row->opportunity_id = (string) $opportunityOfTenantB->id;
        $row->service_line = ServiceLine::DESIGN;
        $row->provenance = ServiceLineProvenance::INFERRED;

        $this->expectException(RuntimeException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(0, OpportunityServiceLine::query()->count());
        }
    }

    // --- I: cross-tenant write rejected (Project side) ---
    public function test_cross_tenant_write_is_rejected_for_project(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $projectOfTenantB = $this->makeProject($tenantB);

        $row = new ProjectServiceLine();
        $row->tenant_id = (string) $tenantA->id;
        $row->project_id = (string) $projectOfTenantB->id;
        $row->service_line = ServiceLine::CONSTRUCTION;
        $row->provenance = ServiceLineProvenance::INFERRED;

        $this->expectException(RuntimeException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(0, ProjectServiceLine::query()->count());
        }
    }

    // --- Gate 3 Correction Round 1, item 1: acting/current tenant mismatch
    // rejected even with NO explicit child tenant_id (Opportunity side) ---
    public function test_acting_tenant_mismatch_is_rejected_for_opportunity_even_without_explicit_child_tenant_id(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $opportunityOfTenantB = $this->makeOpportunity($tenantB);

        app()->instance('tenant', $tenantA);
        try {
            $this->expectException(RuntimeException::class);
            try {
                $opportunityOfTenantB->serviceLines()->create([
                    'service_line' => ServiceLine::DESIGN,
                    'provenance' => ServiceLineProvenance::INFERRED,
                ]);
            } finally {
                $this->assertSame(0, OpportunityServiceLine::query()->withoutGlobalScope('tenant')->count());
            }
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    // --- Gate 3 Correction Round 1, item 1: acting/current tenant mismatch
    // rejected even with NO explicit child tenant_id (Project side) ---
    public function test_acting_tenant_mismatch_is_rejected_for_project_even_without_explicit_child_tenant_id(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $projectOfTenantB = $this->makeProject($tenantB);

        app()->instance('tenant', $tenantA);
        try {
            $this->expectException(RuntimeException::class);
            try {
                $projectOfTenantB->serviceLines()->create([
                    'service_line' => ServiceLine::CONSTRUCTION,
                    'provenance' => ServiceLineProvenance::INFERRED,
                ]);
            } finally {
                $this->assertSame(0, ProjectServiceLine::query()->withoutGlobalScope('tenant')->count());
            }
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    // --- Gate 3 Correction Round 1, item 1: matching acting tenant still
    // succeeds (regression guard on the new check) ---
    public function test_acting_tenant_matching_parent_tenant_is_permitted(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        app()->instance('tenant', $tenant);
        try {
            $row = $opportunity->serviceLines()->create([
                'service_line' => ServiceLine::DESIGN,
                'provenance' => ServiceLineProvenance::INFERRED,
            ]);
            $this->assertSame((string) $tenant->id, (string) $row->tenant_id);
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — A (invalid service_line), Opportunity side ---
    public function test_update_to_invalid_service_line_is_rejected_for_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $row = $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->service_line = 'BOGUS';

        $this->expectException(InvalidArgumentException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(ServiceLine::DESIGN, $row->fresh()->service_line);
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — B (invalid provenance), Opportunity side ---
    public function test_update_to_invalid_provenance_is_rejected_for_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $row = $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->provenance = 'BOGUS';

        $this->expectException(InvalidArgumentException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(ServiceLineProvenance::INFERRED, $row->fresh()->provenance);
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — C (parent reassigned cross-tenant), Opportunity side ---
    public function test_update_reassigning_parent_to_different_tenant_is_rejected_for_opportunity(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $opportunityA = $this->makeOpportunity($tenantA);
        $opportunityB = $this->makeOpportunity($tenantB);

        $row = $opportunityA->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->opportunity_id = (string) $opportunityB->id;

        $this->expectException(RuntimeException::class);
        try {
            $row->save();
        } finally {
            $fresh = $row->fresh();
            $this->assertSame((string) $opportunityA->id, (string) $fresh->opportunity_id);
            $this->assertSame((string) $tenantA->id, (string) $fresh->tenant_id);
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — A (invalid service_line), Project side ---
    public function test_update_to_invalid_service_line_is_rejected_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = $this->makeProject($tenant);

        $row = $project->serviceLines()->create([
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->service_line = 'BOGUS';

        $this->expectException(InvalidArgumentException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(ServiceLine::CONSTRUCTION, $row->fresh()->service_line);
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — B (invalid provenance), Project side ---
    public function test_update_to_invalid_provenance_is_rejected_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = $this->makeProject($tenant);

        $row = $project->serviceLines()->create([
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->provenance = 'BOGUS';

        $this->expectException(InvalidArgumentException::class);
        try {
            $row->save();
        } finally {
            $this->assertSame(ServiceLineProvenance::INFERRED, $row->fresh()->provenance);
        }
    }

    // --- Gate 3 Correction Round 1, item 2: update-path integrity — C (parent reassigned cross-tenant), Project side ---
    public function test_update_reassigning_parent_to_different_tenant_is_rejected_for_project(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $projectA = $this->makeProject($tenantA);
        $projectB = $this->makeProject($tenantB);

        $row = $projectA->serviceLines()->create([
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);
        $row->project_id = (string) $projectB->id;

        $this->expectException(RuntimeException::class);
        try {
            $row->save();
        } finally {
            $fresh = $row->fresh();
            $this->assertSame((string) $projectA->id, (string) $fresh->project_id);
            $this->assertSame((string) $tenantA->id, (string) $fresh->tenant_id);
        }
    }

    // --- Gate 3 Correction Round 1, item 6: (tenant_id, service_line) index exists on both new tables ---
    public function test_tenant_service_line_index_exists_on_both_tables(): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $oppIndexes = collect($connection->select("PRAGMA index_list('opportunity_service_lines')"))->pluck('name');
            $projIndexes = collect($connection->select("PRAGMA index_list('project_service_lines')"))->pluck('name');
            $this->assertTrue($oppIndexes->contains('opp_service_lines_tenant_line_index'));
            $this->assertTrue($projIndexes->contains('proj_service_lines_tenant_line_index'));
        } else {
            $oppIndexes = collect($connection->select('SHOW INDEX FROM opportunity_service_lines'))->pluck('Key_name');
            $projIndexes = collect($connection->select('SHOW INDEX FROM project_service_lines'))->pluck('Key_name');
            $this->assertTrue($oppIndexes->contains('opp_service_lines_tenant_line_index'));
            $this->assertTrue($projIndexes->contains('proj_service_lines_tenant_line_index'));
        }
    }

    // --- J: project-side backfill count is zero (no GAP-046 mechanism populates it) ---
    public function test_project_service_lines_table_has_zero_rows_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeProject($tenant);

        $this->assertSame(0, ProjectServiceLine::query()->count());
    }

    public function test_migration_round_trip_leaves_no_trace(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('opportunity_service_lines'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('project_service_lines'));

        \Illuminate\Support\Facades\Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]);

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('opportunity_service_lines'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('project_service_lines'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('opportunities'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('projects'));

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * @group mysql-parity
     *
     * Gate 3 Correction Round 1, item 6 (canonical MySQL-parity CI
     * coverage) — table/migration behavior. Runs only under
     * `--group=mysql-parity` against a real MySQL connection (this repo's
     * canonical mechanism); a local Docker harness alone is not
     * sufficient evidence per the Owner's correction.
     */
    public function test_migration_creates_expected_tables_and_columns_on_real_mysql(): void
    {
        foreach (['opportunity_service_lines', 'project_service_lines'] as $table) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table));
            foreach (['id', 'tenant_id', 'service_line', 'provenance', 'source', 'created_by', 'created_at', 'updated_at'] as $column) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Schema::hasColumn($table, $column),
                    "expected column [{$column}] on table [{$table}]"
                );
            }
        }
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('opportunity_service_lines', 'opportunity_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('project_service_lines', 'project_id'));
    }

    /**
     * @group mysql-parity
     *
     * Gate 3 Correction Round 1, item 6 — unique membership constraint,
     * proven against real MySQL (a genuine duplicate-key QueryException
     * from MySQL's InnoDB engine, not SQLite's).
     */
    public function test_duplicate_membership_is_rejected_by_unique_constraint_on_real_mysql(): void
    {
        $tenant = Tenant::factory()->create();
        $opportunity = $this->makeOpportunity($tenant);

        $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);

        $this->expectException(QueryException::class);
        $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);
    }

    /**
     * @group mysql-parity
     *
     * Gate 3 Correction Round 1, item 6 — tenant-parent integrity
     * behavior, proven against real MySQL: both the explicit-child-
     * tenant-mismatch path and the acting/current-tenant-mismatch path
     * (Correction Round 1 item 1).
     */
    public function test_cross_tenant_writes_are_rejected_on_real_mysql(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $opportunityOfTenantB = $this->makeOpportunity($tenantB);

        $row = new OpportunityServiceLine();
        $row->tenant_id = (string) $tenantA->id;
        $row->opportunity_id = (string) $opportunityOfTenantB->id;
        $row->service_line = ServiceLine::DESIGN;
        $row->provenance = ServiceLineProvenance::INFERRED;

        try {
            $row->save();
            $this->fail('expected RuntimeException for explicit cross-tenant write');
        } catch (RuntimeException $e) {
            $this->assertSame(0, OpportunityServiceLine::query()->withoutGlobalScope('tenant')->count());
        }

        app()->instance('tenant', $tenantA);
        try {
            try {
                $opportunityOfTenantB->serviceLines()->create([
                    'service_line' => ServiceLine::CONSTRUCTION,
                    'provenance' => ServiceLineProvenance::INFERRED,
                ]);
                $this->fail('expected RuntimeException for acting-tenant-context mismatch');
            } catch (RuntimeException $e) {
                $this->assertSame(0, OpportunityServiceLine::query()->withoutGlobalScope('tenant')->count());
            }
        } finally {
            app()->forgetInstance('tenant');
        }
    }
}
