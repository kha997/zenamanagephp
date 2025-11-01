<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Document;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Document Versioning (No Foreign Keys)
 */
class DocumentVersioningNoFKTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
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
        // Tạo document với minimal data
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Simple Document',
            'original_name' => 'simple.pdf',
            'file_path' => 'documents/simple.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'hash123',
            'category' => 'test',
            'description' => 'Simple test document',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Verify document created
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'Simple Document',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
        ]);

        // Test basic properties
        $this->assertEquals('Simple Document', $document->name);
        $this->assertEquals('simple.pdf', $document->original_name);
        $this->assertEquals('documents/simple.pdf', $document->file_path);
        $this->assertEquals('pdf', $document->file_type);
        $this->assertEquals('application/pdf', $document->mime_type);
        $this->assertEquals(1024, $document->file_size);
        $this->assertEquals('hash123', $document->file_hash);
        $this->assertEquals('test', $document->category);
        $this->assertEquals('Simple test document', $document->description);
        $this->assertEquals('active', $document->status);
        $this->assertEquals(1, $document->version);
        $this->assertTrue($document->is_current_version);
    }

    /**
     * Test document versioning
     */
    public function test_document_versioning(): void
    {
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Version Test Document',
            'original_name' => 'version.pdf',
            'file_path' => 'documents/version.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'hash789',
            'category' => 'test',
            'description' => 'Test versioning',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Update document to version 2
        $document->update([
            'version' => 2,
            'file_path' => 'documents/version-v2.pdf',
            'file_hash' => 'hash789v2',
            'description' => 'Updated version',
        ]);

        // Verify update
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'version' => 2,
            'file_path' => 'documents/version-v2.pdf',
            'file_hash' => 'hash789v2',
        ]);

        // Test version increment
        $this->assertEquals(2, $document->version);
        $this->assertEquals('documents/version-v2.pdf', $document->file_path);
        $this->assertEquals('hash789v2', $document->file_hash);
        $this->assertEquals('Updated version', $document->description);
    }

    /**
     * Test document metadata
     */
    public function test_document_metadata(): void
    {
        $metadata = [
            'tags' => ['test', 'document', 'metadata'],
            'author' => 'Test Author',
            'department' => 'IT',
            'created_by_system' => true,
        ];

        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Metadata Test Document',
            'original_name' => 'metadata.pdf',
            'file_path' => 'documents/metadata.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'hashmetadata',
            'category' => 'test',
            'description' => 'Test metadata',
            'metadata' => $metadata,
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Verify metadata
        $this->assertEquals($metadata, $document->metadata);
        $this->assertIsArray($document->metadata);
        $this->assertArrayHasKey('tags', $document->metadata);
        $this->assertArrayHasKey('author', $document->metadata);
        $this->assertContains('test', $document->metadata['tags']);
        $this->assertEquals('Test Author', $document->metadata['author']);
        $this->assertEquals('IT', $document->metadata['department']);
        $this->assertTrue($document->metadata['created_by_system']);
    }

    /**
     * Test document status changes
     */
    public function test_document_status_changes(): void
    {
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Status Test Document',
            'original_name' => 'status.pdf',
            'file_path' => 'documents/status.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'hashstatus',
            'category' => 'test',
            'description' => 'Test status changes',
            'status' => 'draft',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Test initial status
        $this->assertEquals('draft', $document->status);

        // Change to active
        $document->update(['status' => 'active']);
        $this->assertEquals('active', $document->status);

        // Change to archived
        $document->update(['status' => 'archived']);
        $this->assertEquals('archived', $document->status);

        // Verify database
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'archived',
        ]);
    }

    /**
     * Test document categories
     */
    public function test_document_categories(): void
    {
        $categories = ['technical', 'legal', 'financial', 'design', 'contract'];

        foreach ($categories as $category) {
            $document = Document::create([
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
                'uploaded_by' => $this->user->id,
                'name' => "{$category} Document",
                'original_name' => "{$category}.pdf",
                'file_path' => "documents/{$category}.pdf",
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'file_hash' => "hash{$category}",
                'category' => $category,
                'description' => "Test {$category} document",
                'status' => 'active',
                'version' => 1,
                'is_current_version' => true,
            ]);

            $this->assertEquals($category, $document->category);
            $this->assertDatabaseHas('documents', [
                'id' => $document->id,
                'category' => $category,
            ]);
        }
    }

    /**
     * Test document file types
     */
    public function test_document_file_types(): void
    {
        $fileTypes = [
            ['type' => 'pdf', 'mime' => 'application/pdf'],
            ['type' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['type' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['type' => 'jpg', 'mime' => 'image/jpeg'],
            ['type' => 'png', 'mime' => 'image/png'],
        ];

        foreach ($fileTypes as $fileType) {
            $document = Document::create([
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
                'uploaded_by' => $this->user->id,
                'name' => "Test {$fileType['type']} Document",
                'original_name' => "test.{$fileType['type']}",
                'file_path' => "documents/test.{$fileType['type']}",
                'file_type' => $fileType['type'],
                'mime_type' => $fileType['mime'],
                'file_size' => 1024,
                'file_hash' => "hash{$fileType['type']}",
                'category' => 'test',
                'description' => "Test {$fileType['type']} file",
                'status' => 'active',
                'version' => 1,
                'is_current_version' => true,
            ]);

            $this->assertEquals($fileType['type'], $document->file_type);
            $this->assertEquals($fileType['mime'], $document->mime_type);
        }
    }

    /**
     * Test document bulk operations
     */
    public function test_document_bulk_operations(): void
    {
        $documents = [];
        
        // Create multiple documents individually (bulk insert doesn't work with ULID)
        for ($i = 1; $i <= 5; $i++) {
            Document::create([
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
                'uploaded_by' => $this->user->id,
                'name' => "Bulk Document {$i}",
                'original_name' => "bulk{$i}.pdf",
                'file_path' => "documents/bulk{$i}.pdf",
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1024 * $i,
                'file_hash' => "hashbulk{$i}",
                'category' => 'bulk',
                'description' => "Bulk document {$i}",
                'status' => 'active',
                'version' => 1,
                'is_current_version' => true,
            ]);
        }

        // Verify all documents created
        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseHas('documents', [
                'name' => "Bulk Document {$i}",
                'original_name' => "bulk{$i}.pdf",
                'file_size' => 1024 * $i,
                'file_hash' => "hashbulk{$i}",
                'category' => 'bulk',
            ]);
        }

        // Count documents
        $bulkDocuments = Document::where('category', 'bulk')->get();
        $this->assertCount(5, $bulkDocuments);
    }
}
