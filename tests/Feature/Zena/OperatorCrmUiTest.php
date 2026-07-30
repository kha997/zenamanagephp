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
            ['crm.view', 'crm.manage', 'crm.convert', 'contract.create']
        );
    }

    /**
     * Header set for postJson() calls against CSRF-protected 'web' routes.
     *
     * TestCase::ensureCsrfToken() deliberately skips auto-injecting a token
     * when the request declares an application/json Accept header (JSON API
     * calls are normally CSRF-exempt via routes/api.php's `except` list).
     * This route lives under the 'web' middleware group though, where
     * VerifyCsrfToken::runningUnitTests() is hard-disabled — so JSON POSTs
     * here still need an explicit token. We pin one via withSession().
     */
    private function jsonCsrfHeaders(): array
    {
        $this->withSession(['_token' => 'test-csrf-token']);

        return [
            'X-Tenant-ID' => (string) $this->tenant->id,
            'X-CSRF-TOKEN' => 'test-csrf-token',
        ];
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

    public function test_crm_index_renders_all_six_board_group_labels(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers)
            ->assertOk()
            ->assertSee('Mới')
            ->assertSee('Tư vấn / Khảo sát')
            ->assertSee('Báo giá')
            ->assertSee('Đàm phán / Hợp đồng')
            ->assertSee('Thắng')
            ->assertSee('Thua / Nurture');
    }

    public function test_update_stage_returns_json_shape_when_ajax(): void
    {
        $headers = $this->jsonCsrfHeaders();
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng AJAX test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội AJAX test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Đã cập nhật giai đoạn.',
            'data' => [
                'id' => (string) $opportunity->id,
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
                'is_terminal' => false,
            ],
        ]);
        $this->assertArrayNotHasKey('success', $response->json());
        $this->assertArrayNotHasKey('status', $response->json());
    }

    public function test_update_stage_json_returns_403_when_permission_missing(): void
    {
        $headers = $this->jsonCsrfHeaders();
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng 403 test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội 403 test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $viewer->id,
            'created_by' => (string) $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers)
            ->assertStatus(403);
    }

    public function test_update_stage_json_returns_422_when_lost_reason_missing(): void
    {
        $headers = $this->jsonCsrfHeaders();
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng 422 test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội 422 test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_LOST,
            ], $headers)
            ->assertStatus(422);
    }

    public function test_update_stage_json_blocks_terminal_transition(): void
    {
        $headers = $this->jsonCsrfHeaders();
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng terminal test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội terminal test',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers)
            ->assertStatus(422);

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_WON, $opportunity->pipeline_stage);
    }

    public function test_crm_index_renders_drag_drop_dom_contract(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng contract test',
        ]);
        $normalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội thường',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'estimated_fee' => 1250000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);
        $terminalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội đã thắng',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 500000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers);

        $response->assertOk();
        $html = $response->getContent();

        // Card thường: đủ data attribute, có handle + nút chuyển giai đoạn
        $this->assertStringContainsString('data-opportunity-id="' . $normalOpportunity->id . '"', $html);
        $this->assertStringContainsString('data-current-stage="' . Opportunity::STAGE_NEW_LEAD . '"', $html);
        $this->assertStringContainsString('data-terminal="0"', $html);
        $this->assertStringContainsString('data-amount="1250000000"', $html);
        $this->assertStringContainsString('crm-drag-handle', $html);
        $this->assertStringContainsString('crm-stage-transition-btn', $html);

        // Card terminal: data-terminal=1, KHÔNG có handle/nút trong phạm vi thẻ đó
        $this->assertStringContainsString('data-opportunity-id="' . $terminalOpportunity->id . '"', $html);
        $this->assertStringContainsString('data-terminal="1"', $html);

        // Cột dùng stable key, không phải label
        $this->assertStringContainsString('data-board-group="new"', $html);
        $this->assertStringContainsString('data-board-group="lost_nurture"', $html);
        $this->assertStringContainsString('data-default-entry-stage="' . Opportunity::STAGE_NEW_LEAD . '"', $html);
        $this->assertStringContainsString('data-requires-choice="1"', $html);
        $this->assertStringContainsString('data-choice-options="', $html);

        // Dialog dùng chung
        $this->assertStringContainsString('data-crm-stage-dialog', $html);
        $this->assertStringContainsString('crm-dialog-group-option', $html);
    }

    public function test_crm_index_terminal_card_has_no_drag_handle_or_transition_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng terminal-only test',
        ]);
        $terminalOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội đã thua',
            'pipeline_stage' => Opportunity::STAGE_LOST,
            'lost_reason' => 'Test',
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.crm.index'), $headers);

        $html = $response->getContent();
        $cardStart = strpos($html, 'data-opportunity-id="' . $terminalOpportunity->id . '"');
        $this->assertNotFalse($cardStart);

        // Cắt riêng đoạn HTML của thẻ này (đến </li> gần nhất) để không lẫn với thẻ khác
        $liEnd = strpos($html, '</li>', $cardStart);
        $cardHtml = substr($html, $cardStart, $liEnd - $cardStart);

        $this->assertStringNotContainsString('crm-drag-handle', $cardHtml);
        $this->assertStringNotContainsString('crm-stage-transition-btn', $cardHtml);
    }

    public function test_crm_index_hides_drag_handle_and_transition_button_for_view_only_user(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng view-only test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội view-only',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD, // KHÔNG terminal — chỉ permission là lý do ẩn
            'sales_owner_id' => (string) $viewer->id,
            'created_by' => (string) $viewer->id,
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('operator.crm.index'), $headers);

        $html = $response->getContent();
        $cardStart = strpos($html, 'data-opportunity-id="' . $opportunity->id . '"');
        $this->assertNotFalse($cardStart);

        $liEnd = strpos($html, '</li>', $cardStart);
        $cardHtml = substr($html, $cardStart, $liEnd - $cardStart);

        $this->assertStringContainsString('data-terminal="0"', $cardHtml); // xác nhận rõ: KHÔNG terminal
        $this->assertStringNotContainsString('crm-drag-handle', $cardHtml);
        $this->assertStringNotContainsString('draggable="true"', $cardHtml);
        $this->assertStringNotContainsString('crm-stage-transition-btn', $cardHtml);
    }

    public function test_update_stage_non_json_returns_friendly_redirect_when_opportunity_not_found(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->withSession(['_token' => 'test-csrf-token']);
        $headers['X-CSRF-TOKEN'] = 'test-csrf-token';

        $response = $this->actingAs($this->user)
            ->from(route('operator.crm.index'))
            ->post(route('operator.crm.opportunities.stage', '00000000-0000-0000-0000-000000000000'), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers);

        $response->assertRedirect(route('operator.crm.index'));
        $response->assertSessionHas('error', 'Không tìm thấy cơ hội bán hàng.');
    }

    public function test_update_stage_non_json_returns_friendly_redirect_on_unexpected_exception(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->withSession(['_token' => 'test-csrf-token']);
        $headers['X-CSRF-TOKEN'] = 'test-csrf-token';

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng unexpected-error test',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội unexpected-error test',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        // Force the service layer to blow up with something other than the
        // two typed exceptions the controller already special-cases, to
        // simulate an unexpected DB/infra failure surfacing from
        // OpportunityStageTransitionService::transition().
        $this->app->bind(\App\Services\Crm\OpportunityStageTransitionService::class, function () {
            return new class extends \App\Services\Crm\OpportunityStageTransitionService {
                public function transition(\App\Models\User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
                {
                    throw new \RuntimeException('Simulated unexpected failure');
                }
            };
        });

        $response = $this->actingAs($this->user)
            ->from(route('operator.crm.index'))
            ->post(route('operator.crm.opportunities.stage', $opportunity->id), [
                'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            ], $headers);

        $response->assertRedirect(route('operator.crm.index'));
        $response->assertSessionHas('error', 'Không thể xử lý yêu cầu.');

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_lead_conversion_accepts_custom_scope_summary(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Chi Mai',
            'project_description' => 'Mo ta goc tu Lead',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk();

        $convert = $this->actingAs($this->user)
            ->post(route('operator.crm.leads.convert', $lead->id), [
                'account_name' => 'Chi Mai',
                'opportunity_name' => 'Nha Chi Mai',
                'service_category' => 'interior',
                'service_scope_summary' => 'Tom tat da chinh sua boi sale, khac voi mo ta goc.',
            ], $headers);

        $convert->assertRedirect(route('operator.crm.index'));

        $opportunity = Opportunity::query()->where('opportunity_name', 'Nha Chi Mai')->firstOrFail();
        $this->assertSame('Tom tat da chinh sua boi sale, khac voi mo ta goc.', $opportunity->service_scope_summary);
    }

    public function test_lead_conversion_falls_back_to_project_description_without_scope_summary(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Anh Nam',
            'project_description' => 'Mo ta goc khong bi ghi de',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('operator.crm.leads.convert', $lead->id), [
                'account_name' => 'Anh Nam',
                'opportunity_name' => 'Nha Anh Nam',
                'service_category' => 'architecture',
            ], $headers);

        $opportunity = Opportunity::query()->where('opportunity_name', 'Nha Anh Nam')->firstOrFail();
        $this->assertSame('Mo ta goc khong bi ghi de', $opportunity->service_scope_summary);
    }

    public function test_leads_page_shows_ai_suggest_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Anh Duc',
            'project_description' => 'Nha xuong 200m2',
            'source' => 'hotline',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk()
            ->assertSee('Gợi ý AI')
            ->assertSee('service_scope_summary', false);
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
            ->assertStatus(302);

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
            ->assertStatus(302);

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

    public function test_contract_action_appears_for_won_opportunity_with_accepted_quote(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang won',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi da thang',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-008',
            'external_quote_id' => 'quote_ui_1',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 75000000, 'status' => 'ACCEPTED'],
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Tạo hợp đồng');

        $create = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.create-contract', $opportunity->id), [], $headers);
        $create->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('Tạo hợp đồng')
            ->assertSee('CTR-');
    }

    public function test_contract_action_hidden_for_non_won_opportunity(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang chua thang',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi dang xu ly',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_QUALIFIED,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('Tạo hợp đồng');
    }

    public function test_contract_card_shows_drift_warning_when_quote_changed_after_creation(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang drift',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bi drift',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-009',
            'external_quote_id' => 'quote_ui_2',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 90000000, 'status' => 'ACCEPTED'],
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.create-contract', $opportunity->id), [], $headers);

        // Simulate a later re-sync that pulled a different revision.
        $opportunity->refresh();
        $opportunity->external_quote_snapshot = ['revision' => 2, 'total' => 120000000, 'status' => 'ACCEPTED'];
        $opportunity->save();

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Báo giá đã đổi kể từ khi tạo hợp đồng');
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
