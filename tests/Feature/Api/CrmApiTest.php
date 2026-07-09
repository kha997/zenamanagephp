<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);
    }

    // -- Leads ---------------------------------------------------------

    public function test_can_capture_and_list_leads(): void
    {
        $response = $this->postJson($this->route('leads.store'), [
            'contact_hint' => 'Chị Lan - 0909xxxxxx Zalo',
            'project_description' => 'Nhà phố 4 tầng, quận 7',
            'source' => 'zalo',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Lead::STATUS_NEW)
            ->assertJsonPath('data.source', 'zalo');

        $this->assertDatabaseHas('leads', [
            'contact_hint' => 'Chị Lan - 0909xxxxxx Zalo',
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $index = $this->getJson($this->route('leads.index'), $this->headersFor($this->userA));
        $index->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_lead_convert_creates_account_and_opportunity(): void
    {
        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'contact_hint' => 'Anh Minh - 0988xxxxxx',
            'source' => 'hotline',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('leads.convert', ['id' => $lead->id]), [
            'account_name' => 'Anh Minh',
            'opportunity_name' => 'Nhà phố Anh Minh',
            'service_category' => 'architecture',
            'estimated_fee' => 50000000,
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['lead', 'account', 'opportunity']]);

        $lead->refresh();
        $this->assertSame(Lead::STATUS_CONVERTED, (string) $lead->status);
        $this->assertNotNull($lead->converted_opportunity_id);

        $this->assertDatabaseHas('accounts', ['display_name' => 'Anh Minh', 'tenant_id' => (string) $this->tenantA->id]);
        $this->assertDatabaseHas('opportunities', [
            'opportunity_name' => 'Nhà phố Anh Minh',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
        ]);
    }

    public function test_cannot_convert_already_converted_lead(): void
    {
        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'contact_hint' => 'Already converted',
            'source' => 'other',
            'status' => Lead::STATUS_CONVERTED,
            'captured_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('leads.convert', ['id' => $lead->id]), [
            'opportunity_name' => 'Should fail',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_can_discard_new_lead(): void
    {
        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'contact_hint' => 'Spam contact',
            'source' => 'other',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('leads.discard', ['id' => $lead->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(200);
        $this->assertDatabaseHas('leads', ['id' => (string) $lead->id, 'status' => Lead::STATUS_DISCARDED]);
    }

    public function test_leads_are_tenant_isolated(): void
    {
        Lead::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'contact_hint' => 'Tenant A lead',
            'source' => 'other',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('leads.index'), $this->headersFor($this->userB));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    // -- Accounts --------------------------------------------------------

    public function test_can_create_show_and_update_account(): void
    {
        $create = $this->postJson($this->route('accounts.store', [], []), [
            'display_name' => 'Công ty ABC',
            'account_type' => Account::TYPE_COMPANY,
            'phone' => '0281234567',
            'email' => 'contact@abc.vn',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)->assertJsonPath('data.display_name', 'Công ty ABC');
        $accountId = $create->json('data.id');

        $show = $this->getJson($this->route('accounts.show', ['id' => $accountId]), $this->headersFor($this->userA));
        $show->assertStatus(200)->assertJsonPath('data.email', 'contact@abc.vn');

        $update = $this->putJson($this->route('accounts.update', ['id' => $accountId]), [
            'display_name' => 'Công ty ABC Updated',
        ], $this->headersFor($this->userA));

        $update->assertStatus(200)->assertJsonPath('data.display_name', 'Công ty ABC Updated');
    }

    public function test_account_from_other_tenant_is_not_found(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Tenant A account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $response = $this->getJson($this->route('accounts.show', ['id' => $account->id]), $this->headersFor($this->userB));

        $response->assertStatus(404);
    }

    // -- Opportunities -----------------------------------------------------

    public function test_can_create_update_and_move_opportunity_stage(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khách hàng test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $create = $this->postJson($this->route('opportunities.store'), [
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Biệt thự Thảo Điền',
            'service_category' => 'architecture',
            'estimated_fee' => 120000000,
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)->assertJsonPath('data.pipeline_stage', Opportunity::STAGE_NEW_LEAD);
        $opportunityId = $create->json('data.id');

        $update = $this->putJson($this->route('opportunities.update', ['id' => $opportunityId]), [
            'estimated_fee' => 150000000,
        ], $this->headersFor($this->userA));
        $update->assertStatus(200)->assertJsonPath('data.estimated_fee', '150000000');

        $stage = $this->postJson($this->route('opportunities.stage', ['id' => $opportunityId]), [
            'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
        ], $this->headersFor($this->userA));
        $stage->assertStatus(200)->assertJsonPath('data.pipeline_stage', Opportunity::STAGE_QUALIFIED);
    }

    public function test_lost_stage_requires_reason(): void
    {
        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.stage', ['id' => $opportunity->id]), [
            'pipeline_stage' => Opportunity::STAGE_LOST,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_terminal_opportunity_cannot_change_stage_again(): void
    {
        $opportunity = $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_WON]);

        $response = $this->postJson($this->route('opportunities.stage', ['id' => $opportunity->id]), [
            'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_only_won_opportunity_can_convert_to_project(): void
    {
        $opportunity = $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $response = $this->postJson($this->route('opportunities.convert', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_won_opportunity_converts_to_project(): void
    {
        $opportunity = $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_WON]);

        $response = $this->postJson($this->route('opportunities.convert', ['id' => $opportunity->id]), [
            'project_name' => 'Dự án Biệt thự Thảo Điền',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['opportunity', 'project']]);

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $this->assertDatabaseHas('projects', ['name' => 'Dự án Biệt thự Thảo Điền']);
    }

    public function test_opportunity_convert_requires_crm_convert_permission(): void
    {
        $manageOnly = $this->createTenantUser($this->tenantA, [], ['sales'], ['crm.view', 'crm.manage']);
        $opportunity = $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_WON]);

        $response = $this->postJson($this->route('opportunities.convert', ['id' => $opportunity->id]), [], $this->headersFor($manageOnly));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson($this->route('leads.index'), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ]);

        $response->assertStatus(401);
    }

    private function createOpportunity(array $overrides = []): Opportunity
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khách hàng ' . uniqid(),
            'status' => Account::STATUS_ACTIVE,
        ]);

        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $this->tenantA->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội test',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->userA->id,
            'created_by' => (string) $this->userA->id,
        ], $overrides));
    }

    private function route(string $name, array $parameters = [], array $query = []): string
    {
        $url = route('api.zena.crm.' . $name, $parameters, false);

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('crm-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
