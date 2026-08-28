<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Project;
use App\Models\ProjectServiceLine;
use App\Models\Tenant;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class BackfillOpportunityServiceLinesTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const LEGACY_VALUES = [
        'architecture', 'interior', 'landscape', 'structure', 'mep',
        'construction', 'inspection', 'consulting', 'combined_package',
    ];

    /** @return array<string, Opportunity> keyed by legacy service_category value */
    private function seedOneOpportunityPerLegacyValue(Tenant $tenant): array
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], []);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Backfill Test Account',
        ]);

        $map = [];
        foreach (self::LEGACY_VALUES as $value) {
            $map[$value] = Opportunity::query()->create([
                'tenant_id' => (string) $tenant->id,
                'account_id' => (string) $account->id,
                'opportunity_name' => "Opportunity [{$value}]",
                'service_category' => $value,
                'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
                'sales_owner_id' => (string) $user->id,
                'created_by' => (string) $user->id,
            ]);
        }

        // Simulate an "unrecognized legacy value" row directly via the DB,
        // bypassing the app-level Rule::in validation that only exists at
        // the controller layer (service_category is NOT NULL at the DB
        // level — see 2026_07_09_100000_create_leads_table.php — so a
        // genuinely unrecognized string is the closest proxy for the
        // Gate-2 §7 "unrecognized" case; a literal NULL is not
        // representable in this column's current schema).
        $unrecognized = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Opportunity [unrecognized]',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);
        DB::table('opportunities')->where('id', $unrecognized->id)
            ->update(['service_category' => 'totally_unrecognized_value']);
        $map['__unrecognized__'] = $unrecognized->fresh();

        return $map;
    }

    // --- C: architecture family -> DESIGN/INFERRED only ---
    public function test_architecture_family_creates_only_design_inferred_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $map = $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');

        foreach (['architecture', 'interior', 'landscape', 'structure', 'mep'] as $legacy) {
            $rows = OpportunityServiceLine::query()
                ->where('opportunity_id', $map[$legacy]->id)
                ->get();

            $this->assertCount(1, $rows, "expected exactly 1 row for legacy [{$legacy}]");
            $this->assertSame(ServiceLine::DESIGN, $rows->first()->service_line);
            $this->assertSame(ServiceLineProvenance::INFERRED, $rows->first()->provenance);
        }
    }

    // --- D: construction -> CONSTRUCTION/INFERRED only ---
    public function test_construction_creates_only_construction_inferred_row(): void
    {
        $tenant = Tenant::factory()->create();
        $map = $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');

        $rows = OpportunityServiceLine::query()
            ->where('opportunity_id', $map['construction']->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(ServiceLine::CONSTRUCTION, $rows->first()->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $rows->first()->provenance);
    }

    // --- E: inspection/consulting/combined_package -> zero rows ---
    public function test_inspection_consulting_combined_package_create_zero_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $map = $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');

        foreach (['inspection', 'consulting', 'combined_package'] as $legacy) {
            $count = OpportunityServiceLine::query()
                ->where('opportunity_id', $map[$legacy]->id)
                ->count();
            $this->assertSame(0, $count, "expected zero rows for legacy [{$legacy}]");
        }
    }

    // --- F: unrecognized -> zero rows ---
    public function test_unrecognized_creates_zero_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $map = $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');

        $count = OpportunityServiceLine::query()
            ->where('opportunity_id', $map['__unrecognized__']->id)
            ->count();
        $this->assertSame(0, $count);
    }

    // --- G: never CONFIRMED ---
    public function test_backfill_never_creates_confirmed_provenance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');

        $this->assertSame(
            0,
            OpportunityServiceLine::query()->where('provenance', ServiceLineProvenance::CONFIRMED)->count()
        );
    }

    // --- H: idempotent ---
    public function test_backfill_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities');
        $firstRunCount = OpportunityServiceLine::query()->count();
        $firstRunSnapshot = OpportunityServiceLine::query()->orderBy('id')->get(['id', 'service_line', 'provenance'])->toArray();

        Artisan::call('service-lines:backfill-opportunities');
        $secondRunCount = OpportunityServiceLine::query()->count();
        $secondRunSnapshot = OpportunityServiceLine::query()->orderBy('id')->get(['id', 'service_line', 'provenance'])->toArray();

        $this->assertGreaterThan(0, $firstRunCount);
        $this->assertSame($firstRunCount, $secondRunCount);
        $this->assertSame($firstRunSnapshot, $secondRunSnapshot);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedOneOpportunityPerLegacyValue($tenant);

        Artisan::call('service-lines:backfill-opportunities', ['--dry-run' => true]);

        $this->assertSame(0, OpportunityServiceLine::query()->count());
    }

    // --- Gate 3 Correction Round 1, item 4: strengthened acceptance J —
    // the backfill must create the expected Opportunity-side row while
    // creating ZERO rows for the real, linked converted_project_id
    // Project, not merely an unrelated empty Project (which was too weak
    // to discriminate a Project-side-backfill bug from correct behavior). ---
    public function test_backfill_creates_zero_rows_for_the_linked_converted_project(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Converted Project Test Account',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Opportunity already converted to a real Project',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
            'converted_project_id' => (string) $project->id,
        ]);

        Artisan::call('service-lines:backfill-opportunities');

        $opportunityRows = OpportunityServiceLine::query()
            ->where('opportunity_id', $opportunity->id)
            ->get();
        $this->assertCount(1, $opportunityRows, 'expected exactly 1 Opportunity-side row for the converted Opportunity');
        $this->assertSame(ServiceLine::DESIGN, $opportunityRows->first()->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $opportunityRows->first()->provenance);

        $this->assertSame(
            0,
            ProjectServiceLine::query()->where('project_id', $project->id)->count(),
            'the GAP-046 backfill command must never populate project_service_lines for the linked historical Project (acceptance J).'
        );
    }

    public function test_service_category_column_is_never_modified_by_backfill(): void
    {
        $tenant = Tenant::factory()->create();
        $map = $this->seedOneOpportunityPerLegacyValue($tenant);
        $before = Opportunity::query()->orderBy('id')->pluck('service_category', 'id')->all();

        Artisan::call('service-lines:backfill-opportunities');

        $after = Opportunity::query()->orderBy('id')->pluck('service_category', 'id')->all();
        $this->assertSame($before, $after);
    }
}
