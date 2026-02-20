<?php declare(strict_types=1);

namespace Tests\Feature\Unit\Policies;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ContractPaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ContractPaymentPolicy $policy;
    private Tenant $tenant;
    private User $user;
    private ContractPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ContractPaymentPolicy();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'created_by' => $this->user->id,
        ]);

        $this->payment = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
        ]);
    }

    public function test_allows_same_tenant_with_canonical_permissions(): void
    {
        $this->grantPermission($this->user, 'contract.payment.view');
        $this->grantPermission($this->user, 'contract.payment.create');
        $this->grantPermission($this->user, 'contract.payment.update');
        $this->grantPermission($this->user, 'contract.payment.delete');

        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->view($this->user, $this->payment));
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->update($this->user, $this->payment));
        $this->assertTrue($this->policy->delete($this->user, $this->payment));
    }

    public function test_denies_without_canonical_permissions(): void
    {
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->view($this->user, $this->payment));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->payment));
        $this->assertFalse($this->policy->delete($this->user, $this->payment));
    }

    public function test_denies_cross_tenant_even_with_permissions(): void
    {
        $this->grantPermission($this->user, 'contract.payment.view');
        $this->grantPermission($this->user, 'contract.payment.update');
        $this->grantPermission($this->user, 'contract.payment.delete');

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'pm_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);
        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'created_by' => $otherUser->id,
        ]);
        $otherPayment = ContractPayment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contract_id' => $otherContract->id,
        ]);

        $this->assertFalse($this->policy->view($this->user, $otherPayment));
        $this->assertFalse($this->policy->update($this->user, $otherPayment));
        $this->assertFalse($this->policy->delete($this->user, $otherPayment));
    }

    public function test_legacy_non_canonical_permission_does_not_authorize(): void
    {
        $this->grantPermission($this->user, 'contract.payments.view');
        $this->grantPermission($this->user, 'contract.payments.update');

        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->view($this->user, $this->payment));
        $this->assertFalse($this->policy->update($this->user, $this->payment));
    }

    private function grantPermission(User $user, string $code): void
    {
        [$module, $action] = explode('.', $code, 2);

        $permission = Permission::firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'module' => $module,
                'action' => $action,
                'description' => 'Policy test permission ' . $code,
            ]
        );

        if ($permission->name !== $code) {
            $permission->forceFill(['name' => $code])->save();
        }

        $role = Role::firstOrCreate(
            ['name' => 'policy-contract-payment-role-' . str_replace('.', '-', $code)],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);
    }
}
