<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Documents API permission enforcement
 * 
 * Tests that documents endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * Round 30: RBAC Gap Sweep & Missing Modules
 * 
 * @group tenant-documents
 * @group tenant-permissions
 */
class TenantDocumentsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(44444);
        $this->setDomainName('tenant-documents-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B for isolation tests
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create a document in tenant A
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'original_name' => 'test-document.pdf',
            'file_path' => 'documents/test-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => 'approved',
        ]);
    }

    /**
     * Test that GET /api/v1/app/documents requires tenant.view_documents permission
     */
    public function test_get_documents_requires_view_permission(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/documents');
            
            $response->assertStatus(200, "Role {$role} should be able to GET documents (has tenant.view_documents)");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/documents/{id} requires tenant.view_documents permission
     */
    public function test_get_document_requires_view_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/documents/{$this->document->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/documents requires tenant.manage_documents permission
     */
    public function test_create_document_requires_manage_permission(): void
    {
        $roles = ['owner', 'admin'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            // Create a fake file for upload
            $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100);
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-document-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/documents', [
                'file' => $file,
                'name' => 'New Document ' . uniqid(),
                'category' => 'general',
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create document");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/documents returns 403 without permission
     */
    public function test_create_document_returns_403_without_permission(): void
    {
        $roles = ['member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100);
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-document-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/documents', [
                'file' => $file,
                'name' => 'New Document',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create document");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/documents/{id} requires tenant.manage_documents permission
     */
    public function test_update_document_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/documents/{$this->document->id}", [
            'name' => 'Updated Document Name',
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/documents/{id} returns 403 without permission
     */
    public function test_update_document_returns_403_without_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/documents/{$this->document->id}", [
            'name' => 'Updated Document Name',
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that DELETE /api/v1/app/documents/{id} requires tenant.manage_documents permission
     */
    public function test_delete_document_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $documentToDelete = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'original_name' => 'delete-me.pdf',
            'file_path' => 'documents/delete-me.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/documents/{$documentToDelete->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test tenant isolation - documents from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create document in tenant B
        $documentB = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'original_name' => 'tenant-b-document.pdf',
            'file_path' => 'documents/tenant-b-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
        // Create user in tenant A
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // User A should only see documents from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents');
        
        $response->assertStatus(200);
        $documents = $response->json('data', []);
        
        // Verify document B is not in the list
        $documentIds = array_column($documents, 'id');
        $this->assertNotContains($documentB->id, $documentIds, 'Tenant B document should not be visible in tenant A');
        
        // Verify user A cannot access document B directly
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/documents/{$documentB->id}");
        
        // Should return 403 or 404 (depending on implementation)
        $this->assertContains($response2->status(), [403, 404], 'Should not be able to access tenant B document');
    }

    /**
     * Test that tenant A cannot modify document of tenant B
     */
    public function test_cannot_modify_document_of_another_tenant(): void
    {
        // Create document in tenant B
        $documentB = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'original_name' => 'tenant-b-document.pdf',
            'file_path' => 'documents/tenant-b-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
        // Create owner of tenant A
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;
        
        // Attempt to update document of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/documents/{$documentB->id}", [
            'name' => 'Hacked Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B document');
        
        // Verify document B is unchanged
        $documentB->refresh();
        $this->assertEquals('tenant-b-document.pdf', $documentB->original_name, 'Document should not be modified');
    }
}

