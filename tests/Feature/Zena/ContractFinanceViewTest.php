<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractFinanceViewTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
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
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-FIN-01',
            'title' => 'HĐ tài chính',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 1000000000,
            'currency' => 'VND',
            'created_by' => (string) $this->user->id,
        ]);

        foreach ([
            ['name' => 'Đợt 1', 'amount' => 300000000, 'status' => ContractPayment::STATUS_PAID, 'due_date' => now()->subMonth(), 'paid_at' => now()->subMonth()],
            ['name' => 'Đợt 2', 'amount' => 100000000, 'status' => ContractPayment::STATUS_PLANNED, 'due_date' => now()->subWeek(), 'paid_at' => null],
            ['name' => 'Đợt 3', 'amount' => 200000000, 'status' => ContractPayment::STATUS_PLANNED, 'due_date' => now()->addMonth(), 'paid_at' => null],
        ] as $row) {
            ContractPayment::query()->create($row + [
                'tenant_id' => (string) $this->tenant->id,
                'contract_id' => (string) $this->contract->id,
            ]);
        }

        foreach ([
            ['category' => 'labor', 'amount' => 50000000, 'description' => 'Nhân công phần thô'],
            ['category' => 'misc', 'amount' => 10000000, 'description' => 'Chi khác'],
        ] as $row) {
            ContractExpense::query()->create($row + [
                'tenant_id' => (string) $this->tenant->id,
                'contract_id' => (string) $this->contract->id,
                'expense_date' => '2026-07-13',
                'recorded_by' => (string) $this->user->id,
            ]);
        }
    }

    public function test_finance_block_shows_rollups_expenses_and_balance(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.show', $this->contract->id),
            ['X-Tenant-ID' => (string) $this->tenant->id]
        );

        $response->assertOk()
            ->assertSee('Tài chính hợp đồng')
            // Rollups: tổng 1.000.000.000, đã thu 300.000.000, còn phải thu 700.000.000
            ->assertSee('1,000,000,000')
            ->assertSee('300,000,000')
            ->assertSee('700,000,000')
            // 1 đợt quá hạn (Đợt 2)
            ->assertSee('1 đợt')
            // Chi ghi tay theo nhóm + tổng chi tay 60.000.000
            ->assertSee('Nhân công phần thô')
            ->assertSee('60,000,000')
            // Số dư = 300tr thu − 60tr chi (không có chi vật tư tự động trong fixture)
            ->assertSee('Đã thu − đã chi')
            ->assertSee('240,000,000');
    }
}
