<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-096 S5 — advances on the project Treasury page and the advance page.
 */
class TreasuryAdvanceWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private TreasuryWallet $bank;

    private TreasuryWallet $engineerCash;

    private TreasuryFinancialParty $holder;

    private ContractExpense $cost;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id, 'name' => 'Kỹ sư Z']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->engineerCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Z cash', 'custodian_party_id' => $this->holder->id]);
        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '10000000', 'transaction_date' => '2026-10-01',
        ]);
        $posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->engineerCash->id,
            'amount' => '5000000', 'transaction_date' => '2026-10-02',
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '3000000', 'category' => 'labor', 'description' => 'Nhân công móng',
        ]);
    }

    public function test_engineer_requests_accountant_pays_and_the_advance_is_settled_from_the_pages(): void
    {
        $projectPage = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->engineer)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('Tạm ứng')
            ->assertSee('treasury-advance-form', false)
            ->assertSee('Không có tạm ứng nào còn nợ');
        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.advances.store'), [
            'source_wallet_id' => (string) $this->engineerCash->id, 'destination_party_id' => (string) $this->holder->id,
            'amount' => '2000000', 'transaction_date' => '2026-10-03', 'action' => 'submit',
        ], $this->headers)->assertRedirect($projectPage)->assertSessionHas('success');
        $document = Document::query()->where('document_type', Document::TYPE_ADVANCE)->firstOrFail();
        $this->assertSame(Document::STATUS_SUBMITTED, $document->status);

        $this->actingAs($this->engineer)->get($projectPage, $this->headers)->assertOk()->assertDontSee('treasury-advance-approve-form', false);
        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()->assertSee('treasury-advance-approve-form', false);
        $advancePage = $this->url('operator.treasury.projects.advances.show', ['treasuryAdvance' => (string) $document->id]);
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.advances.action', ['treasuryAdvance' => (string) $document->id, 'action' => 'approve']), [], $this->headers)
            ->assertRedirect($advancePage)->assertSessionHas('success');

        $this->actingAs($this->engineer)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('treasury-advance-ageing-row', false)
            ->assertSee('Kỹ sư Z')
            ->assertSee('Dưới 30 ngày', false);
        $this->actingAs($this->engineer)->get($advancePage, $this->headers)->assertOk()
            ->assertSee('treasury-settlement-request-form', false)
            ->assertDontSee('treasury-advance-return-form', false);

        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.advances.settlement-request.store', ['treasuryAdvance' => (string) $document->id]), [
            'amount' => '1500000', 'note' => 'Hoá đơn nhân công',
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '1500000']],
        ], $this->headers)->assertRedirect($advancePage)->assertSessionHas('success');
        $this->actingAs($this->engineer)->get($advancePage, $this->headers)->assertOk()
            ->assertSee('treasury-advance-open-request', false)
            ->assertSee('Nhân công móng')
            ->assertDontSee('treasury-settlement-approve-form', false);

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.advances.settlement-request.action', ['treasuryAdvance' => (string) $document->id, 'action' => 'approve']), [], $this->headers)
            ->assertRedirect($advancePage)->assertSessionHas('success');
        $this->actingAs($this->accountant)->get($advancePage, $this->headers)->assertOk()
            ->assertSee('treasury-advance-return-form', false)
            ->assertSee('treasury-settlement-remove-form', false)
            ->assertSee('treasury-advance-reverse-hint', false)
            ->assertSee('500.000 ₫');

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.advances.returns.store', ['treasuryAdvance' => (string) $document->id]), [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '500000', 'transaction_date' => '2026-10-06',
        ], $this->headers)->assertRedirect($advancePage)->assertSessionHas('success');
        $this->actingAs($this->engineer)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('Không có tạm ứng nào còn nợ')
            ->assertSee('Nộp lại tạm ứng');

        $settlementId = (string) Settlement::query()->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)->value('id');
        $remove = $this->url('operator.treasury.projects.advance-settlements.remove', ['treasuryAdvanceSettlement' => $settlementId]);
        $this->actingAs($this->engineer)->post($remove, ['reason' => 'Nhầm'], $this->headers)->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->accountant)->post($remove, ['reason' => 'Gắn nhầm'], $this->headers)->assertRedirect($advancePage)->assertSessionHas('success');
        $this->assertSame(2, Settlement::query()->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)->count());
    }

    public function test_reversing_an_advance_with_settlements_shows_the_rule(): void
    {
        $projectPage = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->owner)->get($projectPage, $this->headers)->assertOk();
        $this->actingAs($this->owner)->post($this->url('operator.treasury.projects.advances.store'), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id,
            'amount' => '1000000', 'transaction_date' => '2026-10-03', 'action' => 'submit',
        ], $this->headers)->assertRedirect($projectPage);
        $document = Document::query()->where('document_type', Document::TYPE_ADVANCE)->firstOrFail();
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.advances.action', ['treasuryAdvance' => (string) $document->id, 'action' => 'approve']), [], $this->headers);
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.advances.returns.store', ['treasuryAdvance' => (string) $document->id]), [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000', 'transaction_date' => '2026-10-06',
        ], $this->headers);

        $this->actingAs($this->accountant)->from($projectPage)->post($this->url('operator.treasury.projects.documents.reverse', ['treasuryDocument' => (string) $document->id]), [
            'transaction_date' => '2026-10-07', 'description' => 'Chi nhầm',
        ], $this->headers)->assertRedirect($projectPage)->assertSessionHasErrors();
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $document->fresh()?->status);
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $name, array $params = []): string
    {
        return route($name, ['project' => (string) $this->project->id] + $params, false);
    }
}
