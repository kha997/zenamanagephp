<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Documents Store API
 * 
 * Round 177: Project Document Upload Endpoint
 * 
 * Tests that project document upload endpoint works with proper tenant isolation and validation.
 * 
 * @group projects
 * @group documents
 */
class ProjectDocumentsStoreTest extends TestCase
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
        // These tests run against SQLite in memory, but the documents table enforces
        // multiple foreign keys that do not behave reliably under SQLite's FK implementation.
        if ($this->usingSqlite()) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(17701);
        
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
        
        // Fake storage for file uploads
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
     * Test happy path - upload document successfully
     */
    public function test_store_document_creates_document_successfully(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('test-document.pdf', 1000);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
            'name' => 'Test Document',
            'description' => 'This is a test document',
            'category' => 'report',
        ]);

        $response->assertStatus(201);
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
        $this->assertEquals('Test Document', $data['name']);
        $this->assertEquals('This is a test document', $data['description']);
        $this->assertEquals('report', $data['category']);
        $this->assertEquals('active', $data['status']); // Default status
        $this->assertNotNull($data['file_path']);
        $this->assertNotNull($data['uploaded_by']);

        // Verify document exists in database
        $this->assertDatabaseHas('documents', [
            'id' => $data['id'],
            'project_id' => $project->id,
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Document',
            'uploaded_by' => $this->userA->id,
        ]);

        // Verify file was stored
        Storage::disk('local')->assertExists($data['file_path']);
    }

    /**
     * Test upload with minimal data (only file)
     */
    public function test_store_document_with_minimal_data(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        
        // Name should default to filename without extension
        $this->assertEquals('document', $data['name']);
        $this->assertEquals('general', $data['category']); // Default category
        $this->assertEquals('active', $data['status']); // Default status
        $this->assertNull($data['description']);
    }

    /**
     * Test tenant isolation - user from Tenant B cannot upload to Tenant A project
     */
    public function test_store_document_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 1000);

        // Try to upload from tenant B user
        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->postJson("/api/v1/app/projects/{$projectA->id}/documents", [
            'file' => $file,
            'name' => 'Unauthorized Document',
        ]);

        // Should return 404 (not found) due to tenant isolation
        $response->assertStatus(404);
        // Laravel's exception handler may return different format, just check status

        // Verify no document was created
        $this->assertDatabaseMissing('documents', [
            'name' => 'Unauthorized Document',
        ]);
    }

    /**
     * Test validation - file is required
     */
    public function test_store_document_requires_file(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'name' => 'Document without file',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        // Verify no document was created
        $this->assertDatabaseMissing('documents', [
            'name' => 'Document without file',
        ]);
    }

    /**
     * Test validation - invalid category
     */
    public function test_store_document_validates_category(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 1000);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
            'category' => 'invalid-category',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    /**
     * Test validation - invalid status
     */
    public function test_store_document_validates_status(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 1000);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    /**
     * Test unauthorized access - no authentication
     */
    public function test_store_document_requires_authentication(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 1000);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test project not found
     */
    public function test_store_document_returns_404_for_nonexistent_project(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/nonexistent-id/documents", [
            'file' => $file,
        ]);

        $response->assertStatus(404);
        // Laravel's exception handler may return different format, just check status
    }

    /**
     * Test upload with all optional fields
     */
    public function test_store_document_with_all_optional_fields(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $file = UploadedFile::fake()->create('contract.pdf', 2000);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/documents", [
            'file' => $file,
            'name' => 'Contract Document',
            'description' => 'Important contract document',
            'category' => 'contract',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        
        $this->assertEquals('Contract Document', $data['name']);
        $this->assertEquals('Important contract document', $data['description']);
        $this->assertEquals('contract', $data['category']);
        $this->assertEquals('draft', $data['status']);

        // Verify in database
        $this->assertDatabaseHas('documents', [
            'name' => 'Contract Document',
            'category' => 'contract',
            'status' => 'draft',
            'project_id' => $project->id,
            'tenant_id' => $this->tenantA->id,
        ]);
    }
}

