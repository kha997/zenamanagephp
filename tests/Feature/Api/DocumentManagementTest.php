<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class DocumentManagementTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;
    use RouteNameTrait;

    protected Tenant $tenant;
    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.default', 'local');
        Config::set('filesystems.cloud', 'local');

        $this->app->forgetInstance('filesystem');
        $this->app->forgetInstance(FilesystemManager::class);

        foreach (['local', 'public'] as $disk) {
            Storage::fake($disk);
        }

        $this->forgetCachedStorageServices();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['designer'], [
            'document.view',
            'document.create',
            'document.update',
            'document.delete',
        ]);
        $this->apiAs($this->user, $this->tenant);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_create_list_and_search_document_metadata(): void
    {
        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'A1 Structural Set',
            'document_type' => 'drawing',
            'discipline' => 'structural',
            'package' => 'PKG-A1',
            'status' => 'review',
            'revision' => 'A',
            'tags' => ['ifc', 'steel'],
            'description' => 'Issued for coordination',
            'file' => $this->createValidPdfUploadedFile('a1-structural.pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'drawing')
            ->assertJsonPath('data.discipline', 'structural')
            ->assertJsonPath('data.package', 'PKG-A1')
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.revision', 'A');

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'tenant_id' => $this->tenant->id,
            'document_type' => 'drawing',
            'discipline' => 'structural',
            'package' => 'PKG-A1',
            'status' => 'review',
            'revision' => 'A',
        ]);

        $this->apiGet($this->namedRoute('v1.documents.index', query: [
            'discipline' => 'structural',
            'package' => 'PKG-A1',
            'status' => 'review',
            'revision' => 'A',
            'q' => 'Structural',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $documentId);
    }

    public function test_can_update_document_metadata_fields(): void
    {
        $document = $this->createDocument([
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-01',
            'status' => 'draft',
            'revision' => '0',
            'metadata' => [
                'document_type' => 'specification',
                'discipline' => 'architectural',
                'package' => 'SPEC-01',
                'status' => 'draft',
                'revision' => '0',
            ],
        ]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'title' => 'Updated Spec',
            'discipline' => 'interior',
            'package' => 'SPEC-02',
            'status' => 'approved',
            'revision' => '1',
            'tags' => ['approved'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Spec')
            ->assertJsonPath('data.discipline', 'interior')
            ->assertJsonPath('data.package', 'SPEC-02')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.revision', '1');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Updated Spec',
            'discipline' => 'interior',
            'package' => 'SPEC-02',
            'status' => 'approved',
            'revision' => '1',
        ]);
    }

    public function test_version_history_is_retained_in_document_versions(): void
    {
        $create = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Panel Layout',
            'document_type' => 'drawing',
            'discipline' => 'electrical',
            'package' => 'ELEC-01',
            'status' => 'draft',
            'revision' => '0',
            'file' => $this->createValidPdfUploadedFile('panel-layout-v1.pdf'),
        ])->assertCreated();

        $documentId = $create->json('data.id');

        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $documentId]), [
            'file' => $this->createValidPdfUploadedFile('panel-layout-v2.pdf'),
            'version' => 2,
            'revision' => '1',
            'status' => 'review',
            'change_notes' => 'Added updated feeder routing',
        ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.revision', '1');

        $document = Document::findOrFail($documentId);

        $this->assertSame(2, (int) $document->version);
        $this->assertNotNull($document->current_version_id);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documentId,
            'version_number' => 1,
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documentId,
            'version_number' => 2,
            'comment' => 'Added updated feeder routing',
        ]);

        $versionsResponse = $this->apiGet($this->namedRoute('v1.documents.versions.index', ['id' => $documentId]));
        $versionsResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.1.version_number', 1);

        $this->assertSame(2, DocumentVersion::where('document_id', $documentId)->count());
    }

    public function test_cross_tenant_document_requests_return_not_found(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['designer'], ['document.view']);
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        $foreignDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherUser->id,
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $this->apiGet($this->namedRoute('v1.documents.show', ['id' => $foreignDocument->id]))
            ->assertNotFound();
    }

    public function test_rbac_denies_document_creation_without_permission(): void
    {
        $limitedUser = $this->createTenantUser($this->tenant, [], ['viewer'], ['document.view']);
        $this->apiAs($limitedUser, $this->tenant);

        $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Blocked Upload',
            'document_type' => 'drawing',
            'file' => $this->createValidPdfUploadedFile('blocked.pdf'),
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->flushHeaders()
            ->withHeaders($this->tenantHeaders())
            ->getJson($this->namedRoute('v1.documents.index'))
            ->assertUnauthorized();
    }

    private function createDocument(array $overrides = []): Document
    {
        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'document_type' => 'drawing',
            'discipline' => 'structural',
            'package' => 'PKG-01',
            'status' => 'active',
            'revision' => '0',
            'metadata' => [
                'document_type' => 'drawing',
                'discipline' => 'structural',
                'package' => 'PKG-01',
                'status' => 'active',
                'revision' => '0',
            ],
        ], $overrides));
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
}
