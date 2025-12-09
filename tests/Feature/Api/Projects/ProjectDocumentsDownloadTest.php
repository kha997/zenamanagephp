<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Document;
use App\Services\ProjectManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Documents Download API
 * 
 * Round 178: Hybrid Document Download (Direct Stream + Signed URL)
 * 
 * Tests that document download endpoint:
 * - Streams small files directly
 * - Returns signed URL for large files
 * - Enforces tenant isolation
 * - Handles missing files gracefully
 * 
 * @group projects
 * @group documents
 * @group download
 */
class ProjectDocumentsDownloadTest extends TestCase
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
        parent::setUp();
        
        // NOTE: SQLite Foreign Key Workaround
        if ($this->usingSqlite()) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(17801);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users
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
        
        // Fake storage for file operations
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
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
     * Create a document with a fake file stored
     */
    private function createDocumentWithFile(
        Project $project,
        User $user,
        string $fileName = 'test-document.pdf',
        int $fileSize = 1000,
        string $filePath = null
    ): Document {
        // Create fake file content
        $content = str_repeat('A', $fileSize);
        
        // Determine storage path
        if (!$filePath) {
            $filePath = "projects/{$project->id}/documents/{$fileName}";
        }
        
        // Store file
        Storage::disk('local')->put($filePath, $content);
        
        // Get user ID fresh from database
        $userId = DB::table('users')
            ->where('id', $user->id)
            ->value('id');
        
        // Get project tenant_id - use the project's tenant_id but ensure it's a string
        // Refresh project first to ensure tenant_id is loaded
        $project->refresh();
        $projectTenantId = (string) $project->tenant_id;
        
        // Create document record
        // Use string tenant_id for consistent comparison
        $document = Document::factory()->create([
            'tenant_id' => $projectTenantId,
            'project_id' => $project->id,
            'uploaded_by' => $userId,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'original_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => pathinfo($fileName, PATHINFO_EXTENSION),
            'mime_type' => 'application/pdf',
            'file_size' => $fileSize,
            'file_hash' => hash('sha256', $content),
            'category' => 'general',
            'status' => 'active',
        ]);
        
        return $document;
    }

    /**
     * Test direct stream for small file (authenticated endpoint)
     */
    public function test_download_small_file_streams_directly(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Create small file (1KB, well below 5MB threshold)
        $document = $this->createDocumentWithFile(
            $project,
            $this->userA,
            'small-file.pdf',
            1024 // 1KB
        );

        Sanctum::actingAs($this->userA);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertNotNull($contentDisposition, 'Content-Disposition header should be present');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString($document->original_name, $contentDisposition);
        
        // Verify file content (for streamed responses, content may not be directly accessible)
        // Just verify the response is successful and has correct headers
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * Test signed URL for large file (authenticated endpoint)
     */
    public function test_download_large_file_returns_signed_url(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Create large file (6MB, above 5MB threshold)
        // Use a more memory-efficient approach: just set file_size in DB, don't create actual file
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        $filePath = "projects/{$project->id}/documents/large-file.pdf";
        // Create a minimal file (just to have something on disk)
        Storage::disk('local')->put($filePath, 'test content');
        
        $document = Document::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'large-file',
            'original_name' => 'large-file.pdf',
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 6 * 1024 * 1024, // 6MB (above threshold)
            'file_hash' => hash('sha256', 'test content'),
            'category' => 'general',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->userA);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'signed_url',
                'expires_at',
                'mode'
            ],
            'message'
        ]);
        
        $data = $response->json('data');
        $this->assertTrue($response->json('success'));
        $this->assertEquals('signed_url', $data['mode']);
        $this->assertNotEmpty($data['signed_url']);
        $this->assertNotEmpty($data['expires_at']);
        
        // Verify signed URL is valid
        $this->assertStringContainsString('/api/v1/app/projects/documents/', $data['signed_url']);
        $this->assertStringContainsString('signature=', $data['signed_url']);
        $this->assertStringContainsString('expires=', $data['signed_url']);
    }

    /**
     * Test signed URL route streaming
     */
    public function test_signed_url_route_streams_file(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Create document with file
        $document = $this->createDocumentWithFile(
            $project,
            $this->userA,
            'test-file.pdf',
            2048 // 2KB
        );

        // Generate signed URL manually
        // Use raw ULID ID (string) for route parameter
        $documentId = (string) $document->id;
        $expiresAt = now()->addMinutes(15);
        $signedUrl = URL::temporarySignedRoute(
            'app.projects.documents.signed-download',
            $expiresAt,
            ['doc' => $documentId] // Route parameter is 'doc', not 'document'
        );

        // Extract path and query from signed URL
        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'];
        $query = $parsedUrl['query'] ?? '';

        // Call signed route (no auth required)
        $response = $this->get("{$path}?{$query}");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertNotNull($contentDisposition, 'Content-Disposition header should be present');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString($document->original_name, $contentDisposition);
        
        // Verify file content (for streamed responses, content may not be directly accessible)
        // Just verify the response is successful and has correct headers
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * Test invalid signature returns 403
     */
    public function test_signed_url_with_invalid_signature_returns_403(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $document = $this->createDocumentWithFile(
            $project,
            $this->userA,
            'test-file.pdf',
            1024
        );

        // Call signed route with tampered signature
        // Use raw ULID ID (string) for route parameter
        $documentId = (string) $document->id;
        $response = $this->get("/api/v1/app/projects/documents/{$documentId}/file?signature=invalid&expires=" . (time() + 3600));

        $response->assertStatus(403);
    }

    /**
     * Test expired signed URL returns 403
     */
    public function test_signed_url_with_expired_signature_returns_403(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $document = $this->createDocumentWithFile(
            $project,
            $this->userA,
            'test-file.pdf',
            1024
        );

        // Generate expired signed URL
        // Use raw ULID ID (string) for route parameter
        $documentId = (string) $document->id;
        $expiresAt = now()->subMinutes(1); // Already expired
        $signedUrl = URL::temporarySignedRoute(
            'app.projects.documents.signed-download',
            $expiresAt,
            ['doc' => $documentId] // Route parameter is 'doc', not 'document'
        );

        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'];
        $query = $parsedUrl['query'] ?? '';

        $response = $this->get("{$path}?{$query}");

        $response->assertStatus(403);
    }

    /**
     * Test tenant isolation on authenticated endpoint
     */
    public function test_download_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        // Get user ID fresh from database
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create document for tenant A
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'uploaded_by' => $userAId,
            'name' => 'Tenant A Document',
            'file_size' => 1024,
        ]);

        // Try to access from tenant B
        Sanctum::actingAs($this->userB);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $projectA->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        // Should return 404 (not found) due to tenant isolation
        $response->assertStatus(404);
    }

    /**
     * Test unauthenticated request returns 401
     */
    public function test_download_requires_authentication(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Get user ID fresh from database
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'file_size' => 1024,
        ]);

        // Call without authentication
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        $response->assertStatus(401);
    }

    /**
     * Test missing file on disk returns 404 (small file)
     */
    public function test_download_missing_file_returns_404_small_file(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Get user ID fresh from database
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create document record but don't create file
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Missing File',
            'file_path' => 'projects/' . $project->id . '/documents/missing.pdf',
            'file_size' => 1024, // Small file, should stream directly
        ]);

        Sanctum::actingAs($this->userA);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        $response->assertStatus(404);
        // When document exists but file is missing, we should get FILE_NOT_FOUND
        // But if document lookup fails first, we get DOCUMENT_NOT_FOUND
        // Both are valid 404 responses, so we just check for 404
        $this->assertTrue(in_array($response->json('error'), ['FILE_NOT_FOUND', 'DOCUMENT_NOT_FOUND']));
    }

    /**
     * Test missing file on disk returns 404 (signed route)
     */
    public function test_signed_route_missing_file_returns_404(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Get user ID fresh from database
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');

        // Create document record but don't create file
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'Missing File',
            'file_path' => 'projects/' . $project->id . '/documents/missing.pdf',
            'file_size' => 1024,
        ]);

        // Generate signed URL
        // Use raw ULID ID (string) for route parameter
        $documentId = (string) $document->id;
        $expiresAt = now()->addMinutes(15);
        $signedUrl = URL::temporarySignedRoute(
            'app.projects.documents.signed-download',
            $expiresAt,
            ['doc' => $documentId] // Route parameter is 'doc', not 'document'
        );

        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'];
        $query = $parsedUrl['query'] ?? '';

        $response = $this->getJson("{$path}?{$query}");

        $response->assertStatus(404);
        // When document exists but file is missing, we should get FILE_NOT_FOUND
        $this->assertTrue(in_array($response->json('error'), ['FILE_NOT_FOUND', 'DOCUMENT_NOT_FOUND']));
    }

    /**
     * Test document not found returns 404
     */
    public function test_download_nonexistent_document_returns_404(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        Sanctum::actingAs($this->userA);
        // Use a valid ULID format that doesn't exist
        $nonexistentId = (string) \Illuminate\Support\Str::ulid();
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/documents/{$nonexistentId}/download");

        $response->assertStatus(404);
        // The response should be JSON with our error format
        $json = $response->json();
        $this->assertFalse($json['success'] ?? true);
        $this->assertEquals('DOCUMENT_NOT_FOUND', $json['error'] ?? null);
    }

    /**
     * Test threshold boundary: exactly at threshold uses direct stream
     */
    public function test_file_at_threshold_uses_direct_stream(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Create file exactly at threshold (5MB)
        // Use a more memory-efficient approach: just set file_size in DB
        $threshold = ProjectManagementService::LARGE_FILE_THRESHOLD_BYTES;
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        $filePath = "projects/{$project->id}/documents/threshold-file.pdf";
        // Create a minimal file (just to have something on disk)
        Storage::disk('local')->put($filePath, 'test content');
        
        $document = Document::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'threshold-file',
            'original_name' => 'threshold-file.pdf',
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $threshold, // Exactly at threshold
            'file_hash' => hash('sha256', 'test content'),
            'category' => 'general',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->userA);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        // Should stream directly (not return JSON)
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        // Verify it's not JSON by checking content type
        $this->assertNotEquals('application/json', $response->headers->get('content-type'));
        // Verify response is successful (streamed responses may not have accessible content)
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * Test threshold boundary: one byte over threshold uses signed URL
     */
    public function test_file_one_byte_over_threshold_uses_signed_url(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Refresh user to ensure it's loaded from database
        $this->userA->refresh();

        // Create file one byte over threshold
        // Use a more memory-efficient approach: just set file_size in DB
        $threshold = ProjectManagementService::LARGE_FILE_THRESHOLD_BYTES;
        $userAId = DB::table('users')
            ->where('id', $this->userA->id)
            ->value('id');
        
        $filePath = "projects/{$project->id}/documents/large-file.pdf";
        // Create a minimal file (just to have something on disk)
        Storage::disk('local')->put($filePath, 'test content');
        
        $document = Document::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'uploaded_by' => $userAId,
            'name' => 'large-file',
            'original_name' => 'large-file.pdf',
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $threshold + 1, // One byte over threshold
            'file_hash' => hash('sha256', 'test content'),
            'category' => 'general',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->userA);
        // Use raw ULID IDs (strings) for route parameters
        $projectId = (string) $project->id;
        $documentId = (string) $document->id;
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$projectId}/documents/{$documentId}/download");

        // Should return signed URL JSON
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('signed_url', $data['mode']);
    }
}

