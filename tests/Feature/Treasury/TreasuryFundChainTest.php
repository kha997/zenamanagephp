<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryFundChain;
use App\Models\Treasury\TreasuryFundChainMember;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryFundChainService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-105 F-1 — manual fund chains: members are posted documents or live routes of the same project, at most one chain
 * each; membership never changes the ledger and is audited; manage_routes edits, view reads.
 */
class TreasuryFundChainTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $viewer;

    private TreasuryWallet $bank;

    private TreasuryWallet $site;

    private TreasuryFinancialParty $investor;

    private Document $funding;

    private Document $transfer;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->viewer = $this->userWithRole($this->tenant, 'project_member');
        $this->addMember($this->project, $this->viewer);
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Nhà đầu tư A']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->site = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);

        $posting = app(TreasuryPostingService::class);
        $this->funding = $posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '1000', 'transaction_date' => '2026-10-01', 'reference' => 'F-1',
        ]);
        $this->transfer = $posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->site->id, 'amount' => '600', 'transaction_date' => '2026-10-02',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_chain_groups_posted_documents_without_touching_balances(): void
    {
        $before = app(TreasuryBalanceService::class)->projectSummary($this->project)['wallets'];
        $service = $this->service();
        $chain = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-01', 'description' => 'Đợt 1']);
        $service->addMember($this->project, $this->owner, $chain, (string) $this->transfer->id, null);
        $service->addMember($this->project, $this->owner, $chain, (string) $this->funding->id, null);

        $trace = $service->trace($this->project, $chain);
        $this->assertSame([(string) $this->funding->id, (string) $this->transfer->id], array_column($trace, 'target_id'));
        $this->assertSame($before, app(TreasuryBalanceService::class)->projectSummary($this->project)['wallets']);

        $list = $service->chains($this->project);
        $this->assertSame(2, $list[0]['member_count']);
        $this->assertSame('1000.00', $list[0]['money_in']);
        $this->assertSame(3, AuditLog::query()->where('entity_type', 'treasury_fund_chain')->count());
    }

    public function test_member_rules(): void
    {
        $service = $this->service();
        $chain = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-01']);
        $other = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-02']);
        $service->addMember($this->project, $this->owner, $chain, (string) $this->funding->id, null);

        $this->assertRule(fn () => $service->addMember($this->project, $this->owner, $other, (string) $this->funding->id, null), 'đã thuộc một chuỗi');
        $this->assertRule(fn () => $service->addMember($this->project, $this->owner, $other, null, null), 'đúng một');
        $this->assertRule(fn () => $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-01']), 'đã có');

        $otherProject = $this->projectFor($this->tenant);
        $otherWallet = TreasuryWallet::factory()->create(['project_id' => $otherProject->id]);
        $foreign = app(TreasuryPostingService::class)->declareFunding($otherProject, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $otherWallet->id, 'amount' => '50', 'transaction_date' => '2026-10-01', 'reference' => 'F-X',
        ]);
        $this->assertRule(fn () => $service->addMember($this->project, $this->owner, $other, (string) $foreign->id, null), 'không thuộc dự án');

        $reversed = app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '70', 'transaction_date' => '2026-10-03', 'reference' => 'F-R',
        ]);
        app(TreasuryPostingService::class)->reverse($this->project, $this->owner, $reversed, ['transaction_date' => '2026-10-04', 'description' => 'Nhầm']);
        $this->assertRule(fn () => $service->addMember($this->project, $this->owner, $other, (string) $reversed->id, null), 'đã ghi sổ');
    }

    public function test_remove_member_frees_the_document(): void
    {
        $service = $this->service();
        $chain = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-01']);
        $member = $service->addMember($this->project, $this->owner, $chain, (string) $this->funding->id, null);
        $service->removeMember($this->project, $this->owner, $chain, (string) $member->id);

        $this->assertSame(0, TreasuryFundChainMember::query()->count());
        $other = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-02']);
        $service->addMember($this->project, $this->owner, $other, (string) $this->funding->id, null);
        $this->assertSame(1, TreasuryFundChainMember::query()->where('fund_chain_id', $other->id)->count());
    }

    public function test_trace_report_and_register_filter(): void
    {
        $service = $this->service();
        $chain = $service->create($this->project, $this->owner, ['chain_reference' => 'CQ-01']);
        $service->addMember($this->project, $this->owner, $chain, (string) $this->funding->id, null);

        $report = app(TreasuryReportService::class)->report($this->project, 'fund_chain_trace');
        $this->assertSame('Truy vết chuỗi quỹ', $report['title']);
        $this->assertSame([['CQ-01', '2026-10-01', 'Tiền nhận', Document::STATUS_POSTED_UNRECONCILED, '1000.00', 'F-1']], $report['rows']);

        $filtered = app(TreasuryReportService::class)->registerQuery($this->project, ['fund_chain_id' => (string) $chain->id])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([(string) $this->funding->id], $filtered);
    }

    public function test_web_pages_and_permissions(): void
    {
        // Starts a session so POSTs carry a CSRF token (tests/TestCase.php).
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.fund-chains.index', $this->project->id), $this->headers)
            ->assertOk()->assertSee('Chưa có chuỗi quỹ');
        $this->actingAs($this->owner)
            ->post(route('operator.treasury.projects.fund-chains.store', $this->project->id), ['chain_reference' => 'CQ-W', 'description' => 'Qua web'], $this->headers)
            ->assertRedirect();
        $chain = TreasuryFundChain::query()->where('chain_reference', 'CQ-W')->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('operator.treasury.projects.fund-chains.members.store', [$this->project->id, $chain->id]), ['member_financial_document_id' => (string) $this->funding->id], $this->headers)
            ->assertRedirect(route('operator.treasury.projects.fund-chains.show', [$this->project->id, $chain->id]));

        $this->actingAs($this->viewer)
            ->get(route('operator.treasury.projects.fund-chains.show', [$this->project->id, $chain->id]), $this->headers)
            ->assertOk()->assertSee('CQ-W')->assertSee('F-1')->assertDontSee('Bỏ khỏi chuỗi');
        $this->actingAs($this->viewer)
            ->get(route('operator.treasury.projects.fund-chains.index', $this->project->id), $this->headers)
            ->assertOk()->assertSee('CQ-W')->assertDontSee('treasury-fund-chain-form', false);

        // Viewer has no manage_routes: the rbac middleware refuses the write.
        $this->actingAs($this->viewer)
            ->post(route('operator.treasury.projects.fund-chains.store', $this->project->id), ['chain_reference' => 'CQ-V'], $this->headers);
        $this->assertFalse(TreasuryFundChain::query()->where('chain_reference', 'CQ-V')->exists());

        $other = $this->projectFor($this->tenant);
        $this->actingAs($this->owner)
            ->get(route('operator.treasury.projects.fund-chains.show', [$other->id, $chain->id]), $this->headers)
            ->assertNotFound();
    }

    private function service(): TreasuryFundChainService
    {
        return app(TreasuryFundChainService::class);
    }

    private function assertRule(callable $call, string $messagePart): void
    {
        try {
            $call();
            $this->fail('Expected TreasuryRuleViolation');
        } catch (TreasuryRuleViolation $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }
}
