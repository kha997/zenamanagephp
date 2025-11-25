<?php declare(strict_types=1);

namespace Tests\Feature\Api\Documents;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Documents API tenant permission enforcement
 * 
 * Tests that documents endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET requests) and mutation endpoints (POST/PUT/PATCH/DELETE).
 * 
 * Round 13: Hardening Documents view permissions
 * Round 14: Hardening Documents manage permissions
 * 
 * @group documents
 * @group tenant-permissions
 */
class DocumentsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(67890);
        $this->setDomainName('documents-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create a user for uploaded_by
        $uploader = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Create document
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Document',
            'original_name' => 'test-document.pdf',
            'status' => 'active',
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET documents endpoints (Round 13)
     * 
     * All standard roles have tenant.view_documents from config, so should all pass.
     */
    public function test_all_standard_roles_can_get_documents_endpoints(): void
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

            // All standard roles should be able to GET documents list
            $listResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/documents');

            $listResponse->assertStatus(200, "Role {$role} should be able to GET documents list (has tenant.view_documents)");

            // All standard roles should be able to GET specific document
            $showResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson("/api/v1/app/documents/{$this->document->id}");

            $showResponse->assertStatus(200, "Role {$role} should be able to GET document detail (has tenant.view_documents)");

            // All standard roles should be able to GET documents/kpis
            $kpisResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/documents/kpis');

            $kpisResponse->assertStatus(200, "Role {$role} should be able to GET documents KPIs (has tenant.view_documents)");

            // All standard roles should be able to GET documents/alerts
            $alertsResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/documents/alerts');

            $alertsResponse->assertStatus(200, "Role {$role} should be able to GET documents alerts (has tenant.view_documents)");

            // All standard roles should be able to GET documents/activity
            $activityResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/documents/activity');

            $activityResponse->assertStatus(200, "Role {$role} should be able to GET documents activity (has tenant.view_documents)");
        }
    }

    /**
     * Test that user without tenant.view_documents cannot GET documents endpoints (Round 13)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_documents_cannot_access_documents_endpoints(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_documents
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET documents list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents');

        $listResponse->assertStatus(403);
        $listResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET specific document
        $showResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/documents/{$this->document->id}");

        $showResponse->assertStatus(403);
        $showResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET documents/kpis
        $kpisResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/kpis');

        $kpisResponse->assertStatus(403);
        $kpisResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET documents/alerts
        $alertsResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/alerts');

        $alertsResponse->assertStatus(403);
        $alertsResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET documents/activity
        $activityResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/activity');

        $activityResponse->assertStatus(403);
        $activityResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that documents are scoped to active tenant
     */
    public function test_documents_are_scoped_to_active_tenant(): void
    {
        // Create another tenant and document
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        // Create a user for the other tenant's document
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email_verified_at' => now(),
        ]);
        
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Document',
            'uploaded_by' => $otherUser->id,
        ]);

        // Create user with admin role in first tenant
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // User should only see documents from their active tenant
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents');

        $response->assertStatus(200);
        $documents = $response->json('data') ?? $response->json('documents') ?? [];
        
        // Verify all returned documents belong to the active tenant
        foreach ($documents as $document) {
            $documentTenantId = $document['tenant_id'] ?? null;
            $this->assertEquals(
                $this->tenant->id,
                $documentTenantId,
                'Documents should only be from active tenant'
            );
        }
        
        // Verify other tenant's document is not included
        $documentIds = array_column($documents, 'id');
        $this->assertNotContains($otherDocument->id, $documentIds, 'Other tenant document should not be visible');
    }

    /**
     * Test that owner and admin can manage documents (Round 14)
     * 
     * Owner and admin have tenant.manage_documents permission, so should be able to
     * create, update, delete documents and generate TTL links.
     */
    public function test_owner_and_admin_can_manage_documents(): void
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

            // Test POST /documents (create) - requires idempotency key and file upload
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->post('/api/v1/app/documents', [
                'name' => 'New Document',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('new-document.pdf', 100),
                'category' => 'general',
            ]);

            // Should succeed (201 or 200 depending on controller implementation)
            $this->assertContains(
                $createResponse->status(),
                [200, 201, 204],
                "Role {$role} should be able to POST documents (has tenant.manage_documents)"
            );

            // Get the created document ID if successful
            $createdDocumentId = $createResponse->json('data.id') ?? $createResponse->json('id') ?? $this->document->id;

            // Test PUT /documents/{id} (update) - requires idempotency key
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/documents/{$createdDocumentId}", [
                'name' => 'Updated Document',
                'status' => 'approved',
            ]);

            $this->assertContains(
                $updateResponse->status(),
                [200, 204],
                "Role {$role} should be able to PUT documents (has tenant.manage_documents)"
            );

            // Test PATCH /documents/{id} (partial update) - requires idempotency key
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/documents/{$createdDocumentId}", [
                'name' => 'Patched Document',
                'status' => 'pending',
            ]);

            $this->assertContains(
                $patchResponse->status(),
                [200, 204],
                "Role {$role} should be able to PATCH documents (has tenant.manage_documents)"
            );

            // Test POST /documents/{document}/ttl-link (generate TTL link)
            $ttlResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->postJson("/api/v1/app/documents/{$this->document->id}/ttl-link", [
                'expires_in' => 3600,
            ]);

            $this->assertContains(
                $ttlResponse->status(),
                [200, 201],
                "Role {$role} should be able to POST TTL link (has tenant.manage_documents)"
            );

            // Test DELETE /documents/{id} (delete)
            // Use a separate document to avoid affecting other tests
            $documentToDelete = Document::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Document to Delete',
                'original_name' => 'delete-me.pdf',
                'status' => 'active',
                'uploaded_by' => $user->id,
            ]);

            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/documents/{$documentToDelete->id}");

            $this->assertContains(
                $deleteResponse->status(),
                [200, 204],
                "Role {$role} should be able to DELETE documents (has tenant.manage_documents)"
            );
        }
    }

    /**
     * Test that member and viewer cannot manage documents (Round 14)
     * 
     * Member and viewer do NOT have tenant.manage_documents permission, so should get 403
     * when trying to create, update, delete documents or generate TTL links.
     */
    public function test_member_and_viewer_cannot_manage_documents(): void
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

            // Test POST /documents (create) - should fail with 403
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson('/api/v1/app/documents', [
                'name' => 'New Document',
                'original_name' => 'new-document.pdf',
                'status' => 'active',
            ]);

            $createResponse->assertStatus(403);
            $createResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to POST documents (no tenant.manage_documents)");

            // Test PUT /documents/{id} (update) - should fail with 403
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/documents/{$this->document->id}", [
                'name' => 'Updated Document',
            ]);

            $updateResponse->assertStatus(403);
            $updateResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PUT documents (no tenant.manage_documents)");

            // Test PATCH /documents/{id} (partial update) - should fail with 403
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/documents/{$this->document->id}", [
                'name' => 'Patched Document',
            ]);

            $patchResponse->assertStatus(403);
            $patchResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PATCH documents (no tenant.manage_documents)");

            // Test DELETE /documents/{id} (delete) - should fail with 403
            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/documents/{$this->document->id}");

            $deleteResponse->assertStatus(403);
            $deleteResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to DELETE documents (no tenant.manage_documents)");

            // Test POST /documents/{document}/ttl-link (generate TTL link) - should fail with 403
            $ttlResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->postJson("/api/v1/app/documents/{$this->document->id}/ttl-link", [
                'expires_in' => 3600,
            ]);

            $ttlResponse->assertStatus(403);
            $ttlResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to POST TTL link (no tenant.manage_documents)");
        }
    }

    /**
     * Smoke test for GET /api/v1/app/documents/activity endpoint
     * 
     * Round 31: Bugfix + test hardening
     * 
     * Verifies that the endpoint returns 200 with success:true for users with tenant.view_documents permission.
     */
    public function test_smoke_documents_activity_endpoint(): void
    {
        // Create user with viewer role (has tenant.view_documents permission)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer', // Has tenant.view_documents from config
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Call GET /api/v1/app/documents/activity
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/activity');

        // Assert 200 status
        $response->assertStatus(200);

        // Assert success:true in response
        $response->assertJson([
            'success' => true,
        ]);

        // Assert data structure exists
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'action',
                    'description',
                    'timestamp',
                    'user' => [
                        'id',
                        'name',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Negative smoke test for GET /api/v1/app/documents/activity endpoint
     * 
     * Round 32: Activity & Permission Hardening
     * 
     * Verifies that the endpoint returns 403 with TENANT_PERMISSION_DENIED for users without tenant.view_documents permission.
     */
    public function test_negative_smoke_documents_activity_endpoint_permission_denied(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_documents
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Call GET /api/v1/app/documents/activity
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/activity');

        // Assert 403 status
        $response->assertStatus(403);

        // Assert standard permission error envelope
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that documents activity uses uploader user when available
     * 
     * Round 33: Lock activity behavior - verify uploader is prioritized over authenticated user
     * 
     * Verifies that when a document has an uploader_id, the activity entry shows the uploader
     * user information, not the authenticated user who is viewing the activity.
     */
    public function test_documents_activity_uses_uploader_user_when_available(): void
    {
        // Setup: Tenant T
        // User A (authenticated viewer) with tenant.view_documents
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User A - Viewer',
            'email_verified_at' => now(),
        ]);

        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'viewer', // Has tenant.view_documents
            'is_default' => true,
        ]);

        // User B (uploader, different from User A)
        $userB = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User B - Uploader',
            'email_verified_at' => now(),
        ]);

        $userB->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => false,
        ]);

        // Create document with uploader_id = User B
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Document with Uploader',
            'original_name' => 'test-document-uploader.pdf',
            'status' => 'active',
            'uploaded_by' => $userB->id, // Document uploaded by User B
        ]);

        // Authenticate as User A
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;

        // Call GET /api/v1/app/documents/activity with token of User A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/documents/activity');

        // Assert 200, success: true
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Assert response structure
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'action',
                    'description',
                    'timestamp',
                    'user' => [
                        'id',
                        'name',
                    ],
                ],
            ],
        ]);

        $activityData = $response->json('data');
        $this->assertIsArray($activityData, 'Activity data should be an array');

        // Find the entry for our document
        $documentActivity = null;
        foreach ($activityData as $entry) {
            if (isset($entry['id']) && $entry['id'] === 'document-' . $document->id) {
                $documentActivity = $entry;
                break;
            }
        }

        $this->assertNotNull($documentActivity, 'Document activity entry should exist');

        // Assert: activity_item['user']['id'] and ['name'] match User B (uploader), not User A
        $this->assertEquals(
            $userB->id,
            $documentActivity['user']['id'],
            'Activity user ID should match uploader (User B), not authenticated user (User A)'
        );

        $this->assertEquals(
            $userB->name,
            $documentActivity['user']['name'],
            'Activity user name should match uploader (User B), not authenticated user (User A)'
        );

        // Verify it's NOT User A
        $this->assertNotEquals(
            $userA->id,
            $documentActivity['user']['id'],
            'Activity user ID should NOT match authenticated user (User A)'
        );
    }

    /**
     * Test that guest cannot manage documents (Round 14)
     * 
     * Guest role is not defined in config/permissions.php tenant_roles, so user will have
     * no permissions and should get 403 when trying to manage documents.
     */
    public function test_guest_cannot_manage_documents(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.manage_documents
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Test POST /documents (create) - should fail with 403
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson('/api/v1/app/documents', [
            'name' => 'New Document',
            'original_name' => 'new-document.pdf',
            'status' => 'active',
        ]);

        $createResponse->assertStatus(403);
        $createResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Test PUT /documents/{id} (update) - should fail with 403
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/documents/{$this->document->id}", [
            'name' => 'Updated Document',
        ]);

        $updateResponse->assertStatus(403);
        $updateResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Test DELETE /documents/{id} (delete) - should fail with 403
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/documents/{$this->document->id}");

        $deleteResponse->assertStatus(403);
        $deleteResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Test POST /documents/{document}/ttl-link (generate TTL link) - should fail with 403
        $ttlResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/documents/{$this->document->id}/ttl-link", [
            'expires_in' => 3600,
        ]);

        $ttlResponse->assertStatus(403);
        $ttlResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }
}

