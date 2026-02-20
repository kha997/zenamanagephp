<?php declare(strict_types=1);

namespace Tests\Feature\Unit\Policies;

use App\Models\Contract;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ContractPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ContractPolicy $policy;
    private Tenant $tenant;
    private User $user;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ContractPolicy();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_allows_same_tenant_with_canonical_permissions(): void
    {
        $this->grantPermission($this->user, 'contract.view');
        $this->grantPermission($this->user, 'contract.create');
        $this->grantPermission($this->user, 'contract.update');
        $this->grantPermission($this->user, 'contract.delete');

        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->view($this->user, $this->contract));
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->update($this->user, $this->contract));
        $this->assertTrue($this->policy->delete($this->user, $this->contract));
    }

    public function test_denies_without_canonical_permissions(): void
    {
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->view($this->user, $this->contract));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->contract));
        $this->assertFalse($this->policy->delete($this->user, $this->contract));
    }

    public function test_denies_cross_tenant_even_with_permissions(): void
    {
        $this->grantPermission($this->user, 'contract.view');
        $this->grantPermission($this->user, 'contract.update');
        $this->grantPermission($this->user, 'contract.delete');

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

        $this->assertFalse($this->policy->view($this->user, $otherContract));
        $this->assertFalse($this->policy->update($this->user, $otherContract));
        $this->assertFalse($this->policy->delete($this->user, $otherContract));
    }

    public function test_legacy_non_canonical_permission_does_not_authorize(): void
    {
        $this->grantPermission($this->user, 'contracts.view');
        $this->grantPermission($this->user, 'contracts.update');

        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->view($this->user, $this->contract));
        $this->assertFalse($this->policy->update($this->user, $this->contract));
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
            ['name' => 'policy-contract-role-' . str_replace('.', '-', $code)],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);
    }
}
