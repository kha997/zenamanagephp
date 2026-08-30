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

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert', 'contract.create']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert', 'contract.create']);
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

    public function test_generic_update_cannot_overwrite_external_boq_fields(): void
    {
        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-EXISTING']);

        $response = $this->putJson($this->route('opportunities.update', ['id' => $opportunity->id]), [
            'opportunity_name' => 'Biệt thự cập nhật',
            'external_boq_project_code' => 'PRJ-HACKED',
            'external_quote_snapshot' => ['total' => 999999],
        ], $this->headersFor($this->userA));

        $response->assertStatus(200)->assertJsonPath('data.opportunity_name', 'Biệt thự cập nhật');

        $opportunity->refresh();
        $this->assertSame('PRJ-EXISTING', $opportunity->external_boq_project_code);
        $this->assertNull($opportunity->external_quote_snapshot);
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

        // GAP-048 §12/§13 — convert() is now gated on >=1 CONFIRMED canonical
        // Service Line.
        $opportunity->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

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

    public function test_can_link_opportunity_to_boq_project_when_tenant_authorized(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(200)->assertJsonPath('data.external_boq_project_code', 'PRJ-001');
        $this->assertDatabaseHas('opportunities', [
            'id' => (string) $opportunity->id,
            'external_boq_project_code' => 'PRJ-001',
        ]);
    }

    public function test_link_denied_for_non_authorized_tenant(): void
    {
        // Deliberately do not create/configure a Z.E.N.A tenant matching $this->tenantA.
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(403);
        $this->assertDatabaseHas('opportunities', [
            'id' => (string) $opportunity->id,
            'external_boq_project_code' => null,
        ]);
    }

    public function test_link_fails_closed_when_config_unset(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => null]);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(403);
    }

    public function test_sync_populates_snapshot_on_success(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1',
                'revision' => 3,
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(200)
            ->assertJsonPath('data.external_quote_snapshot.total', 108000000)
            ->assertJsonPath('data.external_quote_snapshot.calibration', 'UNCALIBRATED');

        $opportunity->refresh();
        $this->assertNotNull($opportunity->external_quote_synced_at);
        $this->assertSame('quote_1', $opportunity->external_quote_id);
        $this->assertSame(3, $opportunity->external_quote_snapshot['revision']);
    }

    public function test_sync_degrades_gracefully_when_zena_boq_unreachable(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/*' => \Illuminate\Support\Facades\Http::response(null, 500),
        ]);

        $opportunity = $this->createOpportunity([
            'external_boq_project_code' => 'PRJ-001',
            'external_quote_id' => 'quote_old',
            'external_quote_snapshot' => ['total' => 999, 'status' => 'ISSUED'],
        ]);
        $previousSyncedAt = $opportunity->external_quote_synced_at;

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        // Must not 500; must not wipe out the existing cached snapshot.
        $response->assertStatus(200);
        $opportunity->refresh();
        $this->assertSame(999.0, (float) $opportunity->external_quote_snapshot['total']);
        $this->assertSame('quote_old', $opportunity->external_quote_id);
        $this->assertEquals($previousSyncedAt, $opportunity->external_quote_synced_at);
    }

    public function test_sync_degrades_gracefully_on_malformed_200_response(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response(['id' => '', 'total' => null], 200),
        ]);

        $opportunity = $this->createOpportunity([
            'external_boq_project_code' => 'PRJ-001',
            'external_quote_id' => 'quote_old',
            'external_quote_snapshot' => ['total' => 999, 'status' => 'ISSUED'],
        ]);
        $previousSyncedAt = $opportunity->external_quote_synced_at;

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        // Must not 500; must not wipe out the existing cached snapshot with placeholder garbage.
        $response->assertStatus(200);
        $opportunity->refresh();
        $this->assertSame(999.0, (float) $opportunity->external_quote_snapshot['total']);
        $this->assertSame('quote_old', $opportunity->external_quote_id);
        $this->assertEquals($previousSyncedAt, $opportunity->external_quote_synced_at);
    }

    public function test_sync_requires_project_code_to_be_linked_first(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_sync_denied_for_non_authorized_tenant(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(403);
    }

    public function test_create_contract_auto_converts_and_creates_contract_pinned_to_quote(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'external_boq_project_code' => 'PRJ-004',
            'external_quote_id' => 'quote_won_1',
            'external_quote_snapshot' => [
                'revision' => 2,
                'total' => 250000000,
                'status' => 'ACCEPTED',
                'calibration' => 'CALIBRATED',
            ],
        ]);

        $this->assertNull($opportunity->converted_project_id);

        // GAP-048 §12/§13 — createContract() is now gated on >=1 CONFIRMED
        // canonical Service Line.
        $opportunity->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(201);

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $contract = \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($contract);
        $this->assertSame((string) $opportunity->converted_project_id, (string) $contract->project_id);
        $this->assertSame('quote_won_1', $contract->source_quote_id);
        $this->assertSame(2, $contract->source_quote_revision);
        $this->assertSame(250000000.0, (float) $contract->total_value);
        $this->assertSame('VND', $contract->currency);
        $this->assertSame('draft', $contract->status);
    }

    public function test_create_contract_reuses_project_when_already_converted(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $project = \App\Models\Project::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'name' => 'Du an da convert',
            'code' => 'PRJ-ALREADY1',
            'status' => 'planning',
        ]);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'external_boq_project_code' => 'PRJ-005',
            'external_quote_id' => 'quote_won_2',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 100000000, 'status' => 'ACCEPTED'],
        ]);

        // GAP-048 §12/§13 — createContract() is now gated on >=1 CONFIRMED
        // canonical Service Line.
        $opportunity->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(201);

        $contract = \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($contract);
        $this->assertSame((string) $project->id, (string) $contract->project_id);
    }

    public function test_create_contract_does_not_duplicate_on_second_call(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'external_boq_project_code' => 'PRJ-006',
            'external_quote_id' => 'quote_won_3',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 50000000, 'status' => 'ACCEPTED'],
        ]);

        // GAP-048 §12/§13 — createContract() is now gated on >=1 CONFIRMED
        // canonical Service Line.
        $opportunity->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

        $first = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );
        $first->assertStatus(201);

        $second = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );
        $second->assertStatus(200);

        $this->assertSame(
            1,
            \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->count()
        );
    }

    public function test_create_contract_requires_won_stage_and_accepted_quote(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_QUALIFIED,
            'external_boq_project_code' => 'PRJ-007',
            'external_quote_id' => 'quote_won_4',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 50000000, 'status' => 'ISSUED'],
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(422);
    }

    public function test_create_contract_requires_contract_create_permission(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $noContractCreate = $this->createTenantUser($this->tenantA, [], ['sales'], ['crm.view', 'crm.manage', 'crm.convert']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => Opportunity::STAGE_WON,
            'external_boq_project_code' => 'PRJ-PERM1',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 50000000, 'status' => 'ACCEPTED'],
        ]);

        // GAP-048 §12/§13 — createContract() is now gated on >=1 CONFIRMED
        // canonical Service Line.
        $opportunity->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($noContractCreate)
        );

        $response->assertStatus(403);

        $this->assertDatabaseMissing('contracts', ['source_opportunity_id' => (string) $opportunity->id]);

        // The auto-convert step (guarded by 'crm.convert', which this user does have) runs
        // BEFORE the 'contract.create' authorization check in createContract(), so the
        // opportunity IS converted even though contract creation is blocked afterward.
        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);
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
