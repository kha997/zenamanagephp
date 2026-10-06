<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Tenant;
use App\Models\UserRoleProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * GAP-063 S1 access rule: tenant + code + (all_projects or membership).
 */
class TreasuryPolicyTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
    }

    public function test_owner_and_accountant_reach_every_project_of_their_tenant_without_membership(): void
    {
        $project = $this->projectFor($this->tenant);

        foreach (['Admin', 'System Admin', 'Finance'] as $roleName) {
            $user = $this->userWithRole($this->tenant, $roleName);
            $this->assertTrue(Gate::forUser($user)->allows('treasury.view-project', $project), $roleName);
            $this->assertTrue(Gate::forUser($user)->allows('treasury.manage-wallets', $project), $roleName);
            $this->assertTrue(Gate::forUser($user)->allows('treasury.manage-parties'), $roleName);
        }
    }

    public function test_engineer_needs_membership_and_cannot_manage_wallets_or_parties(): void
    {
        $project = $this->projectFor($this->tenant);
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');

        $this->assertFalse(Gate::forUser($engineer)->allows('treasury.view-project', $project));

        $this->addMember($project, $engineer);

        $this->assertTrue(Gate::forUser($engineer)->allows('treasury.view-project', $project));
        $this->assertFalse(Gate::forUser($engineer)->allows('treasury.manage-wallets', $project));
        $this->assertFalse(Gate::forUser($engineer)->allows('treasury.manage-parties'));
        $this->assertTrue(Gate::forUser($engineer)->allows('treasury.view-parties'));
    }

    public function test_removed_membership_no_longer_grants_access(): void
    {
        $project = $this->projectFor($this->tenant);
        $pm = $this->userWithRole($this->tenant, 'Project Manager');
        $this->addMember($project, $pm);

        UserRoleProject::query()->where('user_id', $pm->id)->update(['deleted_at' => now()]);

        $this->assertFalse(Gate::forUser($pm)->allows('treasury.view-project', $project));
    }

    public function test_viewer_member_can_view_only(): void
    {
        $project = $this->projectFor($this->tenant);
        $designer = $this->userWithRole($this->tenant, 'Designer');
        $this->addMember($project, $designer);

        $this->assertTrue(Gate::forUser($designer)->allows('treasury.view-project', $project));
        $this->assertFalse(Gate::forUser($designer)->allows('treasury.manage-wallets', $project));
    }

    public function test_client_member_has_no_treasury_access(): void
    {
        $project = $this->projectFor($this->tenant);
        $client = $this->userWithRole($this->tenant, 'Client');
        $this->addMember($project, $client);

        $this->assertFalse(Gate::forUser($client)->allows('treasury.view-project', $project));
        $this->assertFalse(Gate::forUser($client)->allows('treasury.view-parties'));
    }

    public function test_other_tenant_is_denied_even_with_all_projects(): void
    {
        $project = $this->projectFor($this->otherTenant);
        $accountant = $this->userWithRole($this->tenant, 'Finance');

        $this->assertFalse(Gate::forUser($accountant)->allows('treasury.view-project', $project));
        $this->assertFalse(Gate::forUser($accountant)->allows('treasury.manage-wallets', $project));
    }
}
