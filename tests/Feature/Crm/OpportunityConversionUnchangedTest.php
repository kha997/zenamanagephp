<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\ProjectServiceLine;
use App\Models\Tenant;
use App\Models\User;
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
            0,
            $opportunity->serviceLines()->count(),
            'Conversion must not implicitly classify the converting Opportunity either.'
        );
    }
}
