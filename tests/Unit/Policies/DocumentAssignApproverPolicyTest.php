<?php

declare(strict_types=1);

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

    /**
     * OWN-2026-024 (O-17h) H4 — SUPERSEDED (Owner Gate 2 Option B / H4 requirement 8: no fallback to legacy users.role). Original preserved in docs/audits/UAT-RECOVERY-01/105-o17h-h4-review.md §4.
     * Original: a user holding a role NAMED 'admin' (no grants) asserted true. GAP-033's "tenant Admin" is now the canonical
     * Owner authority (Owner role + revocable admin.company.manage); the bare role name 'admin' is denied.
     */
    public function test_admin_role_may_assign_approver_even_if_not_the_projects_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->roles()->attach(\App\Models\Role::factory()->create(['name' => 'admin']));
        $otherPm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $otherPm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertFalse($this->policy->assignApprover($admin, $document), 'the role NAME admin is not Owner authority');

        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $ownerRole = \App\Models\Role::query()->firstOrCreate(['name' => 'super_admin'], ['scope' => 'system', 'is_active' => true]);
        $authority = \App\Models\Permission::firstOrCreate(['code' => 'admin.company.manage'], ['name' => 'admin.company.manage', 'module' => 'admin', 'action' => 'company.manage', 'description' => 'test']);
        $ownerRole->permissions()->syncWithoutDetaching([$authority->id]);
        $owner->roles()->attach($ownerRole);
        self::assertTrue($this->policy->assignApprover($owner->fresh(), $document), 'canonical Owner authority, not the project PM');
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
