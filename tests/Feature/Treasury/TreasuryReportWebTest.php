<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-097 S6 — dashboard, register filters, reports, timeline, CSV and the
 * overview over the operator pages and the API (approved Gate 2 Option A).
 */
class TreasuryReportWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $accountant;

    private User $engineer;

    private TreasuryWallet $bank;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-27 09:00'));
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->project = $this->projectFor($this->tenant);
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Nhà đầu tư A']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $site = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id, 'destination_wallet_id' => (string) $this->bank->id,
            'amount' => '1000000', 'transaction_date' => '2026-09-01', 'reference' => 'F-1',
        ]);
        $posting->transfer($this->project, $owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $site->id, 'amount' => '400000', 'transaction_date' => '2026-10-02',
        ]);
        $posting->adjust($this->project, $this->accountant, [
            'wallet_id' => (string) $this->bank->id, 'direction' => 'decrease', 'amount' => '1000', 'transaction_date' => '2026-10-03', 'description' => '=HYPERLINK("x")',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_project_page_shows_the_dashboard_and_filters_the_register(): void
    {
        $page = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->engineer)->get($page, $this->headers)->assertOk()
            ->assertSee('Tổng quan ngân quỹ')
            ->assertSee('treasury-alert-unreconciled_funding', false)
            ->assertSee('Tiền nhận chưa đối soát quá 30 ngày')
            ->assertSee('treasury-reports-link', false)
            ->assertDontSee('treasury-register-export', false);

        $html = (string) $this->actingAs($this->engineer)->get($page . '?type=adjustment', $this->headers)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-testid="treasury-document-row"'));
        $html = (string) $this->actingAs($this->engineer)->get($page . '?date_from=2026-10-01&date_to=2026-10-31', $this->headers)->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-testid="treasury-document-row"'));

        $this->actingAs($this->accountant)->get($page, $this->headers)->assertOk()->assertSee('treasury-register-export', false);
    }

    public function test_reports_page_timeline_and_csv_permissions(): void
    {
        $reports = $this->url('operator.treasury.projects.reports.index');
        $this->actingAs($this->engineer)->get($reports . '?report=wallets', $this->headers)->assertOk()
            ->assertSee('Số dư ví')
            ->assertSee('Quỹ công trường')
            ->assertSee('400.000 ₫')
            ->assertDontSee('treasury-report-export', false);
        $this->actingAs($this->engineer)->get($reports . '?report=unknown', $this->headers)->assertOk()->assertSee('Dòng tiền theo tháng');

        $export = $this->url('operator.treasury.projects.reports.export', ['report' => 'corrections']);
        $this->actingAs($this->engineer)->get($export, $this->headers)->assertRedirect()->assertSessionHas('error');

        $response = $this->actingAs($this->accountant)->get($export, $this->headers)->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'Ngày,Loại,"Số tiền","Chứng từ gốc","Lý do","Người thực hiện"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $register = (string) $this->actingAs($this->accountant)->get($this->url('operator.treasury.projects.reports.export', ['report' => 'register']) . '?type=internal_transfer', $this->headers)
            ->assertOk()->streamedContent();
        $this->assertSame(2, substr_count(trim($register), "\n") + 1);
        $this->actingAs($this->accountant)->get($this->url('operator.treasury.projects.reports.export', ['report' => 'nope']), $this->headers)->assertNotFound();

        $timeline = $this->url('operator.treasury.projects.timeline');
        $this->actingAs($this->engineer)->get($timeline . '?month=2026-10', $this->headers)->assertOk()
            ->assertSee('Ví Bank chuyển 400.000 ₫ sang ví Quỹ công trường')
            ->assertSee('treasury-timeline-nav', false);
        $this->actingAs($this->engineer)->get($timeline . '?month=2026-09', $this->headers)->assertOk()
            ->assertSee('Nhà đầu tư A trả 1.000.000 ₫ vào ví Bank');
    }

    public function test_overview_on_the_treasury_index(): void
    {
        $this->actingAs($this->engineer)->get(route('operator.treasury.index', [], false), $this->headers)->assertOk()
            ->assertSee($this->project->name)
            ->assertSee('Tiền nhận chưa đối soát')
            ->assertSee('999.000 ₫')
            ->assertSee('treasury-project-alerts', false);
    }

    public function test_api_dashboard_register_timeline_reports_and_overview(): void
    {
        $headers = [
            'Accept' => 'application/json', 'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $this->engineer->createToken('treasury-report-test')->plainTextToken,
        ];
        $project = ['project' => (string) $this->project->id];
        $this->getJson(route('api.zena.treasury.reports.dashboard', $project, false), $headers)->assertOk()
            ->assertJsonPath('data.cards.held_total', '999000.00')
            ->assertJsonPath('data.alerts.0.code', 'unreconciled_funding');
        $this->getJson(route('api.zena.treasury.reports.register', $project, false) . '?type=funding', $headers)->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson(route('api.zena.treasury.reports.timeline', $project, false) . '?month=2026-10', $headers)->assertOk()
            ->assertJsonPath('data.events.0.kind', 'document');
        $this->getJson(route('api.zena.treasury.reports.timeline', $project, false) . '?month=2026-13', $headers)->assertStatus(422);
        $this->getJson(route('api.zena.treasury.reports.show', $project + ['report' => 'cashflow'], false), $headers)->assertOk()
            ->assertJsonPath('data.key', 'cashflow')
            ->assertJsonPath('data.rows.0.0', '2026-09');
        $this->getJson(route('api.zena.treasury.reports.show', $project + ['report' => 'nope'], false), $headers)->assertNotFound();
        $this->getJson(route('api.zena.treasury.reports.overview', [], false), $headers)->assertOk()
            ->assertJsonPath('data.0.project_id', (string) $this->project->id);

        $other = $this->projectFor($this->tenant);
        $this->getJson(route('api.zena.treasury.reports.dashboard', ['project' => (string) $other->id], false), $headers)->assertForbidden();
    }

    public function test_timeline_month_label_matches_the_chosen_month_late_in_a_month(): void
    {
        // Codex P2 / Owner amendment 1: on the 31st, February must not read as March.
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00'));
        $this->actingAs($this->engineer)->get($this->url('operator.treasury.projects.timeline') . '?month=2026-02', $this->headers)->assertOk()
            ->assertSee('<span class="text-sm font-semibold" data-testid="treasury-timeline-month">02/2026</span>', false)
            ->assertSee('trong tháng 02/2026')
            ->assertDontSee('03/2026');
    }

    public function test_api_overview_is_paginated_so_every_project_is_reachable(): void
    {
        // Codex P2 / Owner amendment 1: no silent cut-off.
        $headers = [
            'Accept' => 'application/json', 'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $this->accountant->createToken('treasury-overview-test')->plainTextToken,
        ];
        $this->projectFor($this->tenant);
        $this->projectFor($this->tenant);
        $url = route('api.zena.treasury.reports.overview', [], false);

        $this->getJson($url . '?per_page=2', $headers)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
        $this->getJson($url . '?per_page=2&page=2', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $name, array $params = []): string
    {
        return route($name, ['project' => (string) $this->project->id] + $params, false);
    }
}
