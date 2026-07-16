<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractExpenseTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_expense_uses_tenant_scope_and_belongs_to_contract(): void
    {
        $this->assertContains(TenantScope::class, class_uses_recursive(ContractExpense::class));

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-EXP-01',
            'title' => 'HĐ test chi',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);

        $expense = ContractExpense::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-07-13',
            'amount' => 15000000,
            'category' => 'labor',
            'description' => 'Nhân công tháo dỡ',
            'recorded_by' => (string) $user->id,
        ]);

        $this->assertSame('CTR-EXP-01', $expense->contract->code);
        $this->assertSame(1, $contract->expenses()->count());
        $this->assertSame('Nhân công', $expense->categoryLabel());
    }
}
