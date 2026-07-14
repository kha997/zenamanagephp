<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractFinanceSettingsTest extends TestCase
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
            [
                'contract.view', 'contract.update',
                'payment_certificate.view', 'payment_certificate.create', 'payment_certificate.approve',
            ]
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-FIN-01',
            'title' => 'HĐ thử finance settings',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 1000000000,
            'created_by' => (string) $this->user->id,
        ]);

        $this->get('/login');
    }

    private function headers(): array
    {
        return ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    public function test_save_finance_settings_creates_advance_payment(): void
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 200000000,
            'advance_recovery_percent' => 20,
        ], $h)->assertRedirect();

        $this->contract->refresh();
        $this->assertSame(5.0, $this->contract->retention_percent);
        $this->assertSame(200000000.0, $this->contract->advance_amount);
        $this->assertSame(20.0, $this->contract->advance_recovery_percent);

        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Tạm ứng theo hợp đồng',
            'amount' => 200000000,
            'status' => ContractPayment::STATUS_PLANNED,
        ]);
    }

    public function test_update_advance_when_planned_updates_amount(): void
    {
        $h = $this->headers();

        // First save — creates advance payment 200tr
        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 200000000,
            'advance_recovery_percent' => 20,
        ], $h);

        // Second save — updates to 250tr while still planned
        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 250000000,
            'advance_recovery_percent' => 20,
        ], $h);

        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Tạm ứng theo hợp đồng',
            'amount' => 250000000,
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        // Only one advance payment record
        $count = ContractPayment::query()
            ->where('contract_id', $this->contract->id)
            ->where('name', 'Tạm ứng theo hợp đồng')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_update_advance_when_paid_does_not_change_payment(): void
    {
        $h = $this->headers();

        // Create advance payment 200tr
        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 200000000,
            'advance_recovery_percent' => 20,
        ], $h);

        // Force payment to paid
        ContractPayment::query()
            ->where('contract_id', $this->contract->id)
            ->where('name', 'Tạm ứng theo hợp đồng')
            ->update(['status' => ContractPayment::STATUS_PAID]);

        // Update settings with advance 300tr — payment should NOT change
        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 300000000,
            'advance_recovery_percent' => 20,
        ], $h);

        // Payment keeps original 200tr
        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Tạm ứng theo hợp đồng',
            'amount' => 200000000,
            'status' => ContractPayment::STATUS_PAID,
        ]);

        // But settings are saved
        $this->contract->refresh();
        $this->assertSame(300000000.0, $this->contract->advance_amount);
    }

    public function test_retention_percent_over_100_fails_validation(): void
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 150,
            'advance_amount' => 0,
            'advance_recovery_percent' => 0,
        ], $h)->assertSessionHasErrors('retention_percent');
    }

    public function test_user_without_permission_cannot_update_finance_settings(): void
    {
        $h = $this->headers();

        // team_member role — NOT admin (admin bypasses RBAC)
        $noPerm = $this->createTenantUser($this->tenant, [], ['team_member'], ['contract.view']);
        $this->actingAs($noPerm)->post(route('operator.contracts.finance-settings.update', $this->contract->id), [
            'retention_percent' => 5,
            'advance_amount' => 100000000,
            'advance_recovery_percent' => 20,
        ], $h)->assertForbidden();
    }
}
