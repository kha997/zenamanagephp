<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $policy;

    protected $tenant;

    protected $user;

    protected $document;

    /** OWN-2026-024 H4: a role authorizes only through its canonical grants; attach exactly the code the ability needs. */
    private function grant(\App\Models\Role $role, string $code): void
    {
        [$module, $action] = explode('.', $code, 2);
        $permission = \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => $module, 'action' => $action, 'description' => $code]);
        $role->permissions()->syncWithoutDetaching($permission->id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DocumentPolicy();

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant',
        ]);

        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com',
        ]);

        // Create document
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Document',
            'file_path' => '/test/document.pdf',
        ]);
    }

    public function test_user_can_view_document_in_same_tenant()
    {
        $this->grant($this->user->assignRole('pm'), 'document.view'); // OWN-2026-024 H4: the role carries the canonical grant

        $this->assertTrue($this->policy->view($this->user, $this->document));
    }

    public function test_user_cannot_view_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant',
        ]);

        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document',
        ]);

        $this->user->assignRole('pm');

        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    public function test_user_can_create_document_with_proper_role()
    {
        $this->grant($this->user->assignRole('pm'), 'document.create'); // OWN-2026-024 H4: the role carries the canonical grant

        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        $this->user->assignRole('guest');

        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_user_can_update_document_with_proper_role()
    {
        $this->grant($this->user->assignRole('pm'), 'document.update'); // OWN-2026-024 H4: the role carries the canonical grant

        $this->assertTrue($this->policy->update($this->user, $this->document));
    }

    public function test_user_cannot_update_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant',
        ]);

        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document',
        ]);

        $this->user->assignRole('pm');

        $this->assertFalse($this->policy->update($this->user, $otherDocument));
    }

    /**
     * OWN-2026-024 (O-17h) H4 — SUPERSEDED (Owner Gate 2 Option B / H4: no role-name or users.role authorization).
     * Original preserved in docs/audits/UAT-RECOVERY-01/105-o17h-h4-review.md §4.
     * Original: assignRole('admin') then assertTrue(delete). Deletion now needs document.delete AND canonical Owner authority.
     */
    public function test_user_can_delete_document_with_admin_role()
    {
        $this->user->assignRole('admin');
        $this->assertFalse($this->policy->delete($this->user, $this->document), 'the role NAME admin no longer deletes');

        $owner = $this->user->assignRole('super_admin');
        $this->grant($owner, 'document.delete');
        $this->assertFalse($this->policy->delete($this->user->fresh(), $this->document), 'document.delete without Owner authority');
        $this->grant($owner, 'admin.company.manage');
        $this->assertTrue($this->policy->delete($this->user->fresh(), $this->document), 'canonical Owner holding document.delete');
    }

    public function test_user_cannot_delete_document_without_admin_role()
    {
        $this->user->assignRole('pm');

        $this->assertFalse($this->policy->delete($this->user, $this->document));
    }

    public function test_user_can_download_document_with_proper_role()
    {
        $this->grant($this->user->assignRole('engineer'), 'document.view'); // OWN-2026-024 H4: the role carries the canonical grant

        $this->assertTrue($this->policy->download($this->user, $this->document));
    }

    public function test_user_cannot_download_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant',
        ]);

        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document',
        ]);

        $this->user->assignRole('engineer');

        $this->assertFalse($this->policy->download($this->user, $otherDocument));
    }

    public function test_user_can_approve_document_with_management_role()
    {
        $role = $this->user->assignRole('pm');
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'document.approve'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Document approve']
        );
        $role->permissions()->syncWithoutDetaching($permission->id);

        $this->assertTrue($this->policy->approve($this->user, $this->document));
    }

    public function test_user_cannot_approve_document_without_management_role()
    {
        $this->user->assignRole('engineer');

        $this->assertFalse($this->policy->approve($this->user, $this->document));
    }

    /**
     * OWN-2026-024 (O-17h) H4 — SUPERSEDED (Owner Gate 2 Option B / H4: no role-name or users.role authorization).
     * Original preserved in docs/audits/UAT-RECOVERY-01/105-o17h-h4-review.md §4.
     * Original: a super_admin role holding ONLY document.approve asserted true for view, create, update, delete, download,
     * approve and forceDelete — the role name stood in for the missing grants. Now: the bare role name is denied every ability
     * it holds no grant for, and the canonically provisioned Owner performs every action.
     */
    public function test_super_admin_can_perform_all_actions()
    {
        $role = $this->user->assignRole('super_admin');
        $this->grant($role, 'document.approve');
        foreach (['view', 'update', 'delete', 'download', 'forceDelete'] as $ability) {
            $this->assertFalse($this->policy->{$ability}($this->user, $this->document), "role name alone: {$ability}");
        }
        $this->assertFalse($this->policy->create($this->user), 'role name alone: create');
        $this->assertTrue($this->policy->approve($this->user, $this->document), 'the one held grant still works');

        foreach (['document.view', 'document.create', 'document.update', 'document.delete', 'admin.company.manage'] as $code) {
            $this->grant($role, $code);
        }
        $owner = $this->user->fresh();
        $this->assertTrue($this->policy->view($owner, $this->document));
        $this->assertTrue($this->policy->create($owner));
        $this->assertTrue($this->policy->update($owner, $this->document));
        $this->assertTrue($this->policy->delete($owner, $this->document));
        $this->assertTrue($this->policy->download($owner, $this->document));
        $this->assertTrue($this->policy->approve($owner, $this->document));
        $this->assertTrue($this->policy->forceDelete($owner, $this->document));
    }
}
