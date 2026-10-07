<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-066 S3 — expense flow on the project Treasury page.
 */
class TreasuryExpenseWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private User $engineer;
    private TreasuryWallet $cash;
    private TreasuryFinancialParty $payee;
    private ContractExpense $cost;
    private Contract $contract;

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
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id]);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->payee = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'labour', 'name' => 'Tổ đội D']);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ kỹ sư', 'custodian_party_id' => $holder->id]);
        app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->cash->id, 'amount' => '10000000', 'transaction_date' => '2026-10-01',
        ]);
        $this->contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id, 'code' => 'HD-01']);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $this->contract->id, 'expense_date' => '2026-10-01',
            'amount' => '4000000', 'category' => 'labor', 'description' => 'Nhân công tuần 40',
        ]);
    }

    public function test_engineer_submits_owner_approves_and_the_page_shows_paid_and_remaining(): void
    {
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($this->engineer)->get($page, $this->headers)->assertOk()
            ->assertSee('treasury-expense-form', false)
            ->assertSee('Nhân công tuần 40');

        $this->actingAs($this->engineer)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->cash->id, 'destination_party_id' => (string) $this->payee->id,
            'amount' => '3000000', 'transaction_date' => '2026-10-04',
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '3000000']],
            'action' => 'submit',
        ], $this->headers)->assertRedirect($page)->assertSessionHas('success');
        $expense = Document::query()->where('document_type', 'expense')->sole();
        $this->assertSame('submitted', $expense->status);

        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()->assertSee('Duyệt &amp; chi', false);
        $this->actingAs($this->owner)->post(route('operator.treasury.projects.expenses.action', [$this->project->id, $expense->id, 'approve']), [], $this->headers)
            ->assertRedirect($page);

        $this->assertSame('posted_unreconciled', $expense->fresh()?->status);
        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()
            ->assertSee('7.000.000 ₫')
            ->assertSee('1.000.000 ₫')
            ->assertSee('Chi phí');
    }

    public function test_self_approved_expense_gets_a_badge(): void
    {
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($this->owner)->get($page, $this->headers);
        $this->actingAs($this->owner)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->cash->id, 'destination_party_id' => (string) $this->payee->id,
            'amount' => '2000000', 'transaction_date' => '2026-10-04',
            'new_contract_id' => (string) $this->contract->id, 'new_category' => 'misc', 'new_amount' => '2000000', 'new_description' => 'Mua dụng cụ',
            'action' => 'submit',
        ], $this->headers)->assertRedirect($page);
        $expense = Document::query()->where('document_type', 'expense')->sole();
        $this->actingAs($this->owner)->post(route('operator.treasury.projects.expenses.action', [$this->project->id, $expense->id, 'approve']), [], $this->headers);

        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()
            ->assertSee('self-approved-badge', false)
            ->assertSee('Mua dụng cụ');
    }

    public function test_rejection_then_copy_from_the_page(): void
    {
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($this->engineer)->get($page, $this->headers);
        $this->actingAs($this->engineer)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->cash->id, 'destination_party_id' => (string) $this->payee->id,
            'amount' => '1000000', 'transaction_date' => '2026-10-04',
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '1000000']],
            'action' => 'submit',
        ], $this->headers);
        $expense = Document::query()->where('document_type', 'expense')->sole();

        $this->actingAs($this->owner)->post(route('operator.treasury.projects.expenses.action', [$this->project->id, $expense->id, 'reject']), ['note' => 'Thiếu hoá đơn'], $this->headers)
            ->assertRedirect($page);
        $this->actingAs($this->engineer)->post(route('operator.treasury.projects.expenses.action', [$this->project->id, $expense->id, 'copy']), [], $this->headers)
            ->assertRedirect($page);

        $this->assertSame(['draft', 'rejected'], Document::query()->where('document_type', 'expense')->orderBy('status')->pluck('status')->all());
    }

    public function test_engineer_cannot_approve_and_plan_errors_return_to_the_form(): void
    {
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($this->engineer)->get($page, $this->headers);
        $this->actingAs($this->engineer)->from($page)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->cash->id, 'destination_party_id' => (string) $this->payee->id,
            'amount' => '1000000', 'transaction_date' => '2026-10-04',
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '500000']],
            'action' => 'draft',
        ], $this->headers)->assertRedirect($page)->assertSessionHasErrors('allocations');

        $this->actingAs($this->engineer)->post(route('operator.treasury.projects.expenses.store', $this->project->id), [
            'source_wallet_id' => (string) $this->cash->id, 'destination_party_id' => (string) $this->payee->id,
            'amount' => '500000', 'transaction_date' => '2026-10-04',
            'lines' => [['cost' => 'contract_expense:' . $this->cost->id, 'amount' => '500000']],
            'action' => 'submit',
        ], $this->headers);
        $expense = Document::query()->where('document_type', 'expense')->sole();
        $this->actingAs($this->engineer)->post(route('operator.treasury.projects.expenses.action', [$this->project->id, $expense->id, 'approve']), [], $this->headers)
            ->assertForbidden();
    }
}
