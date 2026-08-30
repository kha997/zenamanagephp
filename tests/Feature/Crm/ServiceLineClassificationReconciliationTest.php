<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\OpportunityServiceLineClassificationService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-048 §5/§18 — atomic desired-set canonical Service-Line reconciliation:
 * cases D, E, F, G, H, I, and cross-tenant security.
 */
class ServiceLineClassificationReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    // Route-level: proves the controller/route wiring, not just the service.
    public function test_route_confirms_service_lines_end_to_end(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Route fixture account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Route fixture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) $user->id,
        ]);

        $token = $user->createToken('gap048-route-test')->plainTextToken;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = $this->postJson(
            route('api.zena.crm.opportunities.service-lines', ['id' => $opportunity->id], false),
            ['service_lines' => [ServiceLine::DESIGN, ServiceLine::CONSTRUCTION]],
            $headers
        );

        $response->assertOk();
        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)->pluck('service_line')->sort()->values()->all();
        $this->assertSame([ServiceLine::CONSTRUCTION, ServiceLine::DESIGN], $rows);
    }

    private function service(): OpportunityServiceLineClassificationService
    {
        return app(OpportunityServiceLineClassificationService::class);
    }

    private function fixture(array $opportunityAttrs = []): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Reconciliation fixture account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Reconciliation fixture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) $user->id,
        ], $opportunityAttrs));

        return [$user, $opportunity];
    }

    // Case D — update() reconciles mapper-owned INFERRED row to the new scalar
    public function test_update_reconciles_mapper_owned_inferred_row_to_new_scalar(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Case D account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Case D',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) $user->id,
        ]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED, 'source' => 'writer:store']);

        $token = $user->createToken('gap048-case-d')->plainTextToken;
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Tenant-ID' => (string) $user->tenant_id, 'Authorization' => 'Bearer ' . $token];

        $response = $this->putJson(route('api.zena.crm.opportunities.update', ['id' => $opportunity->id], false), ['service_category' => 'construction'], $headers);

        $response->assertOk();
        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(ServiceLine::CONSTRUCTION, $rows->first()->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $rows->first()->provenance);
    }

    // Case E — update() never overwrites/demotes/deletes an existing CONFIRMED row
    public function test_update_never_overwrites_confirmed_row(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Case E account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Case E',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) $user->id,
        ]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED, 'source' => 'confirm:ui']);

        $token = $user->createToken('gap048-case-e')->plainTextToken;
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Tenant-ID' => (string) $user->tenant_id, 'Authorization' => 'Bearer ' . $token];

        $response = $this->putJson(route('api.zena.crm.opportunities.update', ['id' => $opportunity->id], false), ['service_category' => 'construction'], $headers);

        $response->assertOk();
        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertTrue($rows->contains(fn ($r) => $r->service_line === ServiceLine::DESIGN && $r->provenance === ServiceLineProvenance::CONFIRMED));
    }

    // Case F — active-stage last-CONFIRMED-line removal rejected
    public function test_reconcile_rejects_removing_last_confirmed_line_on_active_stage(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_SCOPE_DEFINED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $this->expectException(ValidationException::class);
        $this->service()->reconcile($user, $opportunity->fresh(), []);
    }

    // Case G — atomic confirmed-set replacement succeeds, no transient empty state observable
    public function test_reconcile_atomically_replaces_confirmed_line(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_SCOPE_DEFINED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = $this->service()->reconcile($user, $opportunity->fresh(), [ServiceLine::CONSTRUCTION]);

        $this->assertTrue($result->fresh()->hasConfirmedServiceLine());
        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(ServiceLine::CONSTRUCTION, $rows->first()->service_line);
    }

    // Case H — pre-scope Opportunity may legitimately return to zero
    public function test_reconcile_allows_prescope_return_to_zero(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = $this->service()->reconcile($user, $opportunity->fresh(), []);

        $this->assertFalse($result->fresh()->hasConfirmedServiceLine());
    }

    // Case I — multiple CONFIRMED lines survive as a set
    public function test_multiple_confirmed_lines_survive_as_a_set(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $this->service()->reconcile($user, $opportunity->fresh(), [ServiceLine::DESIGN, ServiceLine::CONSTRUCTION]);

        $lines = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)->pluck('service_line')->sort()->values()->all();
        $this->assertSame([ServiceLine::CONSTRUCTION, ServiceLine::DESIGN], $lines);
    }

    // Confirming a line reconciles a pre-existing mapper-owned INFERRED row to CONFIRMED
    // rather than duplicating it.
    public function test_confirm_promotes_existing_inferred_row_in_place(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);
        $inferred = $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED]);

        $this->service()->reconcile($user, $opportunity->fresh(), [ServiceLine::DESIGN]);

        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($inferred->id, $rows->first()->id);
        $this->assertSame(ServiceLineProvenance::CONFIRMED, $rows->first()->provenance);
    }

    // Security — cross-tenant reconciliation attempt rejected
    public function test_reconcile_rejects_cross_tenant_opportunity(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actorB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenantA->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Cross-tenant fixture account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $ownerA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenantA->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cross-tenant fixture',
            'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            'created_by' => (string) $ownerA->id,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service()->reconcile($actorB, $opportunity, [ServiceLine::DESIGN]);
    }

    // Invalid Service-Line value rejected
    public function test_reconcile_rejects_invalid_service_line_value(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $this->expectException(ValidationException::class);
        $this->service()->reconcile($user, $opportunity, ['NOT_A_REAL_LINE']);
    }
}
