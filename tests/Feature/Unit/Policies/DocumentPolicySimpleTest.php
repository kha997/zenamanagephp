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
            'project_id' => null,
            'name' => 'Test Document',
            'original_name' => 'test-document.pdf',
            'file_path' => '/test/document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'test-hash-123',
            'category' => 'general',
            'description' => 'Test document description',
            'metadata' => json_encode(['test' => 'data']),
            'status' => 'draft',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'uploaded_by' => $this->user->id,
            'created_by' => null,
            'updated_by' => null
        ]);
        $this->document->save();
    }

    public function test_user_can_view_document_in_same_tenant()
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
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
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
            'project_id' => null,
            'name' => 'Other Document',
            'original_name' => 'other-document.pdf',
            'file_path' => '/test/other.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'other-hash-456',
            'category' => 'general',
            'description' => 'Other document description',
            'metadata' => json_encode(['other' => 'data']),
            'status' => 'draft',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'uploaded_by' => $this->user->id,
            'created_by' => null,
            'updated_by' => null
        ]);
        $otherDocument->save();
        
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
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_document_without_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'guest'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Guest - Limited access',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
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
            'project_id' => null,
            'name' => 'Other Document',
            'original_name' => 'other-document.pdf',
            'file_path' => '/test/other.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'other-hash-789',
            'category' => 'general',
            'description' => 'Other document description',
            'metadata' => json_encode(['other' => 'data']),
            'status' => 'draft',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'uploaded_by' => $this->user->id,
            'created_by' => null,
            'updated_by' => null
        ]);
        $otherDocument->save();
        
        // Even with proper role, should be blocked by tenant isolation
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }
}
