<?php

namespace Tests\Feature\Api\Documents;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        
        // Create test project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create test documents
        $this->documents = Document::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
        
        Storage::fake('local');
    }

    /** @test */
    public function documents_api_returns_correct_response_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/v1/documents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'file_name',
                        'original_name',
                        'file_type',
                        'file_size',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ]
            ]);
    }

    /** @test */
    public function documents_api_supports_pagination()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/v1/documents?page=1&per_page=2');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 2,
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /** @test */
    public function documents_api_supports_project_filtering()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', "/api/v1/documents?project_id={$this->project->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(3, $data);
        
        // Verify all returned documents belong to the project
        foreach ($data as $document) {
            $this->assertEquals($this->project->id, $document['project_id']);
        }
    }

    /** @test */
    public function documents_api_supports_search()
    {
        Sanctum::actingAs($this->user);

        // Update one document with specific name
        $this->documents->first()->update(['name' => 'Test Document Search']);

        $response = $this->json('GET', '/api/v1/documents?search=Test Document');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Test Document', $data[0]['name']);
    }

    /** @test */
    public function single_document_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $document = $this->documents->first();

        $response = $this->json('GET', "/api/v1/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'file_name',
                    'original_name',
                    'file_type',
                    'file_size',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    /** @test */
    public function upload_document_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('test-document.pdf', 1000, 'application/pdf');

        $documentData = [
            'file' => $file,
            'name' => 'Test Upload Document',
            'description' => 'Test document description',
            'project_id' => $this->project->id,
        ];

        $response = $this->json('POST', '/api/v1/documents', $documentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'file_name',
                    'original_name',
                    'file_type',
                    'file_size',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('documents', [
            'name' => 'Test Upload Document',
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);
    }

    /** @test */
    public function update_document_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $document = $this->documents->first();

        $updateData = [
            'name' => 'Updated Document Name',
            'description' => 'Updated description',
        ];

        $response = $this->json('PUT', "/api/v1/documents/{$document->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Updated Document Name',
            'description' => 'Updated description',
        ]);
    }

    /** @test */
    public function delete_document_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $document = $this->documents->first();

        $response = $this->json('DELETE', "/api/v1/documents/{$document->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    /** @test */
    public function download_document_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $document = $this->documents->first();

        $response = $this->json('GET', "/api/v1/documents/{$document->id}/download");

        $response->assertStatus(200);
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function documents_api_respects_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);

        // Create documents for other tenant
        Document::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'uploaded_by' => $otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->json('GET', '/api/v1/documents');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(3, $data); // Only our tenant's documents
        
        // Verify all returned documents belong to our tenant
        foreach ($data as $document) {
            $this->assertEquals($this->tenant->id, $document['tenant_id']);
        }
    }

    /** @test */
    public function bulk_delete_documents_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $documentIds = $this->documents->pluck('id')->toArray();

        $response = $this->json('POST', '/api/v1/documents/bulk-delete', [
            'document_ids' => $documentIds
        ]);

        $response->assertStatus(200);

        foreach ($documentIds as $documentId) {
            $this->assertSoftDeleted('documents', [
                'id' => $documentId,
            ]);
        }
    }
}
