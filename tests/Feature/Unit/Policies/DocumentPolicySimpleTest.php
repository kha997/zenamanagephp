<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\Document;
use App\Models\Tenant;
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
        
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Test Document',
            'file_path' => '/test/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
    }

    public function test_user_can_view_document_in_same_tenant()
    {
        $this->user->role = 'admin';
        
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
            'uploaded_by' => $this->user->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    public function test_user_can_create_document_with_proper_role()
    {
        $this->user->role = 'admin';
        
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
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
        
        // Even with proper role, should be blocked by tenant isolation
        $this->user->role = 'admin';
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }
}
