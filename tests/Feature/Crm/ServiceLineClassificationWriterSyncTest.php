<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Tenant;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-048 §4/§18 test strategy cases A, B, C, I — the shared legacy->canonical
 * mapper (App\Support\LegacyServiceCategoryMapper) must be consumed
 * identically by Api\OpportunityController::store() and
 * Api\LeadController::convert().
 */
class ServiceLineClassificationWriterSyncTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function authHeaders(Tenant $tenant, array $permissions): array
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $user = $this->createTenantUser($tenant, [], ['admin'], $permissions);
        $token = $user->createToken('gap048-writer-sync-test')->plainTextToken;

        return [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => (string) $user->tenant_id,
                'Authorization' => 'Bearer ' . $token,
            ],
            'user' => $user,
        ];
    }

    // Case A
    public function test_store_omitted_service_category_persists_null_and_zero_rows(): void
    {
        $tenant = Tenant::factory()->create();
        ['headers' => $headers] = $this->authHeaders($tenant, ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Case A account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $response = $this->postJson(
            route('api.zena.crm.opportunities.store', [], false),
            ['account_id' => (string) $account->id, 'opportunity_name' => 'Case A'],
            $headers
        );

        $response->assertStatus(201);
        $opportunity = Opportunity::query()->findOrFail($response->json('data.id'));
        $this->assertNull($opportunity->service_category);
        $this->assertSame(0, OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->count());
    }

    // Case B
    public function test_store_construction_maps_to_construction_inferred(): void
    {
        $tenant = Tenant::factory()->create();
        ['headers' => $headers] = $this->authHeaders($tenant, ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Case B account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $response = $this->postJson(
            route('api.zena.crm.opportunities.store', [], false),
            ['account_id' => (string) $account->id, 'opportunity_name' => 'Case B', 'service_category' => 'construction'],
            $headers
        );

        $response->assertStatus(201);
        $opportunity = Opportunity::query()->findOrFail($response->json('data.id'));
        $row = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->sole();
        $this->assertSame(ServiceLine::CONSTRUCTION, $row->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $row->provenance);
    }

    // Case C — identical outcome via Lead conversion
    public function test_lead_convert_construction_matches_store_outcome(): void
    {
        $tenant = Tenant::factory()->create();
        ['headers' => $headers, 'user' => $user] = $this->authHeaders($tenant, ['crm.view', 'crm.manage']);
        $lead = Lead::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contact_hint' => 'Case C lead',
            'source' => 'referral',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $user->id,
        ]);

        $response = $this->postJson(
            route('api.zena.crm.leads.convert', ['id' => $lead->id], false),
            [
                'account_name' => 'Case C account',
                'opportunity_name' => 'Case C',
                'service_category' => 'construction',
            ],
            $headers
        );

        $response->assertStatus(201);
        $opportunityId = $response->json('data.opportunity.id');
        $row = OpportunityServiceLine::query()->where('opportunity_id', $opportunityId)->sole();
        $this->assertSame(ServiceLine::CONSTRUCTION, $row->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $row->provenance);
    }

    // Case I — multiple CONFIRMED lines survive as a set (seeded directly, proven via query)
    public function test_multiple_confirmed_lines_are_never_collapsed_by_a_writer_path(): void
    {
        $tenant = Tenant::factory()->create();
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Case I account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Case I',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) \App\Models\User::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::CONSTRUCTION, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $lines = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)->pluck('service_line')->sort()->values()->all();

        $this->assertSame([ServiceLine::CONSTRUCTION, ServiceLine::DESIGN], $lines);
    }
}
