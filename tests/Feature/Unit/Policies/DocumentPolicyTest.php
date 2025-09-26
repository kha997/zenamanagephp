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
            'file_path' => '/test/document.pdf'
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
        $this->user->assignRole('pm');
        
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        $this->user->assignRole('guest');
        
        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_user_can_update_document_with_proper_role()
    {
        $this->user->assignRole('pm');
        
        $this->assertTrue($this->policy->update($this->user, $this->document));
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
        $this->user->assignRole('admin');
        
        $this->assertTrue($this->policy->delete($this->user, $this->document));
    }

    public function test_user_cannot_delete_document_without_admin_role()
    {
        $this->user->assignRole('pm');
        
        $this->assertFalse($this->policy->delete($this->user, $this->document));
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
        $this->user->assignRole('pm');
        
        $this->assertTrue($this->policy->approve($this->user, $this->document));
    }

    public function test_user_cannot_approve_document_without_management_role()
    {
        $this->user->assignRole('engineer');
        
        $this->assertFalse($this->policy->approve($this->user, $this->document));
    }

    public function test_super_admin_can_perform_all_actions()
    {
        $this->user->assignRole('super_admin');
        
        $this->assertTrue($this->policy->view($this->user, $this->document));
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->update($this->user, $this->document));
        $this->assertTrue($this->policy->delete($this->user, $this->document));
        $this->assertTrue($this->policy->download($this->user, $this->document));
        $this->assertTrue($this->policy->approve($this->user, $this->document));
        $this->assertTrue($this->policy->forceDelete($this->user, $this->document));
    }
}
