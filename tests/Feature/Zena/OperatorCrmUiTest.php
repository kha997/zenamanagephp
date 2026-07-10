<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorCrmUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['crm.view', 'crm.manage', 'crm.convert']
        );
    }

    public function test_crm_ui_full_flow_lead_to_project(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers)
            ->assertOk()
            ->assertSee('Pipeline kinh doanh')
            ->assertSee('Hộp lead');

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk()
            ->assertSee('Ghi nhận lead mới');

        $capture = $this->actingAs($this->user)
            ->post(route('operator.crm.leads.store'), [
                'contact_hint' => 'Anh Tuan - 090xxxxxxx',
                'project_description' => 'Nha pho 4 tang',
                'source' => 'hotline',
            ], $headers);

        $lead = Lead::query()->firstOrFail();
        $capture->assertRedirect(route('operator.crm.leads'));
        $capture->assertSessionHas('success', 'Đã ghi nhận lead');
        $this->assertSame(Lead::STATUS_NEW, (string) $lead->status);

        $convert = $this->actingAs($this->user)
            ->post(route('operator.crm.leads.convert', $lead->id), [
                'account_name' => 'Anh Tuan',
                'opportunity_name' => 'Nha pho Anh Tuan',
                'service_category' => 'architecture',
                'estimated_fee' => 80000000,
            ], $headers);

        $convert->assertRedirect(route('operator.crm.index'));
        $convert->assertSessionHas('success', 'Đã chuyển lead thành cơ hội');

        $lead->refresh();
        $this->assertSame(Lead::STATUS_CONVERTED, (string) $lead->status);
        $opportunity = Opportunity::query()->firstOrFail();
        $this->assertSame((string) $opportunity->id, (string) $lead->converted_opportunity_id);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Nha pho Anh Tuan');

        $stage = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_WON,
            ], $headers);

        $stage->assertRedirect();
        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_WON, (string) $opportunity->pipeline_stage);

        $convertProject = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.convert', $opportunity->id), [
                'project_name' => 'Du an Anh Tuan',
            ], $headers);

        $convertProject->assertRedirect(route('operator.crm.index'));
        $convertProject->assertSessionHas('success', 'Đã tạo dự án từ cơ hội');

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);
    }

    public function test_lead_discard_and_account_creation(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Spam contact',
            'source' => 'other',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk();

        $discard = $this->actingAs($this->user)
            ->post(route('operator.crm.leads.discard', $lead->id), [], $headers);

        $discard->assertRedirect(route('operator.crm.leads'));
        $lead->refresh();
        $this->assertSame(Lead::STATUS_DISCARDED, (string) $lead->status);

        $this->actingAs($this->user)
            ->get(route('operator.crm.accounts'), $headers)
            ->assertOk();

        $createAccount = $this->actingAs($this->user)
            ->post(route('operator.crm.accounts.store'), [
                'display_name' => 'Cong ty XYZ',
                'account_type' => Account::TYPE_COMPANY,
            ], $headers);

        $createAccount->assertRedirect(route('operator.crm.accounts'));
        $this->assertDatabaseHas('accounts', ['display_name' => 'Cong ty XYZ']);
    }

    public function test_crm_pages_require_authentication(): void
    {
        $this->get(route('operator.crm.index'))->assertRedirect();
    }

    public function test_crm_actions_denied_without_manage_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);

        $this->actingAs($viewer)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.crm.leads.store'), [
                'contact_hint' => 'Should be denied',
            ], $headers)
            ->assertForbidden();

        $this->assertDatabaseMissing('leads', ['contact_hint' => 'Should be denied']);
    }

    public function test_opportunity_convert_denied_without_convert_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $salesUser = $this->createTenantUser($this->tenant, [], ['sales'], ['crm.view', 'crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang won',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi da thang',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $salesUser->id,
            'created_by' => (string) $salesUser->id,
        ]);

        $this->actingAs($salesUser)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk();

        $this->actingAs($salesUser)
            ->post(route('operator.crm.opportunities.convert', $opportunity->id), [], $headers)
            ->assertForbidden();

        $opportunity->refresh();
        $this->assertNull($opportunity->converted_project_id);
    }

    public function test_boq_link_and_sync_ui_flow_for_authorized_tenant(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1', 'revision' => 3, 'subtotal' => 100000000, 'vatAmount' => 8000000, 'total' => 108000000,
                'status' => 'ISSUED', 'calibration' => 'UNCALIBRATED', 'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tich hop BOQ',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi tich hop BOQ',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk();

        $link = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.boq-link', $opportunity->id), [
                'external_boq_project_code' => 'PRJ-001',
            ], $headers);
        $link->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertSame('PRJ-001', $opportunity->external_boq_project_code);

        $sync = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.boq-sync', $opportunity->id), [], $headers);
        $sync->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertSame(108000000.0, (float) $opportunity->external_quote_snapshot['total']);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee('Đã phát hành')
            ->assertSee('⚠ Chưa hiệu chỉnh')
            ->assertSee('https://zena-boq.example/quotes/quote_1', false);
    }

    public function test_boq_card_hides_mutation_actions_for_view_only_user(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $viewOnlyUser = $this->createTenantUser($this->tenant, [], ['staff'], ['crm.view']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang xem thoi',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi chi xem',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewOnlyUser)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Chưa liên kết báo giá')
            ->assertDontSee('Liên kết')
            ->assertDontSee('Đồng bộ báo giá');
    }

    public function test_boq_card_flags_synced_quote_older_than_14_days_as_stale(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A', 'zena_boq.base_url' => 'https://zena-boq.example']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang bao gia cu',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bao gia cu',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-002',
            'external_quote_id' => 'quote_old',
            'external_quote_snapshot' => ['total' => 50000000, 'status' => 'ISSUED', 'calibration' => 'CALIBRATED'],
            'external_quote_synced_at' => now()->subDays(20),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('text-amber-600', false);
    }

    public function test_boq_card_does_not_flag_recently_synced_quote_as_stale(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A', 'zena_boq.base_url' => 'https://zena-boq.example']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang bao gia moi',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bao gia moi',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-003',
            'external_quote_id' => 'quote_new',
            'external_quote_snapshot' => ['total' => 50000000, 'status' => 'ISSUED', 'calibration' => 'CALIBRATED'],
            'external_quote_synced_at' => now()->subDays(2),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('text-amber-600', false);
    }

    public function test_boq_card_is_hidden_for_non_authorized_tenant(): void
    {
        // Deliberately no Z.E.N.A tenant configured to match $this->tenant.
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang khong tich hop',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi khong co BOQ',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('external_boq_project_code')
            ->assertDontSee('Đồng bộ báo giá');
    }
}
