<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\Document;
use App\Models\Tenant;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new DocumentPolicy();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
        
        // Create document
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Document',
            'file_path' => '/test/document.pdf',
            'uploaded_by' => $this->user->id
        ]);
    }

    public function test_user_can_view_document_in_same_tenant()
    {
        $this->user->assignRole('pm');
        
        $this->assertTrue($this->policy->view($this->user, $this->document));
    }

    public function test_user_cannot_view_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document'
        ]);
        
        $this->user->assignRole('pm');
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    public function test_user_can_create_document_with_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Create a new user with project_manager role directly
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pm@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Debug information
        $this->assertTrue($userWithRole->hasRole('project_manager'), 'User should have project_manager role');
        $this->assertNotNull($userWithRole->tenant_id, 'User should have tenant_id');
        $this->assertTrue($userWithRole->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer', 'procurement']), 'User should have any of the allowed roles');
        
        $this->assertTrue($this->policy->create($userWithRole));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        $this->user->assignRole('guest');
        
        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_user_can_update_document_with_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Create a new user with project_manager role directly
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pm@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->update($userWithRole, $this->document));
    }

    public function test_user_cannot_update_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document'
        ]);
        
        $this->user->assignRole('pm');
        
        $this->assertFalse($this->policy->update($this->user, $otherDocument));
    }

    public function test_user_can_delete_document_with_admin_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'system',
                'allow_override' => false,
                'description' => 'Administrator - System management',
            ]
        );
        
        // Create a new user with admin role directly
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->delete($userWithRole, $this->document));
    }

    public function test_user_cannot_delete_document_without_admin_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'engineer'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Engineer - Engineering work',
            ]
        );
        
        // Create a new user with engineer role (not admin)
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'engineer@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create a document uploaded by someone else (not this user)
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'other@example-' . uniqid() . '.com'
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'uploaded_by' => $otherUser->id
        ]);
        
        $this->assertFalse($this->policy->delete($userWithRole, $otherDocument));
    }

    public function test_user_can_download_document_with_proper_role()
    {
        $this->user->assignRole('engineer');
        
        $this->assertTrue($this->policy->download($this->user, $this->document));
    }

    public function test_user_cannot_download_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document'
        ]);
        
        $this->user->assignRole('engineer');
        
        $this->assertFalse($this->policy->download($this->user, $otherDocument));
    }

    public function test_user_can_approve_document_with_management_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Create a new user with project_manager role directly
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pm@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->approve($userWithRole, $this->document));
    }

    public function test_user_cannot_approve_document_without_management_role()
    {
        $this->user->assignRole('engineer');
        
        $this->assertFalse($this->policy->approve($this->user, $this->document));
    }

    public function test_super_admin_can_perform_all_actions()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'scope' => 'system',
                'allow_override' => true,
                'description' => 'Super Administrator - Full system access',
            ]
        );
        
        // Create a new user with super_admin role directly
        $userWithRole = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'superadmin@example-' . uniqid() . '.com'
        ]);
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $userWithRole->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->view($userWithRole, $this->document));
        $this->assertTrue($this->policy->create($userWithRole));
        $this->assertTrue($this->policy->update($userWithRole, $this->document));
        $this->assertTrue($this->policy->delete($userWithRole, $this->document));
        $this->assertTrue($this->policy->download($userWithRole, $this->document));
        $this->assertTrue($this->policy->approve($userWithRole, $this->document));
        $this->assertTrue($this->policy->forceDelete($userWithRole, $this->document));
    }
}
