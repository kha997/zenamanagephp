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
        
        // Create document manually to avoid factory issues
        $this->document = new Document([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Document',
            'file_path' => '/test/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
        $this->document->save();
    }

    public function test_user_can_view_document_in_same_tenant()
    {
        // Mock hasRole method
        $this->user->shouldReceive('hasRole')->with(['super_admin', 'admin', 'pm', 'designer', 'engineer'])->andReturn(true);
        
        $this->assertTrue($this->policy->view($this->user, $this->document));
    }

    public function test_user_cannot_view_document_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = new Document([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
        $otherDocument->save();
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    public function test_user_can_create_document_with_proper_role()
    {
        // Mock hasRole method
        $this->user->shouldReceive('hasRole')->with(['super_admin', 'admin', 'pm', 'designer'])->andReturn(true);
        
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        // Mock hasRole method
        $this->user->shouldReceive('hasRole')->with(['super_admin', 'admin', 'pm', 'designer'])->andReturn(false);
        
        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_tenant_isolation_works()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherDocument = new Document([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Document',
            'file_path' => '/test/other.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => 'draft'
        ]);
        $otherDocument->save();
        
        // Even with proper role, should be blocked by tenant isolation
        $this->user->shouldReceive('hasRole')->with(['super_admin', 'admin', 'pm', 'designer', 'engineer'])->andReturn(true);
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }
}
