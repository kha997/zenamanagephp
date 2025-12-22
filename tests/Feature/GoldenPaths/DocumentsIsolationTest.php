<?php declare(strict_types=1);

namespace Tests\Feature\GoldenPaths;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Golden Path 3: Documents (Upload, List, Download)
 * 
 * Tests the critical flow: Upload file to project → List documents → Download document
 * Verifies tenant isolation: User tenant A cannot download file from tenant B
 */
class DocumentsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
    }

    /** @test */
    public function user_can_upload_document_to_project(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $file = UploadedFile::fake()->create('test-document.txt', 100);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->postJson('/api/v1/app/documents', [
            'file' => $file,
            'project_id' => $project->id,
            'name' => 'Test Document',
            'description' => 'Test document for golden path',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'document' => [
                    'id',
                    'name',
                    'project_id',
                    'tenant_id',
                ],
            ],
        ]);
        
        $document = $response->json('data.document');
        $this->assertEquals('Test Document', $document['name']);
        $this->assertEquals($project->id, $document['project_id']);
        $this->assertEquals($this->tenantId, $document['tenant_id']);
    }

    /** @test */
    public function user_can_list_documents_for_project(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'name' => 'Document 1',
        ]);
        
        Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'name' => 'Document 2',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/documents?project_id={$project->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'documents',
            ],
        ]);
        
        $documents = $response->json('data.documents');
        $this->assertIsArray($documents);
        $this->assertCount(2, $documents);
        
        // All documents should belong to project and tenant
        foreach ($documents as $document) {
            $this->assertEquals($project->id, $document['project_id']);
            $this->assertEquals($this->tenantId, $document['tenant_id']);
        }
    }

    /** @test */
    public function user_cannot_download_document_from_other_tenant(): void
    {
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX6Y';
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        
        $otherDocument = Document::factory()->create([
            'project_id' => $otherProject->id,
            'tenant_id' => $otherTenantId,
            'name' => 'Other Tenant Document',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/documents/{$otherDocument->id}/download", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 403 Forbidden or 404 Not Found (tenant isolation)
        $this->assertContains($response->status(), [403, 404]);
    }

    /** @test */
    public function user_can_download_own_tenant_document(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $document = Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
            'name' => 'My Document',
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/documents/{$document->id}/download", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 200 OK with file download
        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('Content-Disposition'));
    }

    /** @test */
    public function file_too_large_returns_error(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        // Create a file that exceeds size limit (assuming 10MB limit)
        $file = UploadedFile::fake()->create('large-file.txt', 11000); // 11MB
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->postJson('/api/v1/app/documents', [
            'file' => $file,
            'project_id' => $project->id,
            'name' => 'Large Document',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 413 Payload Too Large or 422 Validation Failed
        $this->assertContains($response->status(), [413, 422]);
        
        if ($response->status() === 413) {
            $response->assertJsonStructure([
                'ok',
                'code',
                'message',
            ]);
            $this->assertEquals('FILE_TOO_LARGE', $response->json('code'));
        }
    }

    /** @test */
    public function invalid_file_type_returns_error(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        // Create an executable file (should be blocked)
        $file = UploadedFile::fake()->create('script.exe', 100);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->postJson('/api/v1/app/documents', [
            'file' => $file,
            'project_id' => $project->id,
            'name' => 'Executable File',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 422 Unprocessable Entity
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'details',
        ]);
        
        $this->assertFalse($response->json('ok'));
        $this->assertEquals('INVALID_FILE_TYPE', $response->json('code'));
    }

    /** @test */
    public function documents_are_filtered_by_tenant(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);
        
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX6Y';
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        
        // Create documents in both tenants
        Document::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenantId,
        ]);
        
        Document::factory()->create([
            'project_id' => $otherProject->id,
            'tenant_id' => $otherTenantId,
        ]);
        
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/app/documents', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $documents = $response->json('data.documents');
        
        // Should only see documents from user's tenant
        foreach ($documents as $document) {
            $this->assertEquals($this->tenantId, $document['tenant_id']);
        }
    }
}

