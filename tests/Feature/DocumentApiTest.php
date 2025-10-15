<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $project;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->createRolesAndPermissions();
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        $permissions = [
            'document.create',
            'document.read',
            'document.update',
            'document.delete',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'document',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test upload document
     */
    public function test_can_upload_document()
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/documents', [
            'name' => 'Test Document',
            'project_id' => $this->project->id,
            'file' => $file,
            'description' => 'Initial upload'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'document' => [
                             'id',
                             'title',
                             'project_id',
                             'current_version_id',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('documents', [
            'name' => 'Test Document',
            'project_id' => $this->project->id
        ]);

        $this->assertDatabaseHas('document_versions', [
            'version_number' => 1,
            'comment' => 'Initial version'
        ]);
    }

    /**
     * Test get all documents
     */
    public function test_can_get_all_documents()
    {
        Document::factory()->count(3)->create([
            'project_id' => $this->project->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/documents');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'documents' => [
                             '*' => [
                                 'id',
                                 'title',
                                 'project_id',
                                 'current_version_id',
                                 'created_at',
                                 'updated_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test upload new version
     */
    public function test_can_upload_new_version()
    {
        // First create a document via API
        $file1 = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');
        $uploadResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/documents', [
            'name' => 'Test Document',
            'project_id' => $this->project->id,
            'file' => $file1,
            'description' => 'Initial upload'
        ]);
        
        $documentId = $uploadResponse->json('data.document.id');

        $file = UploadedFile::fake()->create('test-document-v2.pdf', 1024, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/documents/{$documentId}/versions", [
            'file' => $file,
            'change_description' => 'Updated version'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'version' => [
                             'id',
                             'document_id',
                             'version_number',
                             'file_path',
                             'comment',
                             'created_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documentId,
            'version_number' => 2,
            'comment' => 'Updated version'
        ]);
    }

    /**
     * Test revert to previous version
     */
    public function test_can_revert_to_previous_version()
    {
        $document = Document::factory()->create([
            'project_id' => $this->project->id
        ]);

        // Tạo version 1
        $version1 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1
        ]);

        // Tạo version 2
        $version2 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/documents/{$document->id}/revert", [
            'version_number' => 1
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Đã khôi phục về phiên bản 1'
                     ]
                 ]);

        // Kiểm tra version 3 được tạo với reverted_from_version_number = 1
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 3,
            'reverted_from_version_number' => 1
        ]);
    }

    /**
     * Test download document
     */
    public function test_can_download_document()
    {
        $document = Document::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'file_path' => 'documents/1/test-file.pdf'
        ]);

        // Tạo thư mục và file giả trong storage
        $filePath = storage_path('app/documents/1/test-file.pdf');
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, 'fake file content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get("/api/v1/documents/{$document->id}/download");

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test validation errors
     */
    public function test_upload_document_validation_errors()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/documents', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'file']);
    }
}