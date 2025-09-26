<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Document;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Document Versioning (Simplified Debug)
 */
class DocumentVersioningDebugTest extends TestCase
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
        // Verify entities exist
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);

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

        // Test relationships
        $this->assertEquals($this->project->id, $document->project_id);
        $this->assertEquals($this->tenant->id, $document->tenant_id);
        $this->assertEquals($this->user->id, $document->uploaded_by);
    }

    /**
     * Test document relationships
     */
    public function test_document_relationships(): void
    {
        $document = Document::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Relationship Test Document',
            'original_name' => 'relationship.pdf',
            'file_path' => 'documents/relationship.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => 'hash456',
            'category' => 'test',
            'description' => 'Test relationships',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
        ]);

        // Test project relationship
        $this->assertEquals($this->project->id, $document->project->id);
        $this->assertEquals($this->project->name, $document->project->name);

        // Test tenant relationship
        $this->assertEquals($this->tenant->id, $document->tenant->id);
        $this->assertEquals($this->tenant->name, $document->tenant->name);

        // Test uploader relationship
        $this->assertEquals($this->user->id, $document->uploader->id);
        $this->assertEquals($this->user->name, $document->uploader->name);
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
    }
}
