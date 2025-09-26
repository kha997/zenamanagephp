<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Document Versioning và File Management - Simplified Version
 * 
 * Kịch bản: Tạo documents → Test versioning → Test file management
 */
class DocumentVersioningSimpleTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'DOC-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test tạo document đơn giản
     */
    public function test_can_create_simple_document(): void
    {
        // Tạo document đơn giản
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Simple Test Document',
            'original_name' => 'simple-test.pdf',
            'file_path' => 'documents/simple-test.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'simple123hash',
            'category' => 'general',
            'description' => 'Simple test document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Kiểm tra document được tạo thành công
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Simple Test Document',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($this->project->id, $document->project->id);
        $this->assertEquals($this->tenant->id, $document->tenant_id);
        $this->assertEquals($this->user->id, $document->uploaded_by);
    }

    /**
     * Test tạo document version đơn giản
     */
    public function test_can_create_document_version(): void
    {
        // Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Version Test Document',
            'original_name' => 'version-test.pdf',
            'file_path' => 'documents/version-test.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'version123hash',
            'category' => 'general',
            'description' => 'Version test document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Tạo document version
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/version-test-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Initial version',
            'metadata' => [
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'original_name' => 'version-test.pdf'
            ],
            'created_by' => $this->user->id,
        ]);

        // Kiểm tra version được tạo thành công
        $this->assertDatabaseHas('document_versions', [
            'id' => $version->id,
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/version-test-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($document->id, $version->document->id);
        $this->assertEquals($this->user->id, $version->creator->id);
    }

    /**
     * Test document versioning với multiple versions
     */
    public function test_can_create_multiple_document_versions(): void
    {
        // Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Multi Version Document',
            'original_name' => 'multi-version.pdf',
            'file_path' => 'documents/multi-version.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'multi123hash',
            'category' => 'general',
            'description' => 'Multi version document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Tạo version 1
        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/multi-version-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Version 1',
            'created_by' => $this->user->id,
        ]);

        // Tạo version 2
        $version2 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/multi-version-v2.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Version 2',
            'created_by' => $this->user->id,
        ]);

        // Tạo version 3
        $version3 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/multi-version-v3.pdf',
            'storage_driver' => DocumentVersion::STORAGE_S3,
            'comment' => 'Version 3 - S3',
            'created_by' => $this->user->id,
        ]);

        // Kiểm tra tất cả versions được tạo
        $this->assertDatabaseHas('document_versions', [
            'id' => $version1->id,
            'version_number' => 1,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'id' => $version2->id,
            'version_number' => 2,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'id' => $version3->id,
            'version_number' => 3,
        ]);

        // Kiểm tra document có 3 versions
        $this->assertCount(3, $document->versions);

        // Kiểm tra version order (latest first)
        $versions = $document->versions;
        $this->assertEquals(3, $versions[0]->version_number);
        $this->assertEquals(2, $versions[1]->version_number);
        $this->assertEquals(1, $versions[2]->version_number);

        // Kiểm tra storage drivers
        $localVersions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_LOCAL)->get();
        $s3Versions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_S3)->get();

        $this->assertCount(2, $localVersions);
        $this->assertCount(1, $s3Versions);
    }

    /**
     * Test document versioning với different storage drivers
     */
    public function test_can_use_different_storage_drivers(): void
    {
        // Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Storage Test Document',
            'original_name' => 'storage-test.pdf',
            'file_path' => 'documents/storage-test.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'storage123hash',
            'category' => 'general',
            'description' => 'Storage test document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Test local storage
        $localVersion = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/local-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Local storage',
            'created_by' => $this->user->id,
        ]);

        // Test S3 storage
        $s3Version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/s3-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_S3,
            'comment' => 'S3 storage',
            'created_by' => $this->user->id,
        ]);

        // Test Google Drive storage
        $gdriveVersion = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/gdrive-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_GDRIVE,
            'comment' => 'Google Drive storage',
            'created_by' => $this->user->id,
        ]);

        // Kiểm tra tất cả storage drivers
        $this->assertDatabaseHas('document_versions', [
            'id' => $localVersion->id,
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'id' => $s3Version->id,
            'storage_driver' => DocumentVersion::STORAGE_S3,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'id' => $gdriveVersion->id,
            'storage_driver' => DocumentVersion::STORAGE_GDRIVE,
        ]);

        // Test scopes
        $localVersions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_LOCAL)->get();
        $s3Versions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_S3)->get();
        $gdriveVersions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_GDRIVE)->get();

        $this->assertCount(1, $localVersions);
        $this->assertCount(1, $s3Versions);
        $this->assertCount(1, $gdriveVersions);
    }

    /**
     * Test document versioning workflow end-to-end
     */
    public function test_document_versioning_workflow_end_to_end(): void
    {
        // 1. Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'E2E Test Document',
            'original_name' => 'e2e-test.pdf',
            'file_path' => 'documents/e2e-test.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'e2e123hash',
            'category' => 'general',
            'description' => 'E2E test document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // 2. Tạo version 1
        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/e2e-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Initial version',
            'metadata' => ['file_size' => 1024, 'mime_type' => 'application/pdf'],
            'created_by' => $this->user->id,
        ]);

        // 3. Tạo version 2
        $version2 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/e2e-v2.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Updated version',
            'metadata' => ['file_size' => 2048, 'mime_type' => 'application/pdf'],
            'created_by' => $this->user->id,
        ]);

        // 4. Tạo version 3 với S3
        $version3 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/e2e-v3.pdf',
            'storage_driver' => DocumentVersion::STORAGE_S3,
            'comment' => 'S3 version',
            'metadata' => ['file_size' => 4096, 'mime_type' => 'application/pdf'],
            'created_by' => $this->user->id,
        ]);

        // 5. Test complete workflow
        $this->assertCount(3, $document->versions);
        $this->assertTrue($document->hasVersions());

        // Kiểm tra version order
        $versions = $document->versions;
        $this->assertEquals(3, $versions[0]->version_number);
        $this->assertEquals(2, $versions[1]->version_number);
        $this->assertEquals(1, $versions[2]->version_number);

        // Kiểm tra relationships
        $this->assertEquals($document->id, $version1->document->id);
        $this->assertEquals($document->id, $version2->document->id);
        $this->assertEquals($document->id, $version3->document->id);
        $this->assertEquals($this->user->id, $version1->creator->id);
        $this->assertEquals($this->user->id, $version2->creator->id);
        $this->assertEquals($this->user->id, $version3->creator->id);

        // Kiểm tra metadata
        $this->assertNotNull($version1->metadata);
        $this->assertEquals(1024, $version1->metadata['file_size']);
        $this->assertEquals('application/pdf', $version1->metadata['mime_type']);

        // Kiểm tra storage drivers
        $localVersions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_LOCAL)->get();
        $s3Versions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_S3)->get();

        $this->assertCount(2, $localVersions);
        $this->assertCount(1, $s3Versions);
    }
}
