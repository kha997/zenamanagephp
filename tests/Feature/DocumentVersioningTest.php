<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Test Document Versioning và File Management
 * 
 * Kịch bản: Tạo documents → Upload files → Test versioning → Test file management
 */
class DocumentVersioningTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $task;
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

        // Tạo task
        $this->task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Task Description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        Storage::fake('public');
    }

    /**
     * Test tạo document với version đầu tiên
     */
    public function test_can_create_document_with_initial_version(): void
    {
        // Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Test Document',
            'original_name' => 'test-document.pdf',
            'file_path' => 'documents/test-document.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'abc123def456',
            'category' => 'technical',
            'description' => 'Test Document Description',
            'metadata' => ['tags' => ['test', 'document']],
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Tạo version đầu tiên
        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/test-document-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Initial version',
            'metadata' => [
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'original_name' => 'test-document.pdf'
            ],
            'created_by' => $this->user->id,
        ]);

        // Kiểm tra document được tạo thành công
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Test Document',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Kiểm tra version được tạo thành công
        $this->assertDatabaseHas('document_versions', [
            'id' => $version1->id,
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/test-document-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($document->id, $version1->document->id);
        $this->assertEquals($this->user->id, $version1->creator->id);
        $this->assertEquals($this->project->id, $document->project->id);
    }

    /**
     * Test tạo version mới cho document
     */
    public function test_can_create_new_version_for_document(): void
    {
        // Tạo document với version đầu tiên
        $document = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test Document Description',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/test-document-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Initial version',
            'created_by' => $this->user->id,
        ]);

        $document->update(['current_version_id' => $version1->id]);

        // Tạo version mới
        $version2 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/test-document-v2.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Updated version with changes',
            'metadata' => [
                'file_size' => 2048,
                'mime_type' => 'application/pdf',
                'original_name' => 'test-document-v2.pdf'
            ],
            'created_by' => $this->user->id,
        ]);

        // Cập nhật document với version mới
        $document->update(['current_version_id' => $version2->id]);

        // Kiểm tra version mới được tạo
        $this->assertDatabaseHas('document_versions', [
            'id' => $version2->id,
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/test-document-v2.pdf',
        ]);

        // Kiểm tra document có 2 versions
        $this->assertCount(2, $document->versions);
        $this->assertEquals(2, $document->getCurrentVersionNumber());
        $this->assertTrue($document->hasVersions());

        // Kiểm tra current version là version 2
        $this->assertEquals($version2->id, $document->currentVersion->id);
        $this->assertEquals(2, $document->currentVersion->version_number);
    }

    /**
     * Test revert về version cũ
     */
    public function test_can_revert_to_previous_version(): void
    {
        // Tạo document với 3 versions
        $document = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test Document Description',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/test-document-v1.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Initial version',
            'created_by' => $this->user->id,
        ]);

        $version2 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/test-document-v2.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Second version',
            'created_by' => $this->user->id,
        ]);

        $version3 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/test-document-v3.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Third version',
            'created_by' => $this->user->id,
        ]);

        $document->update(['current_version_id' => $version3->id]);

        // Revert về version 1
        $revertedVersion = $document->revertToVersion(1, $this->user->id, 'Reverted to version 1');

        // Kiểm tra revert version được tạo
        $this->assertNotNull($revertedVersion);
        $this->assertDatabaseHas('document_versions', [
            'id' => $revertedVersion->id,
            'document_id' => $document->id,
            'version_number' => 4, // Next version number
            'file_path' => 'documents/test-document-v1.pdf', // Same as version 1
            'reverted_from_version_number' => 1,
            'comment' => 'Reverted to version 1',
        ]);

        // Kiểm tra document có 4 versions
        $this->assertCount(4, $document->versions);
        $this->assertEquals(4, $document->getCurrentVersionNumber());

        // Kiểm tra current version là reverted version
        $this->assertEquals($revertedVersion->id, $document->currentVersion->id);
    }

    /**
     * Test file upload với different storage drivers
     */
    public function test_can_upload_files_with_different_storage_drivers(): void
    {
        // Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test Document Description',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        // Test local storage
        $localVersion = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/local-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => 'Local storage version',
            'created_by' => $this->user->id,
        ]);

        // Test S3 storage
        $s3Version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/s3-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_S3,
            'comment' => 'S3 storage version',
            'created_by' => $this->user->id,
        ]);

        // Test Google Drive storage
        $gdriveVersion = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/gdrive-file.pdf',
            'storage_driver' => DocumentVersion::STORAGE_GDRIVE,
            'comment' => 'Google Drive storage version',
            'created_by' => $this->user->id,
        ]);

        // Kiểm tra tất cả versions được tạo với different storage drivers
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
     * Test document visibility và client approval
     */
    public function test_document_visibility_and_client_approval(): void
    {
        // Tạo internal document
        $internalDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Internal Document',
            'description' => 'Internal Document Description',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'client_approved' => false,
            'created_by' => $this->user->id,
        ]);

        // Tạo client document (chưa approved)
        $clientDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Client Document',
            'description' => 'Client Document Description',
            'visibility' => Document::VISIBILITY_CLIENT,
            'client_approved' => false,
            'created_by' => $this->user->id,
        ]);

        // Tạo client document (đã approved)
        $approvedClientDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Approved Client Document',
            'description' => 'Approved Client Document Description',
            'visibility' => Document::VISIBILITY_CLIENT,
            'client_approved' => true,
            'created_by' => $this->user->id,
        ]);

        // Test visibility scopes
        $internalDocuments = Document::withVisibility(Document::VISIBILITY_INTERNAL)->get();
        $clientDocuments = Document::withVisibility(Document::VISIBILITY_CLIENT)->get();
        $approvedClientDocuments = Document::clientApproved()->get();

        $this->assertCount(1, $internalDocuments);
        $this->assertTrue($internalDocuments->contains($internalDocument));

        $this->assertCount(2, $clientDocuments);
        $this->assertTrue($clientDocuments->contains($clientDocument));
        $this->assertTrue($clientDocuments->contains($approvedClientDocument));

        $this->assertCount(1, $approvedClientDocuments);
        $this->assertTrue($approvedClientDocuments->contains($approvedClientDocument));

        // Test visibility methods
        $this->assertFalse($internalDocument->isVisibleToClient());
        $this->assertFalse($clientDocument->isVisibleToClient());
        $this->assertTrue($approvedClientDocument->isVisibleToClient());
    }

    /**
     * Test document linking với different entities
     */
    public function test_document_linking_with_different_entities(): void
    {
        // Tạo document linked to task
        $taskDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Task Document',
            'description' => 'Document linked to task',
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $this->task->id,
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        // Tạo document linked to diary
        $diaryDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'Diary Document',
            'description' => 'Document linked to diary',
            'linked_entity_type' => Document::ENTITY_TYPE_DIARY,
            'linked_entity_id' => 'diary-123',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        // Tạo document linked to CR
        $crDocument = Document::create([
            'project_id' => $this->project->id,
            'title' => 'CR Document',
            'description' => 'Document linked to change request',
            'linked_entity_type' => Document::ENTITY_TYPE_CR,
            'linked_entity_id' => 'cr-456',
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
        ]);

        // Test entity type scopes
        $taskDocuments = Document::forEntityType(Document::ENTITY_TYPE_TASK)->get();
        $diaryDocuments = Document::forEntityType(Document::ENTITY_TYPE_DIARY)->get();
        $crDocuments = Document::forEntityType(Document::ENTITY_TYPE_CR)->get();

        $this->assertCount(1, $taskDocuments);
        $this->assertTrue($taskDocuments->contains($taskDocument));

        $this->assertCount(1, $diaryDocuments);
        $this->assertTrue($diaryDocuments->contains($diaryDocument));

        $this->assertCount(1, $crDocuments);
        $this->assertTrue($crDocuments->contains($crDocument));

        // Test specific entity scope
        $specificTaskDocuments = Document::forEntity(Document::ENTITY_TYPE_TASK, $this->task->id)->get();
        $this->assertCount(1, $specificTaskDocuments);
        $this->assertTrue($specificTaskDocuments->contains($taskDocument));
    }

    /**
     * Test document versioning workflow end-to-end
     */
    public function test_document_versioning_workflow_end_to_end(): void
    {
        // 1. Tạo document
        $document = Document::create([
            'project_id' => $this->project->id,
            'title' => 'E2E Test Document',
            'description' => 'End-to-end test document',
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $this->task->id,
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $this->user->id,
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

        $document->update(['current_version_id' => $version1->id]);

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

        $document->update(['current_version_id' => $version2->id]);

        // 4. Tạo version 3
        $version3 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/e2e-v3.pdf',
            'storage_driver' => DocumentVersion::STORAGE_S3,
            'comment' => 'S3 version',
            'metadata' => ['file_size' => 4096, 'mime_type' => 'application/pdf'],
            'created_by' => $this->user->id,
        ]);

        $document->update(['current_version_id' => $version3->id]);

        // 5. Revert về version 1
        $revertedVersion = $document->revertToVersion(1, $this->user->id, 'Reverted to initial version');

        // 6. Test complete workflow
        $this->assertCount(4, $document->versions);
        $this->assertEquals(4, $document->getCurrentVersionNumber());
        $this->assertTrue($document->hasVersions());

        // Kiểm tra version order
        $versions = $document->versions;
        $this->assertEquals(4, $versions[0]->version_number); // Latest first
        $this->assertEquals(3, $versions[1]->version_number);
        $this->assertEquals(2, $versions[2]->version_number);
        $this->assertEquals(1, $versions[3]->version_number);

        // Kiểm tra reverted version
        $this->assertNotNull($revertedVersion->reverted_from_version_number);
        $this->assertEquals(1, $revertedVersion->reverted_from_version_number);

        // Kiểm tra relationships
        $this->assertEquals($document->id, $revertedVersion->document->id);
        $this->assertEquals($this->user->id, $revertedVersion->creator->id);
        $this->assertEquals($this->project->id, $document->project->id);

        // Kiểm tra metadata
        $this->assertNotNull($version1->metadata);
        $this->assertEquals(1024, $version1->metadata['file_size']);
        $this->assertEquals('application/pdf', $version1->metadata['mime_type']);
    }

    /**
     * Test document versioning với bulk operations
     */
    public function test_document_versioning_bulk_operations(): void
    {
        // Tạo multiple documents
        $documents = [];
        for ($i = 1; $i <= 3; $i++) {
            $documents[] = Document::create([
                'project_id' => $this->project->id,
                'title' => "Bulk Document {$i}",
                'description' => "Bulk document {$i} description",
                'visibility' => Document::VISIBILITY_INTERNAL,
                'created_by' => $this->user->id,
            ]);
        }

        // Tạo multiple versions cho mỗi document
        foreach ($documents as $index => $document) {
            for ($v = 1; $v <= 2; $v++) {
                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => $v,
                    'file_path' => "documents/bulk-doc-{$index}-v{$v}.pdf",
                    'storage_driver' => DocumentVersion::STORAGE_LOCAL,
                    'comment' => "Version {$v} of document {$index}",
                    'created_by' => $this->user->id,
                ]);

                if ($v === 2) {
                    $document->update(['current_version_id' => $version->id]);
                }
            }
        }

        // Test bulk queries
        $allDocuments = Document::where('project_id', $this->project->id)->get();
        $allVersions = DocumentVersion::whereIn('document_id', $allDocuments->pluck('id'))->get();

        $this->assertCount(3, $allDocuments);
        $this->assertCount(6, $allVersions); // 3 documents × 2 versions each

        // Test bulk updates
        DocumentVersion::whereIn('document_id', $allDocuments->pluck('id'))
            ->update(['storage_driver' => DocumentVersion::STORAGE_S3]);

        $s3Versions = DocumentVersion::withStorageDriver(DocumentVersion::STORAGE_S3)->get();
        $this->assertCount(6, $s3Versions);

        // Test bulk deletes (soft delete)
        $documents[0]->delete();
        $this->assertSoftDeleted('documents', ['id' => $documents[0]->id]);

        // Versions should still exist (cascade delete not implemented)
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documents[0]->id,
        ]);
    }
}
