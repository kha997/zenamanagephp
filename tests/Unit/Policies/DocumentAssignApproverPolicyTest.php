<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAssignApproverPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy();
    }

    public function test_projects_own_manager_may_assign_approver(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertTrue($this->policy->assignApprover($pm, $document));
    }

    public function test_admin_role_may_assign_approver_even_if_not_the_projects_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->roles()->attach(\App\Models\Role::factory()->create(['name' => 'admin']));
        $otherPm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $otherPm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertTrue($this->policy->assignApprover($admin, $document));
    }

    public function test_designer_role_with_document_update_permission_may_not_assign_approver(): void
    {
        $tenant = Tenant::factory()->create();
        $designer = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherPm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $otherPm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertFalse($this->policy->assignApprover($designer, $document));
    }

    public function test_cross_tenant_user_may_not_assign_approver_even_as_admin(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenantB->id]);
        $admin->roles()->attach(\App\Models\Role::factory()->create(['name' => 'admin']));
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $document = Document::factory()->create(['tenant_id' => $tenantA->id, 'project_id' => $project->id]);

        self::assertFalse($this->policy->assignApprover($admin, $document));
    }
}
