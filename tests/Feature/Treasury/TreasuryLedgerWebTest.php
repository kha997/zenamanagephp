<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-064 S2 — ledger actions on the project Treasury page.
 */
class TreasuryLedgerWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private TreasuryFinancialParty $investor;
    private TreasuryWallet $bank;
    private TreasuryWallet $cash;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Chủ đầu tư A']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Ngân hàng']);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Tiền mặt']);
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    public function test_owner_declares_transfers_reverses_and_sees_the_register(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $page = route('operator.treasury.projects.show', $this->project->id);

        $this->actingAs($owner)->get($page, $this->headers)->assertOk()
            ->assertSee('treasury-funding-form', false)
            ->assertSee('treasury-transfer-form', false)
            ->assertSee('treasury-adjustment-form', false);

        $this->actingAs($owner)->post(route('operator.treasury.projects.funding.store', $this->project->id), [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000000',
            'transaction_date' => '2026-10-01', 'reference' => 'UNC-1',
        ], $this->headers)->assertRedirect($page)->assertSessionHas('success');

        $this->actingAs($owner)->post(route('operator.treasury.projects.transfers.store', $this->project->id), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->cash->id,
            'amount' => '30000000', 'transaction_date' => '2026-10-02',
        ], $this->headers)->assertRedirect($page);

        $this->actingAs($owner)->get($page, $this->headers)->assertOk()
            ->assertSee('100.000.000 ₫')
            ->assertSee('70.000.000 ₫')
            ->assertSee('30.000.000 ₫')
            ->assertSee('UNC-1')
            ->assertSee('Chủ đầu tư A');

        $funding = Document::query()->where('document_type', 'funding')->firstOrFail();
        $this->actingAs($owner)->post(route('operator.treasury.projects.documents.reverse', [$this->project->id, $funding->id]), [
            'transaction_date' => '2026-10-03', 'description' => 'Nhập nhầm',
        ], $this->headers)->assertRedirect($page);
        $this->assertSame(Document::STATUS_REVERSED, $funding->fresh()?->status);

        $this->actingAs($owner)->get($page, $this->headers)->assertOk()
            ->assertSee('Bút toán đảo')
            ->assertSee('text-rose-600', false);
    }

    public function test_duplicate_shows_a_warning_and_posts_when_confirmed(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $page = route('operator.treasury.projects.show', $this->project->id);
        $payload = [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '5000000',
            'transaction_date' => '2026-10-01', 'reference' => 'UNC-5',
        ];
        $this->actingAs($owner)->get($page, $this->headers);
        $this->actingAs($owner)->post(route('operator.treasury.projects.funding.store', $this->project->id), $payload, $this->headers);

        $this->actingAs($owner)->from($page)->post(route('operator.treasury.projects.funding.store', $this->project->id), $payload, $this->headers)
            ->assertRedirect($page)->assertSessionHas('treasury_duplicate');
        $this->assertSame(1, Document::query()->count());

        $this->actingAs($owner)->post(route('operator.treasury.projects.funding.store', $this->project->id), $payload + ['confirm_duplicate' => '1'], $this->headers)
            ->assertRedirect($page);
        $this->assertSame(2, Document::query()->count());
    }

    public function test_insufficient_balance_comes_back_as_a_form_error(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($owner)->get($page, $this->headers);

        $this->actingAs($owner)->from($page)->post(route('operator.treasury.projects.transfers.store', $this->project->id), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->cash->id,
            'amount' => '1', 'transaction_date' => '2026-10-02',
        ], $this->headers)->assertRedirect($page)->assertSessionHasErrors('source_wallet_id');
        $this->assertSame(0, Document::query()->count());
    }

    public function test_engineer_sees_transfer_sources_limited_to_held_wallets_and_no_adjust_or_reverse(): void
    {
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $engineer);
        $custodian = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $engineer->id]);
        TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ kỹ sư', 'custodian_party_id' => $custodian->id]);

        $response = $this->actingAs($engineer)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)->assertOk()
            ->assertSee('treasury-funding-form', false)
            ->assertSee('treasury-transfer-form', false)
            ->assertDontSee('treasury-adjustment-form', false);
        $html = (string) $response->getContent();
        $sources = substr($html, (int) strpos($html, 'id="t_source"'), 600);
        $this->assertStringContainsString('Quỹ kỹ sư', $sources);
        $this->assertStringNotContainsString('Ngân hàng', $sources);
    }

    public function test_viewer_sees_balances_without_any_form(): void
    {
        $designer = $this->userWithRole($this->tenant, 'Designer');
        $this->addMember($this->project, $designer);

        $this->actingAs($designer)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)->assertOk()
            ->assertSee('treasury-balances', false)
            ->assertDontSee('treasury-funding-form', false)
            ->assertDontSee('treasury-transfer-form', false);
    }
}
