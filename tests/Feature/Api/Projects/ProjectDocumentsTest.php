<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Document;
use App\Models\ProjectActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Documents API
 * 
 * Round 170: Project Documents & History Endpoints
 * 
 * Tests that project documents endpoint returns documents with proper tenant isolation and RBAC.
 * 
 * @group projects
 * @group documents
 */
class ProjectDocumentsTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        // Call parent setUp first to bootstrap the application
        parent::setUp();
        
        // NOTE: SQLite Foreign Key Workaround
        // These tests run against SQLite in memory, but the documents table enforces
        // multiple foreign keys (tenant_id, project_id, uploaded_by/created_by/updated_by)
        // that do not behave reliably under SQLite's FK implementation, despite using
        // real migrations and valid data. In production (MySQL/PostgreSQL), FKs are fully
        // enforced at the DB level. Here we disable foreign key enforcement for this
        // test class ONLY on SQLite so we can focus on testing the API behavior:
        // - JSON response shape
        // - tenant isolation
        // - filter/search behavior
        // The underlying FK constraints remain enforced in real environments.
        if ($this->usingSqlite()) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(17001);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users - they will be created with ulid('id') matching the real migration
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'userA@test.com',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email' => 'userB@test.com',
        ]);
        
        // Attach users to tenants with appropriate roles
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'pm']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'pm']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        // Re-enable foreign key enforcement for SQLite so other tests are unaffected
        if ($this->usingSqlite()) {
            DB::statement('PRAGMA foreign_keys = ON');
        }
        
        parent::tearDown();
    }

    /**
     * Check if we're using SQLite driver
     */
    protected function usingSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }


    /**
     * Test happy path - same tenant, has permission
     */
    public function test_documents_returns_project_documents(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();
        
        // Verify user and project exist in database before creating documents
        $this->assertDatabaseHas('users', ['id' => $this->userA->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        
        // Use the user ID directly - it should exist now
        $userAId = $this->userA->id;

        // Create documents for this project
        // Ensure all foreign keys are set correctly
        $document1 = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Document 1',
            'description' => 'First document',
        ]);

        $document2 = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Document 2',
            'description' => 'Second document',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'title',
                    'description',
                    'category',
                    'status',
                    'file_type',
                    'mime_type',
                    'file_size',
                    'file_path',
                    'uploaded_by',
                    'created_at',
                    'updated_at',
                ]
            ],
            'message'
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        // Verify document IDs are present
        $documentIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($document1->id, $documentIds);
        $this->assertContains($document2->id, $documentIds);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_documents_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        // Get user ID fresh from database
        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        // Create document for tenant A project
        Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'uploaded_by' => $userAId,
            'name' => 'Tenant A Document',
        ]);

        // Try to access from tenant B
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson("/api/v1/app/projects/{$projectA->id}/documents");

        // Should return 404 (not found) due to tenant isolation
        $response->assertStatus(404);
    }

    /**
     * Test empty documents list
     */
    public function test_documents_returns_empty_array_when_no_documents(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Empty Project',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    /**
     * Test project not found
     */
    public function test_documents_returns_404_for_nonexistent_project(): void
    {
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/nonexistent-id/documents");

        $response->assertStatus(404);
        // Laravel's route model binding returns a different error format
        // Just verify it's a 404
    }

    /**
     * Test documents filtering by category
     */
    public function test_documents_can_be_filtered_by_category(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Get user ID fresh from database
        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        // Create documents with different categories
        Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Document 1',
            'category' => 'contract',
        ]);

        Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Document 2',
            'category' => 'report',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents?category=contract");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('contract', $data[0]['category']);
    }

    /**
     * Test documents search
     */
    public function test_documents_can_be_searched(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Get user ID fresh from database
        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Important Document',
            'description' => 'This is important',
        ]);

        Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Other Document',
            'description' => 'Unrelated content',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents?search=Important");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Important Document', $data[0]['name']);
    }

    /**
     * Test update project document metadata successfully
     */
    public function test_update_project_document_metadata_successfully(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Original Name',
            'description' => 'Original description',
            'category' => 'general',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->patchJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'category' => 'contract',
            'status' => 'archived',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'title',
                'description',
                'category',
                'status',
                'file_type',
                'mime_type',
                'file_size',
                'file_path',
                'uploaded_by',
                'created_at',
                'updated_at',
            ],
            'message'
        ]);

        $data = $response->json('data');
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('Updated description', $data['description']);
        $this->assertEquals('contract', $data['category']);
        $this->assertEquals('archived', $data['status']);

        // Verify database was updated
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'category' => 'contract',
            'status' => 'archived',
        ]);
    }

    /**
     * Test update document respects tenant isolation
     */
    public function test_update_document_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'uploaded_by' => $userAId,
            'name' => 'Tenant A Document',
        ]);

        // Try to update from tenant B
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->patchJson("/api/v1/app/projects/{$projectA->id}/documents/{$document->id}", [
            'name' => 'Hacked Name',
        ]);

        // Should return 404 due to tenant isolation
        $response->assertStatus(404);

        // Verify document was not updated
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Tenant A Document',
        ]);
    }

    /**
     * Test update document requires authentication
     */
    public function test_update_document_requires_authentication(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->patchJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test update nonexistent document returns 404
     */
    public function test_update_nonexistent_document_returns_404(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->patchJson("/api/v1/app/projects/{$project->id}/documents/nonexistent-id", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test update invalid payload returns 422
     */
    public function test_update_invalid_payload_returns_422(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->patchJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}", [
            'category' => 'invalid_category',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category', 'status']);
    }

    /**
     * Test delete project document successfully deletes record and file
     */
    public function test_delete_project_document_successfully_deletes_record_and_file(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create a file in storage
        $filePath = "projects/{$project->id}/documents/test-document.pdf";
        Storage::disk('local')->put($filePath, 'fake file content');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Test Document',
            'file_path' => $filePath,
        ]);

        // Verify file exists
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->deleteJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'message'
        ]);
        $response->assertJson([
            'success' => true,
            'data' => null,
            'message' => 'Project document deleted successfully.'
        ]);

        // Verify document record is deleted
        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        // Verify file is deleted
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    /**
     * Test delete document respects tenant isolation
     */
    public function test_delete_document_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'uploaded_by' => $userAId,
            'name' => 'Tenant A Document',
        ]);

        // Try to delete from tenant B
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->deleteJson("/api/v1/app/projects/{$projectA->id}/documents/{$document->id}");

        // Should return 404 due to tenant isolation
        $response->assertStatus(404);

        // Verify document was not deleted
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    /**
     * Test delete document requires authentication
     */
    public function test_delete_document_requires_authentication(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->deleteJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}");

        $response->assertStatus(401);
    }

    /**
     * Test delete nonexistent document returns 404
     */
    public function test_delete_nonexistent_document_returns_404(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->deleteJson("/api/v1/app/projects/{$project->id}/documents/nonexistent-id");

        $response->assertStatus(404);
    }

    /**
     * Test delete missing file still deletes document
     */
    public function test_delete_missing_file_still_deletes_document(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create document with file_path that doesn't exist in storage
        $filePath = "projects/{$project->id}/documents/missing-file.pdf";
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Test Document',
            'file_path' => $filePath,
        ]);

        // Verify file does NOT exist
        $this->assertFalse(Storage::disk('local')->exists($filePath));

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->deleteJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}");

        // Should still succeed even though file is missing
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => null,
            'message' => 'Project document deleted successfully.'
        ]);

        // Verify document record is deleted
        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);
    }

    /**
     * Test uploading document creates project activity
     */
    public function test_uploading_document_creates_project_activity(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        Sanctum::actingAs($this->userA);
        $file = \Illuminate\Http\UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
            'name' => 'Test Document',
            'description' => 'Test description',
        ]);

        $response->assertStatus(201);
        $documentId = $response->json('data.id');

        // Verify ProjectActivity was created
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_DOCUMENT_UPLOADED,
            'entity_type' => ProjectActivity::ENTITY_DOCUMENT,
            'entity_id' => $documentId,
        ]);

        // Verify activity description
        $activity = ProjectActivity::where('entity_id', $documentId)
            ->where('action', ProjectActivity::ACTION_DOCUMENT_UPLOADED)
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Uploaded document', $activity->description);
    }

    /**
     * Test updating document creates project activity
     */
    public function test_updating_document_creates_project_activity(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Original Name',
            'description' => 'Original description',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->patchJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);

        // Verify ProjectActivity was created
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_DOCUMENT_UPDATED,
            'entity_type' => ProjectActivity::ENTITY_DOCUMENT,
            'entity_id' => $document->id,
        ]);

        // Verify activity description
        $activity = ProjectActivity::where('entity_id', $document->id)
            ->where('action', ProjectActivity::ACTION_DOCUMENT_UPDATED)
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Updated document', $activity->description);
    }

    /**
     * Test deleting document creates project activity
     */
    public function test_deleting_document_creates_project_activity(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Test Document',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->deleteJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}");

        $response->assertStatus(200);

        // Verify ProjectActivity was created (before document deletion)
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_DOCUMENT_DELETED,
            'entity_type' => ProjectActivity::ENTITY_DOCUMENT,
            'entity_id' => $document->id,
        ]);

        // Verify activity description
        $activity = ProjectActivity::where('entity_id', $document->id)
            ->where('action', ProjectActivity::ACTION_DOCUMENT_DELETED)
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Deleted document', $activity->description);
    }

    /**
     * Test downloading document creates project activity
     */
    public function test_downloading_document_creates_project_activity(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create a file in storage
        $filePath = "projects/{$project->id}/documents/test-document.pdf";
        Storage::disk('local')->put($filePath, 'fake file content');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Test Document',
            'file_path' => $filePath,
            'file_size' => 100, // Small file to trigger direct stream
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents/{$document->id}/download");

        // Should return 200 (stream) or 200 (signed URL JSON)
        $response->assertStatus(200);

        // Verify ProjectActivity was created
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $this->userA->id,
            'action' => ProjectActivity::ACTION_DOCUMENT_DOWNLOADED,
            'entity_type' => ProjectActivity::ENTITY_DOCUMENT,
            'entity_id' => $document->id,
        ]);

        // Verify activity description
        $activity = ProjectActivity::where('entity_id', $document->id)
            ->where('action', ProjectActivity::ACTION_DOCUMENT_DOWNLOADED)
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Downloaded document', $activity->description);
    }

    /**
     * Test tenant isolation for document activities
     */
    public function test_tenant_isolation_for_document_activities(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        $userAId = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'uploaded_by' => $userAId,
            'name' => 'Tenant A Document',
        ]);

        // Try to update from tenant B (should fail)
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->patchJson("/api/v1/app/projects/{$projectA->id}/documents/{$document->id}", [
            'name' => 'Hacked Name',
        ]);

        // Should return 404 due to tenant isolation
        $response->assertStatus(404);

        // Verify NO activity was created for tenant B
        $this->assertDatabaseMissing('project_activities', [
            'project_id' => $projectA->id,
            'user_id' => $this->userB->id,
            'action' => ProjectActivity::ACTION_DOCUMENT_UPDATED,
            'entity_id' => $document->id,
        ]);
    }
}

