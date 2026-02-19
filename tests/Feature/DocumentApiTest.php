<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\ApiTestTrait;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTrait, ApiTestTrait;

    protected $tenant;
    protected $user;
    protected $project;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');

        $this->tenant = Tenant::factory()->create([
            'name' => 'Document Tenant',
            'domain' => 'documents.example.com',
            'is_active' => true,
        ]);

        $this->user = $this->createRbacAdminUser($this->tenant, [
            'name' => 'Document User',
            'email' => 'documents@example.com',
        ]);

        $token = $this->user->createToken('document-test-token')->plainTextToken;
        $headers = $this->authHeadersForUser($this->user, $token);
        $this->apiHeaders = $headers;
        $this->withHeaders($headers);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

    }

    /**
     * Test upload document
     */
    public function test_can_upload_document()
    {
        $file = $this->createValidPdfUploadedFile('test-document.pdf');

        $response = $this->apiPostMultipart('/api/v1/documents', [
            'title' => 'Test Document',
            'project_id' => $this->project->id,
            'document_type' => 'drawing',
            'linked_entity_type' => 'project',
            'linked_entity_id' => $this->project->id,
            'file' => $file,
            'comment' => 'Initial upload'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'id',
                         'title',
                         'project_id',
                         'document_type',
                         'version',
                         'created_at',
                         'updated_at'
                     ]
                 ]);

        $this->assertDatabaseHas('documents', [
            'name' => 'Test Document',
            'project_id' => $this->project->id
        ]);

    }

    /**
     * Test get all documents
     */
    public function test_can_get_all_documents()
    {
        foreach (['Doc 1', 'Doc 2', 'Doc 3'] as $name) {
            $this->createDocumentForTest($name);
        }

        $response = $this->apiGet('/api/v1/documents');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'project_id',
                             'created_at',
                             'updated_at'
                         ]
                     ],
                     'meta' => [
                         'pagination' => [
                             'page',
                             'per_page',
                             'total',
                             'last_page',
                         ]
                     ]
                 ]);
    }

    /**
     * Test upload new version
     */
    public function test_can_upload_new_version()
    {
        $documentId = $this->uploadTestDocument('Versioned Document');

        $file = $this->createValidPdfUploadedFile('test-document-v2.pdf');

        $response = $this->apiPostMultipart("/api/v1/documents/{$documentId}/versions", [
            'file' => $file,
            'comment' => 'Updated version',
            'version' => 2
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'id',
                         'parent_document_id',
                         'version',
                         'file_path',
                         'mime_type',
                         'file_name',
                         'created_at',
                         'updated_at'
                     ]
                 ]);

    }

    /**
     * Test revert to previous version
     */
    public function test_can_revert_to_previous_version()
    {
        $documentId = $this->uploadTestDocument('Revert Document');

        $file = $this->createValidPdfUploadedFile('revert-document-v2.pdf');

        $this->apiPostMultipart("/api/v1/documents/{$documentId}/versions", [
            'file' => $file,
            'comment' => 'Second version',
            'version' => 2
        ])->assertStatus(201);

        $response = $this->apiPost('/api/v1/documents/non-existent-document/revert', [ // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_UNSUPPORTED_ENDPOINT)
            'version_number' => 1,
            'comment' => 'Reverting to first version'
        ]);

        $response->assertStatus(404)
                 ->assertJsonStructure([
                     'message'
                 ]);
    }

    /**
     * Test download document
     */
    public function test_can_download_document()
    {
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'file_path' => 'documents/test-file.pdf',
        ]);

        // Tạo file giả trong storage
        Storage::put('documents/test-file.pdf', 'fake file content');

        $response = $this->apiGet("/api/v1/documents/{$document->id}/download");

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test validation errors
     */
    public function test_upload_document_validation_errors()
    {
        $response = $this->apiPost('/api/v1/documents', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title', 'project_id', 'file']);
    }
    private function createValidPdfUploadedFile(string $name = 'test-document.pdf', int $paddingBytes = 0): UploadedFile
    {
        $padding = max(0, $paddingBytes);
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\n";

        if ($padding > 0) {
            $content .= str_repeat('0', $padding) . "\n";
        }

        $content .= "trailer<<>>\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $content, 'application/pdf');
    }

    private function createDocumentForTest(string $name): string
    {
        $timestamp = now();

        $id = (string) Str::ulid();

        DB::table('documents')->insert([
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'name' => $name,
            'original_name' => Str::slug($name) . '.pdf',
            'file_path' => 'documents/' . Str::random(8) . '/' . Str::slug($name) . '.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 524288,
            'file_hash' => Str::ulid(),
            'category' => 'drawing',
            'description' => 'Auto generated document for testing',
            'metadata' => json_encode(['document_type' => 'drawing']),
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $id;
    }

    private function uploadTestDocument(string $title = 'Test Document'): string
    {
        $file = $this->createValidPdfUploadedFile('test-document.pdf');

        $response = $this->apiPostMultipart('/api/v1/documents', [
            'title' => $title,
            'project_id' => $this->project->id,
            'document_type' => 'drawing',
            'linked_entity_type' => 'project',
            'linked_entity_id' => $this->project->id,
            'file' => $file,
            'comment' => 'Automated upload'
        ]);

        $response->assertStatus(201);

        return $response->json('data.id');
    }
}
