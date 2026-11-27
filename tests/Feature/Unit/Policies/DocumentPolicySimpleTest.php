<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPolicySimpleTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;

    protected $tenant;

    protected $user;

    protected $document;

    /** OWN-2026-024 H4: a canonical grant through a test-owned role (user_roles -> role_permissions). */
    private function grantCodes(User $user, array $codes): void
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'o17h-h4-document-fixture'], ['scope' => 'custom', 'is_active' => true]);
        foreach ($codes as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => $module, 'action' => $action, 'description' => $code]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $user->roles()->syncWithoutDetaching($role->id);
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

        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Test Document',
            'file_path' => '/test/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft',
        ]);
    }

    /** OWN-2026-024 (O-17h) H4 — SUPERSEDED (Owner Gate 2 Option B / H4 requirement 8: no fallback to legacy users.role). Original preserved in docs/audits/UAT-RECOVERY-01/105-o17h-h4-review.md §4. Original: users.role = 'admin' then assertTrue(view). Now: the column grants nothing; a canonical grant does. */
    public function test_user_can_view_document_in_same_tenant()
    {
        $this->user->role = 'admin';
        $this->assertFalse($this->policy->view($this->user, $this->document), 'users.role alone');
        $this->grantCodes($this->user, ['document.view']);
        $this->user = $this->user->fresh();

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
            'uploaded_by' => $this->user->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft',
        ]);

        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    /** OWN-2026-024 (O-17h) H4 — SUPERSEDED (Owner Gate 2 Option B / H4 requirement 8: no fallback to legacy users.role). Original preserved in docs/audits/UAT-RECOVERY-01/105-o17h-h4-review.md §4. Original: users.role = 'admin' then assertTrue(create). */
    public function test_user_can_create_document_with_proper_role()
    {
        $this->user->role = 'admin';
        $this->assertFalse($this->policy->create($this->user), 'users.role alone');
        $this->grantCodes($this->user, ['document.create']);
        $this->user = $this->user->fresh();

        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        $this->user->role = 'viewer';

        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_tenant_isolation_works()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant',
        ]);

        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft',
        ]);

        // Even with proper role, should be blocked by tenant isolation
        $this->user->role = 'admin';

        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }
}
