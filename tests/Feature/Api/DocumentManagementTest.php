<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.default', 'local');
        Config::set('filesystems.cloud', 'local');

        $this->app->forgetInstance('filesystem');
        $this->app->forgetInstance(FilesystemManager::class);

        $disks = array_unique(array_filter([
            'local',
            'public',
            Config::get('filesystems.default'),
            Config::get('filesystems.cloud'),
            Config::get('documents.disk'),
            Config::get('uploads.disk'),
            Config::get('zena.documents.disk'),
        ]));

        foreach ($disks as $disk) {
            if (!Config::has("filesystems.disks.$disk")) {
                continue;
            }

            Storage::fake($disk);
        }

        $this->forgetCachedStorageServices();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->project = Project::factory()->create([
            'tenant_id' => $this->apiFeatureTenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    private function forgetCachedStorageServices(): void
    {
        $services = [
            \App\Services\FileStorageService::class,
            \App\Services\EnhancedMimeValidationService::class,
            \Src\Foundation\Services\FileStorageService::class,
            \Src\Foundation\Services\EnhancedMimeValidationService::class,
        ];

        foreach ($services as $service) {
            if ($this->app->bound($service)) {
                $this->app->forgetInstance($service);
            }
        }
    }

    /**
     * Test document upload
     */
    public function test_can_upload_document()
    {
        $file = $this->createValidPdfUploadedFile('test-document.pdf');

        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test document description',
            'document_type' => 'drawing',
            'file' => $file,
            'version' => 1,
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
            'name' => 'Test Document',
            'project_id' => $this->project->id,
        ]);
        $this->assertDatabaseHas('documents', [
            'project_id' => $this->project->id,
            'metadata' => json_encode(['document_type' => 'drawing']),
        ]);

        // Assert file was stored
        Storage::disk('local')->assertExists('documents/' . $this->project->id . '/' . $response->json('data.file_name'));
    }

    /**
     * Test document validation
     */
    public function test_document_upload_requires_valid_data()
    {
        $response = $this->apiPost($this->namedRoute('v1.documents.store'), []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'document_type', 'file']);
    }

    /**
     * Test file type validation
     */
    public function test_document_upload_validates_file_types()
    {
        $file = UploadedFile::fake()->create('test-file.exe', 1000, 'application/x-msdownload');

        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
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
        $file = $this->createLargePdfUploadedFile();

        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
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
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'file_path' => 'documents/test-file.pdf',
            'original_name' => 'test-file.pdf'
        ]);

        // Create a fake file
        Storage::disk('local')->put('documents/test-file.pdf', 'fake file content');

        $response = $this->apiGet($this->namedRoute('v1.documents.download', ['id' => $document->id]));

        $response->assertStatus(200);
    }

    /**
     * Test document versioning
     */
    public function test_can_create_document_version()
    {
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'metadata' => ['document_type' => 'drawing']
        ]);
        $document->updateQuietly(['tenant_id' => $this->project->tenant_id]);

        $file = $this->createValidPdfUploadedFile('updated-document.pdf');

        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $file,
            'version' => 2,
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
            'version' => 2,
        ]);
        $this->assertDatabaseHas('documents', [
            'parent_document_id' => $document->id,
            'metadata' => json_encode([
                'document_type' => 'drawing',
                'change_notes' => 'Updated with new specifications'
            ]),
        ]);
    }

    /**
     * Test getting document versions
     */
    public function test_can_get_document_versions()
    {
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        // Create versions
        Document::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'parent_document_id' => $document->id
        ]);

        $response = $this->apiGet($this->namedRoute('v1.documents.versions.index', ['id' => $document->id]));

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
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $updateData = [
            'title' => 'Updated Document Title',
            'description' => 'Updated description',
            'tags' => ['updated', 'tag']
        ];

        $response = $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), $updateData);

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
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'file_path' => 'documents/test-file.pdf'
        ]);

        // Create a fake file
        Storage::disk('local')->put('documents/test-file.pdf', 'fake file content');

        $response = $this->apiDelete($this->namedRoute('v1.documents.destroy', ['id' => $document->id]));

        $response->assertStatus(200);

        $this->assertSoftDeleted('documents', [
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
        Document::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'category' => 'drawing',
            'metadata' => ['document_type' => 'drawing'],
        ]);

        Document::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'category' => 'specification',
            'metadata' => ['document_type' => 'specification'],
        ]);

        $response = $this->apiGet($this->namedRoute('v1.documents.index', query: ['document_type' => 'drawing']));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'document_type'
                        ]
                    ]
                ]);

        $documents = $response->json('data');
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
        $response = $this->getJson($this->namedRoute('v1.documents.index'));
        $response->assertStatus(401);
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

    private function createLargePdfUploadedFile(): UploadedFile
    {
        $minSize = config('app.max_file_size', 10485760) + 2048;
        return $this->createValidPdfUploadedFile('large-file.pdf', $minSize);
    }
}
