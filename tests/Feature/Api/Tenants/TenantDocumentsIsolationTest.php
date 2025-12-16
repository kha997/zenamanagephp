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
 * Tests for Documents API cross-tenant isolation
 * 
 * Tests that documents endpoints properly enforce tenant isolation
 * and prevent cross-tenant access via API routes.
 * 
 * Round 30: RBAC Gap Sweep & Missing Modules
 * 
 * @group tenant-documents-isolation
 * @group tenant-permissions
 */
class TenantDocumentsIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Document $documentA;
    private Document $documentB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(55555);
        $this->setDomainName('tenant-documents-isolation');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create document A in tenant A
        $this->documentA = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'original_name' => 'tenant-a-document.pdf',
            'file_path' => 'documents/tenant-a-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
        // Create document B in tenant B
        $this->documentB = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'original_name' => 'tenant-b-document.pdf',
            'file_path' => 'documents/tenant-b-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
    }

    /**
     * Test that tenant A cannot see documents from tenant B
     */
    public function test_tenant_a_cannot_see_tenant_b_documents(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents');
        
        $response->assertStatus(200);
        $documents = $response->json('data', []);
        
        // Verify document B is not in the list
        $documentIds = array_column($documents, 'id');
        $this->assertNotContains($this->documentB->id, $documentIds, 'Tenant B document should not be visible in tenant A');
        $this->assertContains($this->documentA->id, $documentIds, 'Tenant A document should be visible');
    }

    /**
     * Test that tenant A cannot access document B directly
     */
    public function test_tenant_a_cannot_access_tenant_b_document(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/documents/{$this->documentB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to access tenant B document');
    }

    /**
     * Test that tenant A cannot update document B
     */
    public function test_tenant_a_cannot_update_tenant_b_document(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $originalName = $this->documentB->original_name;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/documents/{$this->documentB->id}", [
            'name' => 'Hacked Document Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B document');
        
        // Verify document B is unchanged
        $this->documentB->refresh();
        $this->assertEquals($originalName, $this->documentB->original_name, 'Document should not be modified');
    }

    /**
     * Test that tenant A cannot delete document B
     */
    public function test_tenant_a_cannot_delete_tenant_b_document(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $documentBId = $this->documentB->id;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/documents/{$documentBId}");
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to delete tenant B document');
        
        // Verify document B still exists
        $this->assertDatabaseHas('documents', [
            'id' => $documentBId,
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test that tenant B cannot see documents from tenant A
     */
    public function test_tenant_b_cannot_see_tenant_a_documents(): void
    {
        Sanctum::actingAs($this->userB);
        $token = $this->userB->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents');
        
        $response->assertStatus(200);
        $documents = $response->json('data', []);
        
        // Verify document A is not in the list
        $documentIds = array_column($documents, 'id');
        $this->assertNotContains($this->documentA->id, $documentIds, 'Tenant A document should not be visible in tenant B');
        $this->assertContains($this->documentB->id, $documentIds, 'Tenant B document should be visible');
    }
}

