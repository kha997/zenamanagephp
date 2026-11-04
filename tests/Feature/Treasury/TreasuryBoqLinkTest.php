<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryDashboardService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GAP-105 B-1 — an expense names at most one BOQ line of its own project, set while draft; spending per BOQ line with a
 * budget only when the unit price is known; overrun alert; register filter; a used BOQ line cannot be deleted.
 */
class TreasuryBoqLinkTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private TreasuryWallet $bank;

    private TreasuryFinancialParty $supplier;

    private ContractExpense $cost;

    private Boq $boq;

    private BoqLineItem $concrete;

    private BoqLineItem $steel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->supplier = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'supplier', 'name' => 'NCC Bê tông']);
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
        $this->boq = Boq::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'code' => 'BOQ-1']);
        $this->concrete = BoqLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'boq_id' => $this->boq->id, 'code' => 'A.01', 'name' => 'Bê tông', 'quantity' => 10, 'unit_price' => 1000]);
        $this->steel = BoqLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'boq_id' => $this->boq->id, 'code' => 'A.02', 'name' => 'Thép', 'quantity' => 5, 'unit_price' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expense_keeps_its_boq_line_and_rejects_other_project_lines(): void
    {
        $draft = $this->draft('1000', $this->concrete);
        $this->assertSame((string) $this->concrete->id, $draft->fresh()->boq_line_item_id);

        $otherBoq = Boq::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->projectFor($this->tenant)->id]);
        $foreignLine = BoqLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'boq_id' => $otherBoq->id]);

        try {
            $this->draft('1000', $foreignLine);
            $this->fail('Expected TreasuryRuleViolation');
        } catch (TreasuryRuleViolation $e) {
            $this->assertSame('boq_line_item_id', $e->field);
        }
    }

    public function test_boq_line_cannot_change_after_submit(): void
    {
        $expenses = app(TreasuryExpenseService::class);
        $draft = $this->draft('1000', $this->concrete);
        $expenses->submit($this->project, $this->owner, $draft);

        $this->expectException(TreasuryRuleViolation::class);
        $expenses->updateDraft($this->project, $this->owner, $draft->fresh(), $this->payload('1000', $this->steel));
    }

    public function test_spending_per_boq_line_budget_and_overrun(): void
    {
        $this->posted('12000', $this->concrete);
        $this->posted('3000', $this->steel);
        $this->posted('500', null);
        $reversed = $this->posted('4000', $this->concrete);
        app(TreasuryPostingService::class)->reverse($this->project, $this->accountant, $reversed, ['transaction_date' => '2026-10-10', 'description' => 'Nhầm']);

        $report = app(TreasuryReportService::class)->report($this->project, 'expense_by_boq');
        $this->assertSame('Chi theo BOQ', $report['title']);
        $this->assertSame([
            ['BOQ-1', 'A.01', 'Bê tông', '12000.00', '10000.00', '-2000.00'],
            ['BOQ-1', 'A.02', 'Thép', '3000.00', 'Chưa có dữ liệu', ''],
            ['Chưa gắn BOQ', '', '', '500.00', '', ''],
        ], $report['rows']);

        $alerts = collect(app(TreasuryDashboardService::class)->dashboard($this->project)['alerts'])->keyBy('code');
        $this->assertSame(1, $alerts['boq_overrun']['count']);
        $this->assertSame('2000.00', $alerts['boq_overrun']['amount']);

        $filtered = app(TreasuryReportService::class)->registerQuery($this->project, ['boq_line_item_id' => (string) $this->steel->id])->get();
        $this->assertCount(1, $filtered);
        $this->assertSame('3000.00', (string) $filtered->first()->amount);
    }

    public function test_used_boq_line_cannot_be_deleted(): void
    {
        $this->draft('1000', $this->concrete);

        $this->expectException(QueryException::class);
        DB::table('boq_line_items')->where('id', $this->concrete->id)->delete();
    }

    public function test_expense_form_lists_project_boq_lines(): void
    {
        $this->actingAs($this->owner)
            ->get(route('operator.treasury.projects.show', $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee('treasury-expense-boq-line', false)
            ->assertSee('BOQ-1 / A.01 — Bê tông');

        $this->actingAs($this->owner)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->supplier->id,
            'amount' => '700', 'transaction_date' => '2026-10-05', 'boq_line_item_id' => (string) $this->steel->id,
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '700']],
        ], ['X-Tenant-ID' => (string) $this->tenant->id])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame((string) $this->steel->id, Document::query()->where('amount', '700')->value('boq_line_item_id'));
    }

    private function draft(string $amount, ?BoqLineItem $line): Document
    {
        return app(TreasuryExpenseService::class)->createDraft($this->project, $this->owner, $this->payload($amount, $line));
    }

    private function posted(string $amount, ?BoqLineItem $line): Document
    {
        $expenses = app(TreasuryExpenseService::class);
        $draft = $this->draft($amount, $line);
        $expenses->submit($this->project, $this->owner, $draft);

        return $expenses->approve($this->project, $this->accountant, $draft->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $amount, ?BoqLineItem $line): array
    {
        return [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->supplier->id,
            'amount' => $amount, 'transaction_date' => '2026-10-05',
            'boq_line_item_id' => $line === null ? null : (string) $line->id,
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $this->cost->id, 'amount' => $amount]],
        ];
    }
}
