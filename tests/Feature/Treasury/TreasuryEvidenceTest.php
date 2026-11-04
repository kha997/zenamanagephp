<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryDocumentAttachment;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryDashboardService;
use App\Services\Treasury\TreasuryEvidenceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GAP-106 (Gate 2 S-2, E-1, M-1, D-1) — Treasury evidence files: Treasury-only storage read through the Treasury
 * policy, file rules, remove only before posting, tenant switch blocking submit without evidence, missing-evidence
 * outputs, and no leak into the general documents list.
 */
class TreasuryEvidenceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $viewer;

    private User $outsider;

    private TreasuryWallet $bank;

    private TreasuryFinancialParty $supplier;

    private ContractExpense $cost;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default', 'local'));
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->viewer = $this->userWithRole($this->tenant, 'project_member');
        $this->addMember($this->project, $this->viewer);
        $this->outsider = $this->userWithRole($this->tenant, 'no_treasury_role');
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->supplier = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'supplier', 'name' => 'NCC Thép']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000', 'transaction_date' => '2026-10-01', 'reference' => 'F-1',
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '100000', 'category' => 'material', 'description' => 'Vật tư',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attach_list_and_download_through_treasury_policy_only(): void
    {
        $expense = $this->draft('1000');
        $this->service()->attach($this->project, $this->owner, $expense, $this->pdf('hoa-don.pdf'), 'invoice', 'HĐ 0012');

        $attachment = TreasuryDocumentAttachment::query()->firstOrFail();
        $this->assertSame((string) $this->project->id, $attachment->project_id);
        Storage::disk(config('filesystems.default', 'local'))->assertExists($attachment->file_path);
        $this->assertStringStartsWith('treasury/' . $this->tenant->id . '/' . $this->project->id . '/', $attachment->file_path);
        $this->assertSame(1, AuditLog::query()->where('action', 'treasury.evidence.attached')->count());

        $url = route('operator.treasury.projects.evidence.download', [$this->project->id, $expense->id, $attachment->id]);
        $this->actingAs($this->viewer)->get($url, $this->headers)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('hoa-don.pdf');
        $this->actingAs($this->viewer)->get(route('operator.treasury.projects.evidence.show', [$this->project->id, $expense->id]), $this->headers)
            ->assertOk()->assertSee('hoa-don.pdf')->assertSee('HĐ 0012')->assertDontSee('treasury-evidence-form', false);

        $this->actingAs($this->outsider)->get($url, $this->headers)->assertStatus(302);

        $other = $this->projectFor($this->tenant);
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.evidence.download', [$other->id, $expense->id, $attachment->id]), $this->headers)->assertNotFound();
    }

    public function test_file_rules_and_who_may_attach(): void
    {
        $expense = $this->draft('1000');
        $service = $this->service();

        $this->assertRule(fn () => $service->attach($this->project, $this->owner, $expense, UploadedFile::fake()->create('script.php', 10, 'text/x-php'), 'invoice'), 'Chỉ nhận');
        $this->assertRule(fn () => $service->attach($this->project, $this->owner, $expense, UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), 'invoice'), '10 MB');
        $this->assertRule(fn () => $service->attach($this->project, $this->owner, $expense, $this->pdf('a.pdf'), 'selfie'), 'Loại chứng từ');
        $this->assertRule(fn () => $service->attach($this->project, $this->viewer, $expense, $this->pdf('a.pdf'), 'invoice'), 'không được đính');

        $file = $this->pdf('same.pdf');
        $service->attach($this->project, $this->accountant, $expense, $file, 'receipt');
        $this->assertRule(fn () => $service->attach($this->project, $this->owner, $expense, $file, 'receipt'), 'đã được đính');
    }

    public function test_remove_only_by_uploader_before_posting(): void
    {
        $expenses = app(TreasuryExpenseService::class);
        $service = $this->service();
        $expense = $this->draft('1000');
        $first = $service->attach($this->project, $this->owner, $expense, $this->pdf('a.pdf'), 'invoice');
        $kept = $service->attach($this->project, $this->owner, $expense, $this->pdf('b.pdf'), 'receipt');

        $this->assertRule(fn () => $service->remove($this->project, $this->accountant, $expense, $first), 'người tải lên');
        $service->remove($this->project, $this->owner, $expense, $first);
        Storage::disk(config('filesystems.default', 'local'))->assertMissing($first->file_path);

        $expenses->submit($this->project, $this->owner, $expense);
        $expenses->approve($this->project, $this->accountant, $expense->fresh());
        $this->assertRule(fn () => $service->remove($this->project, $this->owner, $expense->fresh(), $kept), 'chưa ghi sổ');
        $this->assertSame(1, TreasuryDocumentAttachment::query()->count());
    }

    public function test_tenant_switch_blocks_submit_without_evidence(): void
    {
        $expenses = app(TreasuryExpenseService::class);
        $service = $this->service();
        $this->assertFalse($service->evidenceRequired((string) $this->tenant->id));
        $expenses->submit($this->project, $this->owner, $this->draft('100'));

        $this->assertRule(fn () => $service->setEvidenceRequired($this->accountant, true), 'chủ doanh nghiệp');
        $service->setEvidenceRequired($this->owner, true);
        $this->assertTrue($service->evidenceRequired((string) $this->tenant->id));
        $this->assertSame(1, AuditLog::query()->where('action', 'treasury.evidence.policy_changed')->count());

        $expense = $this->draft('200');
        $this->assertRule(fn () => $expenses->submit($this->project, $this->owner, $expense), 'ít nhất một chứng từ');
        $service->attach($this->project, $this->owner, $expense, $this->pdf('x.pdf'), 'invoice');
        $this->assertSame(Document::STATUS_SUBMITTED, $expenses->submit($this->project, $this->owner, $expense->fresh())->status);
    }

    public function test_policy_switch_page_is_owner_only(): void
    {
        $this->actingAs($this->owner)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertSee('treasury-evidence-policy-form', false);
        $this->actingAs($this->owner)->post(route('operator.treasury.settings.evidence'), ['evidence_required' => '1'], $this->headers)
            ->assertRedirect(route('operator.treasury.index'));
        $this->assertTrue($this->service()->evidenceRequired((string) $this->tenant->id));

        $this->actingAs($this->accountant)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertDontSee('treasury-evidence-policy-form', false);
        $this->actingAs($this->accountant)->post(route('operator.treasury.settings.evidence'), ['evidence_required' => '0'], $this->headers);
        $this->assertTrue($this->service()->evidenceRequired((string) $this->tenant->id));
    }

    public function test_missing_evidence_card_alert_report_and_filter(): void
    {
        $withFile = $this->posted('3000', withFile: true);
        $missing = $this->posted('500');
        $reversed = $this->posted('700');
        app(TreasuryPostingService::class)->reverse($this->project, $this->accountant, $reversed, ['transaction_date' => '2026-10-10', 'description' => 'Nhầm']);
        $this->draft('900');

        $dashboard = app(TreasuryDashboardService::class)->dashboard($this->project);
        $this->assertSame(1, $dashboard['cards']['missing_evidence_count']);
        $this->assertSame('500.00', $dashboard['cards']['missing_evidence_amount']);
        $alert = collect($dashboard['alerts'])->firstWhere('code', 'missing_evidence');
        $this->assertSame(1, $alert['count']);

        $report = app(TreasuryReportService::class)->report($this->project, 'missing_evidence');
        $this->assertSame('Thiếu chứng từ', $report['title']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(['2026-10-05', 'NCC Thép', '500.00'], array_slice($report['rows'][0], 0, 3));

        $filtered = app(TreasuryReportService::class)->registerQuery($this->project, ['missing_evidence' => '1'])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([(string) $missing->id], $filtered);
        $this->assertNotContains((string) $withFile->id, $filtered);
    }

    public function test_project_page_shows_counts_and_files_never_reach_general_documents(): void
    {
        $expense = $this->posted('3000', withFile: true);

        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)
            ->assertOk()->assertSee('Chứng từ (1)');

        $this->actingAs($this->owner)->get(route('app.documents'), $this->headers)
            ->assertOk()->assertDontSee('evidence-file.pdf');
        $this->assertSame(0, DB::table('documents')->count());
        $this->assertSame(1, TreasuryDocumentAttachment::query()->where('financial_document_id', $expense->id)->count());
    }

    public function test_register_query_count_does_not_grow_with_attachments(): void
    {
        $this->posted('100', withFile: true);
        $few = $this->pageQueries();
        foreach (range(1, 5) as $i) {
            $this->posted((string) (200 + $i), withFile: true);
        }
        $many = $this->pageQueries();

        $this->assertSame($few, $many);
    }

    private function pageQueries(): int
    {
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function service(): TreasuryEvidenceService
    {
        return app(TreasuryEvidenceService::class);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n" . $name . "\n%%EOF");
    }

    private function draft(string $amount): Document
    {
        return app(TreasuryExpenseService::class)->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->supplier->id,
            'amount' => $amount, 'transaction_date' => '2026-10-05',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $this->cost->id, 'amount' => $amount]],
        ]);
    }

    private function posted(string $amount, bool $withFile = false): Document
    {
        $expenses = app(TreasuryExpenseService::class);
        $draft = $this->draft($amount);
        if ($withFile) {
            $this->service()->attach($this->project, $this->owner, $draft, $this->pdf('evidence-file.pdf'), 'invoice');
        }
        $expenses->submit($this->project, $this->owner, $draft);

        return $expenses->approve($this->project, $this->accountant, $draft->fresh());
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
