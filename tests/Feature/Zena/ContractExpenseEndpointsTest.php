<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractExpenseEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['contract.view', 'contract.expense.view', 'contract.expense.create', 'contract.expense.delete']
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-EXP-EP',
            'title' => 'HĐ endpoint chi',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $this->user->id,
        ]);

        $this->get('/login');
    }

    public function test_store_expense_happy_path(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->post(route('operator.contracts.expenses.store', $this->contract->id), [
            'expense_date' => '2026-07-13',
            'amount' => 15000000,
            'category' => 'labor',
            'description' => 'Nhân công tháo dỡ',
        ], $headers)->assertRedirect();

        $this->assertDatabaseHas('contract_expenses', [
            'contract_id' => (string) $this->contract->id,
            'category' => 'labor',
            'description' => 'Nhân công tháo dỡ',
        ]);
    }

    public function test_store_validates_amount_and_category(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->post(route('operator.contracts.expenses.store', $this->contract->id), [
            'expense_date' => '2026-07-13',
            'amount' => -5,
            'description' => 'x',
        ], $headers)->assertSessionHasErrors(['amount', 'category']);

        $this->actingAs($this->user)->post(route('operator.contracts.expenses.store', $this->contract->id), [
            'expense_date' => '2026-07-13',
            'amount' => 100,
            'category' => 'materials',
            'description' => 'x',
        ], $headers)->assertSessionHasErrors('category');

        $this->assertSame(0, ContractExpense::query()->count());
    }

    public function test_cross_tenant_store_is_404(): void
    {
        $other = Tenant::factory()->create();
        $intruder = $this->createTenantUser($other, [], ['admin'], ['contract.expense.create']);

        $this->actingAs($intruder)->post(route('operator.contracts.expenses.store', $this->contract->id), [
            'expense_date' => '2026-07-13',
            'amount' => 100,
            'category' => 'misc',
            'description' => 'x',
        ], ['X-Tenant-ID' => (string) $other->id])->assertNotFound();

        $this->assertSame(0, ContractExpense::query()->count());
    }

    public function test_delete_clears_row_and_wrong_contract_is_404(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $expense = ContractExpense::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'expense_date' => '2026-07-13',
            'amount' => 100,
            'category' => 'misc',
            'description' => 'x',
        ]);

        $otherContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-EXP-EP2',
            'title' => 'HĐ khác',
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('operator.contracts.expenses.delete', [$otherContract->id, $expense->id]), [], $headers)
            ->assertNotFound();
        $this->assertSame(1, ContractExpense::query()->count());

        $this->actingAs($this->user)
            ->post(route('operator.contracts.expenses.delete', [$this->contract->id, $expense->id]), [], $headers)
            ->assertRedirect();
        $this->assertSame(0, ContractExpense::query()->count());
    }
}
