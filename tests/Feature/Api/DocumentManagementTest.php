<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Project;
use App\Models\ZenaProject;
use App\Models\ZenaDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\DocumentManagement\Models\DocumentVersion;
use Tests\Traits\DocumentUploadTestHelper;
use Tests\Traits\RbacTestTrait;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker, RbacTestTrait, DocumentUploadTestHelper;

    protected $user;
    protected $project;
    protected ?string $token = null;
    private array $skipSanctumAuthentication = [
        'test_unauthorized_access_returns_401',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        if (in_array($this->getName(false), $this->skipSanctumAuthentication, true)) {
            $this->user = $this->makeTenantUser();
            $this->token = $this->user->createToken('tests')->plainTextToken;
        } else {
            $context = $this->actingAsWithPermissions([
                'document.create',
                'document.read',
                'document.update',
                'document.delete',
            ]);

            $this->user = $context['user'];
            $this->token = $context['token'];
        }
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $status = match ($this->project->status) {
            'in_progress' => 'active',
            default => $this->project->status,
        };
        $startDate = $this->project->start_date?->format('Y-m-d');
        $endDate = $this->project->end_date?->format('Y-m-d');

        DB::table('zena_projects')->insert([
            'id' => $this->project->id,
            'code' => $this->project->code,
            'name' => $this->project->name,
            'description' => $this->project->description,
            'client_id' => null,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => null,
            'settings' => json_encode($this->project->settings ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->token = $this->user->createToken('tests')->plainTextToken;
        
        Storage::fake('local');
    }

    /**
     * Test document upload
     */
    public function test_can_upload_document()
    {
        $file = $this->fakePdfFile('test-document.pdf');
        $payload = $this->documentUploadPayload($this->project, $file);

        $response = $this->sendDocumentUploadRequest($this->token, $payload);

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
            'name' => 'Test Document',
            'project_id' => $this->project->id,
            'file_type' => 'drawing'
        ]);

        $documentId = $response->json('data.id');
        $latestVersion = DocumentVersion::where('document_id', $documentId)
            ->orderByDesc('version_number')
            ->first();

        $this->assertNotNull($latestVersion);
        Storage::disk('local')->assertExists($latestVersion->file_path);
    }

    public function test_cannot_upload_document_without_permission()
    {
        $context = $this->actingAsWithPermissions(['document.read']);
        $token = $context['sanctum_token'];

        $project = ZenaProject::factory()->create([
            'created_by' => $context['user']->id,
            'tenant_id' => $context['user']->tenant_id,
        ]);

        $file = $this->fakePdfFile('blocked.pdf');
        $payload = $this->documentUploadPayload($project, $file, [
            'title' => 'Blocked Upload',
            'description' => 'Should not be allowed',
        ]);

        putenv('RBAC_BYPASS_TESTING=0');
        $_ENV['RBAC_BYPASS_TESTING'] = '0';

        $response = $this->sendDocumentUploadRequest($token, $payload, '/api/documents');

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error' => [
                        'code',
                        'message'
                    ]
                ]);

        $this->assertEquals(
            'E403.AUTHORIZATION',
            data_get($response->json(), 'error.code')
        );

        $this->assertEquals(
            'You do not have sufficient RBAC assignments to access this resource',
            data_get($response->json(), 'error.message')
        );
    }

    private function sendDocumentUploadRequest(string $token, array $payload, string $route = '/api/zena/documents'): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post($route, $payload);
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
        $uploaded = $this->uploadSampleDocument([
            'title' => 'Download Document',
            'description' => 'Document prepared for download',
            'document_type' => 'drawing',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/documents/{$uploaded['document_id']}/download");

        $response->assertStatus(200);

        Storage::disk('local')->assertExists($uploaded['version']->file_path);
    }

    private function uploadSampleDocument(array $overrides = []): array
    {
        $file = UploadedFile::fake()->create($overrides['file_name'] ?? 'test-document.pdf', 1000);
        $payload = array_merge([
            'project_id' => $this->project->id,
            'title' => 'Sample Document',
            'description' => 'Document created for helper',
            'document_type' => 'drawing',
            'file' => $file,
            'version' => '1.0',
            'tags' => ['test', 'helper'],
        ], $overrides);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', $payload);

        $response->assertStatus(201);

        $documentId = $response->json('data.id');
        $version = DocumentVersion::where('document_id', $documentId)
            ->orderByDesc('version_number')
            ->firstOrFail();

        return [
            'document_id' => $documentId,
            'version' => $version,
            'response' => $response,
        ];
    }

    /**
     * Test document versioning
     */
    public function test_can_create_document_version()
    {
        $uploaded = $this->uploadSampleDocument([
            'title' => 'Versioned Document',
            'description' => 'Document that will receive new versions'
        ]);

        $documentId = $uploaded['document_id'];

        $file = UploadedFile::fake()->create('updated-document.pdf', 1000);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/documents/{$documentId}/version", [
            'file' => $file,
            'version' => '2.0',
            'comment' => 'Updated with new specifications'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'version' => [
                            'id',
                            'version_number',
                            'comment'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documentId,
            'version_number' => 2,
            'comment' => 'Updated with new specifications'
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
            'name' => 'Updated Document Title'
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
            'file_type' => 'drawing'
        ]);

        ZenaDocument::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'file_type' => 'specification'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/documents?document_type=drawing&project_id={$this->project->id}");

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
        $response = $this->getJson("/api/zena/documents?project_id={$this->project->id}");
        $response->assertStatus(401);
    }

}
