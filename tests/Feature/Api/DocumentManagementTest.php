<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
        
        Storage::fake('local');
    }

    /**
     * Test document upload
     */
    public function test_can_upload_document()
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', [
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test document description',
            'document_type' => 'drawing',
            'file' => $file,
            'version' => '1.0',
            'tags' => ['test', 'drawing']
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'document_type',
                        'file_name',
                        'file_size',
                        'version'
                    ]
                ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Test Document',
            'project_id' => $this->project->id,
            'document_type' => 'drawing'
        ]);

        // Assert file was stored
        Storage::disk('local')->assertExists('documents/' . $this->project->id . '/' . $response->json('data.file_name'));
    }

    /**
     * Test document validation
     */
    public function test_document_upload_requires_valid_data()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'document_type', 'file']);
    }

    /**
     * Test file type validation
     */
    public function test_document_upload_validates_file_types()
    {
        $file = UploadedFile::fake()->create('test-file.exe', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', [
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test document description',
            'document_type' => 'drawing',
            'file' => $file
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test file size validation
     */
    public function test_document_upload_validates_file_size()
    {
        $file = UploadedFile::fake()->create('large-file.pdf', 15000); // 15MB

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', [
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test document description',
            'document_type' => 'drawing',
            'file' => $file
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test document download
     */
    public function test_can_download_document()
    {
        $document = ZenaDocument::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'file_path' => 'documents/test-file.pdf'
        ]);

        // Create a fake file
        Storage::disk('local')->put('documents/test-file.pdf', 'fake file content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    /**
     * Test document versioning
     */
    public function test_can_create_document_version()
    {
        $document = ZenaDocument::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id
        ]);

        $file = UploadedFile::fake()->create('updated-document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/documents/{$document->id}/version", [
            'file' => $file,
            'version' => '2.0',
            'change_notes' => 'Updated with new specifications'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'version',
                        'parent_document_id',
                        'change_notes'
                    ]
                ]);

        $this->assertDatabaseHas('documents', [
            'parent_document_id' => $document->id,
            'version' => '2.0',
            'change_notes' => 'Updated with new specifications'
        ]);
    }

    /**
     * Test getting document versions
     */
    public function test_can_get_document_versions()
    {
        $document = ZenaDocument::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id
        ]);

        // Create versions
        ZenaDocument::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'parent_document_id' => $document->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/documents/{$document->id}/versions");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'version',
                            'created_at'
                        ]
                    ]
                ]);

        $versions = $response->json('data');
        $this->assertCount(3, $versions); // Original + 2 versions
    }

    /**
     * Test document update
     */
    public function test_can_update_document()
    {
        $document = ZenaDocument::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id
        ]);

        $updateData = [
            'title' => 'Updated Document Title',
            'description' => 'Updated description',
            'tags' => ['updated', 'tag']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/documents/{$document->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'tags'
                    ]
                ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Updated Document Title'
        ]);
    }

    /**
     * Test document deletion
     */
    public function test_can_delete_document()
    {
        $document = ZenaDocument::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'file_path' => 'documents/test-file.pdf'
        ]);

        // Create a fake file
        Storage::disk('local')->put('documents/test-file.pdf', 'fake file content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/documents/{$document->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id
        ]);

        // Assert file was deleted
        Storage::disk('local')->assertMissing('documents/test-file.pdf');
    }

    /**
     * Test document listing with filters
     */
    public function test_can_filter_documents()
    {
        ZenaDocument::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'document_type' => 'drawing'
        ]);

        ZenaDocument::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'document_type' => 'specification'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/documents?document_type=drawing');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'document_type'
                            ]
                        ]
                    ]
                ]);

        $documents = $response->json('data.data');
        $this->assertCount(3, $documents);
        
        foreach ($documents as $document) {
            $this->assertEquals('drawing', $document['document_type']);
        }
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/zena/documents');
        $response->assertStatus(401);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
