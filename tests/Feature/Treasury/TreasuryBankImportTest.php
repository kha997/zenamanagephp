<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryBankStatementLine as Line;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry;
use App\Models\Treasury\TreasuryReconciliationEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryBankImportService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-107 I-1 — CSV bank statement import: parsing (delimiters, encodings, debit/credit, number and date formats),
 * duplicate file / line handling, match suggestions (direction, amount, ±3 days, one entry each), confirmation through
 * bank reconciliation without touching balances, undo reopens lines, ignore with reason, no document ever created.
 */
class TreasuryBankImportTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $viewer;

    private TreasuryWallet $bank;

    private TreasuryWallet $cash;

    private TreasuryFinancialParty $investor;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->viewer = $this->userWithRole($this->tenant, 'project_member');
        $this->addMember($this->project, $this->viewer);
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'VCB', 'wallet_type' => 'company_bank']);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Tiền mặt', 'wallet_type' => 'company_cash']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_parse_handles_delimiters_formats_and_debit_credit_columns(): void
    {
        $service = $this->service();
        $signed = $service->parse("Ngày;Số tiền;Diễn giải\n05/10/2026;1.500.000;Nhận tiền A\n2026-10-06;-200.000,50;Phí\n", ['date' => 0, 'amount' => 1, 'description' => 2]);
        $this->assertSame([], $signed['errors']);
        $this->assertSame(['2026-10-05', 'in', 150000000], [$signed['rows'][0]['date'], $signed['rows'][0]['direction'], $signed['rows'][0]['cents']]);
        $this->assertSame(['2026-10-06', 'out', 20000050], [$signed['rows'][1]['date'], $signed['rows'][1]['direction'], $signed['rows'][1]['cents']]);

        $split = $service->parse("Date,Debit,Credit,Ref\n2026-10-07,\"1,234,567.00\",,FT001\n2026-10-08,,500,FT002\nabc,1,,x\n", ['date' => 0, 'debit' => 1, 'credit' => 2, 'reference' => 3]);
        $this->assertSame(['out', 123456700, 'FT001'], [$split['rows'][0]['direction'], $split['rows'][0]['cents'], $split['rows'][0]['reference']]);
        $this->assertSame(['in', 50000], [$split['rows'][1]['direction'], $split['rows'][1]['cents']]);
        $this->assertSame(['Dòng 4: ngày không đọc được.'], $split['errors']);

        $win = $service->parse(mb_convert_encoding("Ngay\tTien\tNoi dung\n09/10/2026\t300\tCh\xC3\xA9p\n", 'Windows-1252', 'UTF-8'), ['date' => 0, 'amount' => 1, 'description' => 2]);
        $this->assertSame([], $win['errors']);
        $this->assertSame(30000, $win['rows'][0]['cents']);
    }

    public function test_import_suggest_confirm_and_undo(): void
    {
        $matchA = $this->funding('1500000', '2026-10-04', 'F-A');
        $this->funding('1500000', '2026-10-20', 'F-FAR');
        $this->funding('999', '2026-10-05', 'F-OTHER');

        $import = $this->service()->import($this->project, $this->accountant, $this->bank, $this->csv("Ngày,Số tiền,Nội dung\n05/10/2026,1.500.000,Nhận tiền A\n06/10/2026,-50.000,Phí ngân hàng\n"), ['date' => 0, 'amount' => 1, 'description' => 2], 'SK-10/2026');
        $this->assertSame(2, $import->line_count);

        $suggestions = $this->service()->suggestions($this->project, $import);
        $this->assertCount(1, $suggestions);
        $lineA = Line::query()->where('import_id', $import->id)->where('line_no', 2)->firstOrFail();
        $entryA = TreasuryLedgerEntry::query()->where('source_financial_document_id', $matchA->id)->firstOrFail();
        $this->assertSame((string) $entryA->id, $suggestions[(string) $lineA->id]['ledger_entry_id']);

        $balanceBefore = app(TreasuryBalanceService::class)->projectSummary($this->project)['wallets'];
        $documentsBefore = Document::query()->count();
        $reconciliation = $this->service()->confirm($this->project, $this->accountant, $import, [(string) $lineA->id => (string) $entryA->id]);
        $this->assertSame('bank_statement', $reconciliation->reconciliation_type);
        $this->assertSame('SK-10/2026', $reconciliation->external_reference);
        $this->assertSame(Line::STATUS_MATCHED, $lineA->fresh()->status);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $matchA->fresh()->status);
        $this->assertSame($balanceBefore, app(TreasuryBalanceService::class)->projectSummary($this->project)['wallets']);
        $this->assertSame($documentsBefore, Document::query()->count());

        $apply = TreasuryReconciliationEntry::query()->where('ledger_entry_id', $entryA->id)->firstOrFail();
        app(TreasuryReconciliationService::class)->undoEntry($this->project, $this->accountant, $apply, 'Ghép nhầm');
        $this->assertSame(Line::STATUS_UNMATCHED, $lineA->fresh()->status);
        $this->assertNull($lineA->fresh()->ledger_entry_id);
    }

    public function test_duplicates_wallet_type_and_ignore(): void
    {
        $service = $this->service();
        $content = "Ngày,Số tiền,Nội dung\n05/10/2026,100,A\n";
        $service->import($this->project, $this->accountant, $this->bank, $this->csv($content), ['date' => 0, 'amount' => 1, 'description' => 2], 'SK-1');
        $this->assertRule(fn () => $service->import($this->project, $this->accountant, $this->bank, $this->csv($content), ['date' => 0, 'amount' => 1, 'description' => 2], 'SK-1b'), 'đã được import');

        $second = $service->import($this->project, $this->accountant, $this->bank, $this->csv($content . "06/10/2026,200,B\n"), ['date' => 0, 'amount' => 1, 'description' => 2], 'SK-2');
        $statuses = Line::query()->where('import_id', $second->id)->orderBy('line_no')->pluck('status')->all();
        $this->assertSame([Line::STATUS_DUPLICATE, Line::STATUS_UNMATCHED], $statuses);

        $this->assertRule(fn () => $service->import($this->project, $this->accountant, $this->cash, $this->csv($content), ['date' => 0, 'amount' => 1], 'SK-C'), 'ví ngân hàng');
        $this->assertRule(fn () => $service->import($this->project, $this->accountant, $this->bank, $this->csv("Ngày,Số tiền\nxx,1\n"), ['date' => 0, 'amount' => 1], 'SK-E'), 'Dòng 2');

        $line = Line::query()->where('import_id', $second->id)->where('status', Line::STATUS_UNMATCHED)->firstOrFail();
        $this->assertRule(fn () => $service->ignore($this->project, $this->accountant, $second, (string) $line->id, ''), 'lý do');
        $service->ignore($this->project, $this->accountant, $second, (string) $line->id, 'Không thuộc dự án');
        $this->assertSame(Line::STATUS_IGNORED, $line->fresh()->status);
    }

    public function test_confirm_rejects_mismatched_pairs(): void
    {
        $doc = $this->funding('700', '2026-10-05', 'F-1');
        $import = $this->service()->import($this->project, $this->accountant, $this->bank, $this->csv("Ngày,Số tiền\n05/10/2026,800\n"), ['date' => 0, 'amount' => 1], 'SK-X');
        $line = Line::query()->where('import_id', $import->id)->firstOrFail();
        $entry = TreasuryLedgerEntry::query()->where('source_financial_document_id', $doc->id)->firstOrFail();

        $this->assertRule(fn () => $this->service()->confirm($this->project, $this->accountant, $import, [(string) $line->id => (string) $entry->id]), 'không khớp');
    }

    public function test_pages_and_permissions(): void
    {
        $this->funding('1500000', '2026-10-04', 'F-A');
        $page = route('operator.treasury.projects.wallets.bank-import', [$this->project->id, $this->bank->id]);
        $this->actingAs($this->accountant)->get($page, $this->headers)->assertOk()->assertSee('treasury-bank-import-form', false);
        $this->actingAs($this->accountant)->post(route('operator.treasury.projects.wallets.bank-import.store', [$this->project->id, $this->bank->id]), [
            'file' => $this->csv("Ngày,Số tiền,Nội dung\n05/10/2026,1.500.000,Nhận tiền A\n"),
            'statement_reference' => 'SK-WEB', 'has_header' => '1', 'map' => ['date' => 0, 'amount' => 1, 'description' => 2],
        ], $this->headers)->assertRedirect();
        $import = \App\Models\Treasury\TreasuryBankStatementImport::query()->where('statement_reference', 'SK-WEB')->firstOrFail();
        $show = route('operator.treasury.projects.bank-imports.show', [$this->project->id, $import->id]);
        $this->actingAs($this->accountant)->get($show, $this->headers)->assertOk()->assertSee('treasury-bank-suggestion', false)->assertSee('treasury-bank-confirm', false);

        $this->actingAs($this->viewer)->get($show, $this->headers)->assertOk()->assertSee('Nhận tiền A')->assertDontSee('treasury-bank-confirm', false);
        $this->actingAs($this->viewer)->get($page, $this->headers)->assertOk()->assertDontSee('treasury-bank-import-form', false);
        $this->actingAs($this->accountant)->get(route('operator.treasury.projects.wallets.bank-import', [$this->project->id, $this->cash->id]), $this->headers)->assertNotFound();
        $this->actingAs($this->accountant)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)->assertOk()->assertSee('treasury-wallet-bank-import-link', false);
    }

    private function service(): TreasuryBankImportService
    {
        return app(TreasuryBankImportService::class);
    }

    private function funding(string $amount, string $date, string $reference): Document
    {
        return app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => $amount, 'transaction_date' => $date, 'reference' => $reference,
        ], true);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sao-ke.csv', $content);
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
