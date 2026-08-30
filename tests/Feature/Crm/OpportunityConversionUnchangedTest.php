<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\ProjectServiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-046 acceptance K — proves runtime Opportunity -> Project Service-Line
 * propagation remains absent after this Work ID. This test exercises the
 * EXISTING, UNMODIFIED WON -> Project conversion path
 * (OpportunityController::convert(), reached via api.zena.crm.opportunities.convert)
 * exactly as it already behaves — it adds no wiring of its own. It is a
 * negative assertion: immediately after conversion, no rows exist in
 * project_service_lines for the newly created Project, and no rows exist
 * for the converting Opportunity in opportunity_service_lines either
 * (GAP-046 added no automatic classification on conversion in either
 * direction).
 */
class OpportunityConversionUnchangedTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_won_to_project_conversion_creates_zero_service_line_rows(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Conversion regression account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Conversion regression opportunity',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        // GAP-048 §12 — convert() is now gated on >=1 CONFIRMED canonical
        // Service Line; seed the minimum required so this test can still
        // reach the conversion call it is actually exercising. This does
        // not weaken the propagation proof below: the assertion changes
        // from "zero rows" to "still exactly the one seeded row, no
        // additional rows created by conversion."
        $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);

        $token = $user->createToken('propagation-regression-test')->plainTextToken;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = $this->postJson(
            route('api.zena.crm.opportunities.convert', ['id' => $opportunity->id], false),
            ['project_name' => 'Conversion regression project'],
            $headers
        );

        $response->assertStatus(201);

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $this->assertSame(
            0,
            ProjectServiceLine::query()->where('project_id', $opportunity->converted_project_id)->count(),
            'GAP-046 must not add any runtime Opportunity -> Project Service-Line propagation.'
        );
        $this->assertSame(
            1,
            $opportunity->serviceLines()->count(),
            'Conversion must not implicitly add further classification to the converting Opportunity beyond the one seeded CONFIRMED line the GAP-048 gate required.'
        );
    }

    /**
     * Gate 3 Correction Round 1, item 5 — strengthened acceptance K. The
     * original test above starts from an Opportunity with zero canonical
     * rows, which cannot discriminate a propagation implementation from
     * correct no-propagation behavior (there would be nothing to
     * propagate either way). This test seeds a REAL canonical Service-Line
     * membership on the Opportunity before conversion and proves: (a) that
     * membership row survives conversion completely unchanged, and (b) the
     * newly created Project still receives zero project_service_lines rows
     * — i.e. GAP-046 does not propagate even when there is real canonical
     * data available to propagate. Exercises the existing, unmodified
     * conversion endpoint only; OpportunityController is not touched.
     */
    public function test_won_to_project_conversion_does_not_propagate_existing_canonical_membership(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Conversion propagation-guard account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Conversion propagation-guard opportunity',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        // GAP-048 §12 — convert() is now gated on >=1 CONFIRMED canonical
        // Service Line, so the pre-existing membership this test seeds
        // must be CONFIRMED (an INFERRED-only row would now be rejected
        // by the gate before propagation could even be tested).
        $membership = $opportunity->serviceLines()->create([
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);

        $token = $user->createToken('propagation-guard-test')->plainTextToken;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = $this->postJson(
            route('api.zena.crm.opportunities.convert', ['id' => $opportunity->id], false),
            ['project_name' => 'Conversion propagation-guard project'],
            $headers
        );

        $response->assertStatus(201);

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $survivingRows = $opportunity->serviceLines()->get();
        $this->assertCount(1, $survivingRows, 'the pre-existing canonical membership must survive conversion unchanged.');
        $this->assertSame($membership->id, $survivingRows->first()->id);
        $this->assertSame(ServiceLine::DESIGN, $survivingRows->first()->service_line);
        $this->assertSame(ServiceLineProvenance::CONFIRMED, $survivingRows->first()->provenance);

        $this->assertSame(
            0,
            ProjectServiceLine::query()->where('project_id', $opportunity->converted_project_id)->count(),
            'GAP-046 must not propagate even when the Opportunity carries a real canonical membership row before conversion.'
        );
    }
}
