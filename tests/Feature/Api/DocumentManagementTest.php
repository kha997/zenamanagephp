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

    public function test_zena_documents_index_can_filter_change_request_linked_documents(): void
    {
        $changeRequestId = '01HZYCRFILTERTARGET0000000001';

        $matchingDocument = $this->createDocument([
            'linked_entity_type' => 'cr',
            'linked_entity_id' => $changeRequestId,
            'title' => 'CR linked document',
        ]);

        $this->createDocument([
            'linked_entity_type' => 'cr',
            'linked_entity_id' => '01HZYCRFILTEROTHER0000000002',
            'title' => 'Other CR document',
        ]);

        $this->createDocument([
            'linked_entity_type' => 'task',
            'linked_entity_id' => $changeRequestId,
            'title' => 'Task linked document',
        ]);

        $this->apiGet($this->zena('documents.index', query: [
            'linked_entity_type' => 'cr',
            'linked_entity_id' => $changeRequestId,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingDocument->id)
            ->assertJsonPath('data.0.linked_entity_type', 'cr')
            ->assertJsonPath('data.0.linked_entity_id', $changeRequestId);
    }

    public function test_canonical_documents_store_and_index_prove_metadata_fields(): void
    {
        $response = $this->apiPostMultipart($this->zena('documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'A2 Architectural Set',
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'review',
            'revision' => 'B',
            'tags' => ['issued', 'coordination'],
            'description' => 'Canonical metadata proof',
            'file' => $this->createValidPdfUploadedFile('a2-architectural.pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'specification')
            ->assertJsonPath('data.discipline', 'architectural')
            ->assertJsonPath('data.package', 'SPEC-A2')
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.revision', 'B')
            ->assertJsonPath('data.metadata.document_type', 'specification')
            ->assertJsonPath('data.metadata.discipline', 'architectural')
            ->assertJsonPath('data.metadata.package', 'SPEC-A2')
            ->assertJsonPath('data.metadata.status', 'review')
            ->assertJsonPath('data.metadata.revision', 'B')
            ->assertJsonPath('data.metadata.tags.0', 'issued');

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'tenant_id' => $this->tenant->id,
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'review',
            'revision' => 'B',
        ]);

        $this->apiGet($this->zena('documents.index', query: [
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'review',
            'revision' => 'B',
            'q' => 'Architectural',
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

    public function test_canonical_update_persists_document_metadata_fields(): void
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

        $this->apiPut($this->zena('documents.update', ['id' => $document->id]), [
            'title' => 'Canonical Spec',
            'discipline' => 'interior',
            'package' => 'SPEC-02',
            'status' => 'approved',
            'revision' => '1',
            'tags' => ['approved'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Canonical Spec')
            ->assertJsonPath('data.discipline', 'interior')
            ->assertJsonPath('data.package', 'SPEC-02')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.revision', '1')
            ->assertJsonPath('data.metadata.discipline', 'interior')
            ->assertJsonPath('data.metadata.package', 'SPEC-02')
            ->assertJsonPath('data.metadata.status', 'approved')
            ->assertJsonPath('data.metadata.revision', '1')
            ->assertJsonPath('data.metadata.tags.0', 'approved');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Canonical Spec',
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

    public function test_canonical_version_history_is_retained_on_zena_document_versions_routes(): void
    {
        $create = $this->apiPostMultipart($this->zena('documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Canonical Panel Layout',
            'document_type' => 'drawing',
            'discipline' => 'electrical',
            'package' => 'ELEC-01',
            'status' => 'draft',
            'revision' => '0',
            'file' => $this->createValidPdfUploadedFile('canonical-panel-layout-v1.pdf'),
        ])->assertCreated();

        $documentId = $create->json('data.id');

        $this->apiPostMultipart($this->zena('documents.versions.store', ['id' => $documentId]), [
            'file' => $this->createValidPdfUploadedFile('canonical-panel-layout-v2.pdf'),
            'version' => 2,
            'document_type' => 'drawing',
            'discipline' => 'electrical',
            'package' => 'ELEC-02',
            'status' => 'review',
            'revision' => '1',
            'change_notes' => 'Canonical feeder routing update',
        ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.package', 'ELEC-02')
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.revision', '1')
            ->assertJsonPath('data.metadata.package', 'ELEC-02')
            ->assertJsonPath('data.metadata.status', 'review')
            ->assertJsonPath('data.metadata.revision', '1')
            ->assertJsonPath('data.metadata.change_notes', 'Canonical feeder routing update');

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
            'comment' => 'Canonical feeder routing update',
        ]);

        $versionsResponse = $this->apiGet($this->zena('documents.versions.index', ['id' => $documentId]));
        $versionsResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.0.comment', 'Canonical feeder routing update')
            ->assertJsonPath('data.1.version_number', 1);

        $this->assertSame(2, DocumentVersion::where('document_id', $documentId)->count());
    }

    public function test_canonical_submit_transitions_document_from_draft_to_submitted(): void
    {
        $document = $this->createDocument([
            'status' => 'draft',
            'metadata' => [
                'status' => 'draft',
            ],
        ]);

        $this->apiPost($this->zena('documents.submit', ['id' => $document->id]), [])
            ->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.metadata.status', 'submitted')
            ->assertJsonPath('data.metadata.submitted_by', $this->user->id);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'submitted',
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_canonical_decision_can_approve_submitted_document(): void
    {
        $approver = $this->createTenantUser($this->tenant, [], ['admin'], [
            'document.view',
            'document.update',
        ]);
        $this->apiAs($approver, $this->tenant);

        $document = $this->createDocument([
            'status' => 'submitted',
            'metadata' => [
                'status' => 'submitted',
                'submitted_by' => $this->user->id,
                'submitted_at' => now()->subMinute()->toISOString(),
            ],
        ]);

        $this->apiPost($this->zena('documents.decision', ['id' => $document->id]), [
            'decision' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.metadata.status', 'approved')
            ->assertJsonPath('data.metadata.decision', 'approved')
            ->assertJsonPath('data.metadata.decision_by', $approver->id);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'approved',
            'updated_by' => $approver->id,
        ]);
    }

    public function test_canonical_decision_can_reject_submitted_document(): void
    {
        $approver = $this->createTenantUser($this->tenant, [], ['admin'], [
            'document.view',
            'document.update',
        ]);
        $this->apiAs($approver, $this->tenant);

        $document = $this->createDocument([
            'status' => 'submitted',
            'metadata' => [
                'status' => 'submitted',
                'submitted_by' => $this->user->id,
            ],
        ]);

        $this->apiPost($this->zena('documents.decision', ['id' => $document->id]), [
            'decision' => 'rejected',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.metadata.decision', 'rejected');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'rejected',
            'updated_by' => $approver->id,
        ]);
    }

    public function test_canonical_workflow_rejects_invalid_transitions(): void
    {
        $approvedDocument = $this->createDocument([
            'status' => 'approved',
            'metadata' => [
                'status' => 'approved',
            ],
        ]);

        $this->apiPost($this->zena('documents.submit', ['id' => $approvedDocument->id]), [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'E409.CONFLICT');

        $submittedDocument = $this->createDocument([
            'status' => 'draft',
            'metadata' => [
                'status' => 'draft',
            ],
        ]);

        $this->user->assignRole('admin');
        $this->apiAs($this->user, $this->tenant);

        $this->apiPost($this->zena('documents.decision', ['id' => $submittedDocument->id]), [
            'decision' => 'approved',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'E409.CONFLICT');
    }

    public function test_canonical_document_workflow_routes_are_tenant_safe(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherApprover = $this->createTenantUser($otherTenant, [], ['pm'], [
            'document.view',
            'document.update',
        ]);
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherApprover->id,
        ]);

        $foreignDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherApprover->id,
            'created_by' => $otherApprover->id,
            'updated_by' => $otherApprover->id,
            'status' => 'submitted',
            'metadata' => [
                'status' => 'submitted',
            ],
        ]);

        $this->apiPost($this->zena('documents.submit', ['id' => $foreignDocument->id]), [])
            ->assertNotFound();

        $this->apiPost($this->zena('documents.decision', ['id' => $foreignDocument->id]), [
            'decision' => 'approved',
        ])->assertNotFound();
    }

    public function test_canonical_document_decision_requires_management_policy_authorization(): void
    {
        $nonApprover = $this->createTenantUser($this->tenant, [], ['engineer'], [
            'document.view',
            'document.update',
        ]);
        $this->apiAs($nonApprover, $this->tenant);

        $document = $this->createDocument([
            'status' => 'submitted',
            'metadata' => [
                'status' => 'submitted',
            ],
        ]);

        $this->apiPost($this->zena('documents.decision', ['id' => $document->id]), [
            'decision' => 'approved',
        ])->assertForbidden();
    }

    public function test_canonical_document_version_routes_are_tenant_safe(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['designer'], [
            'document.view',
            'document.update',
        ]);
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
            'status' => 'draft',
            'version' => 1,
        ]);

        $this->apiGet($this->zena('documents.versions.index', ['id' => $foreignDocument->id]))
            ->assertNotFound();

        $this->apiPostMultipart($this->zena('documents.versions.store', ['id' => $foreignDocument->id]), [
            'file' => $this->createValidPdfUploadedFile('foreign-version.pdf'),
            'version' => 2,
            'revision' => '1',
            'status' => 'review',
        ])->assertNotFound();
    }

    public function test_canonical_document_versions_index_allows_view_access(): void
    {
        $document = $this->createDocument();

        $viewOnlyUser = $this->createTenantUser($this->tenant, [], ['engineer'], [
            'document.view',
        ]);
        $this->apiAs($viewOnlyUser, $this->tenant);

        $this->apiGet($this->zena('documents.versions.index', ['id' => $document->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_canonical_document_versions_store_rejects_missing_update_permission(): void
    {
        $document = $this->createDocument();

        $viewOnlyUser = $this->createTenantUser($this->tenant, [], ['engineer'], [
            'document.view',
        ]);
        $this->apiAs($viewOnlyUser, $this->tenant);

        $this->apiPostMultipart($this->zena('documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('blocked-version.pdf'),
            'version' => 2,
        ])->assertForbidden();
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
            'status' => 'draft',
            'revision' => '0',
            'metadata' => [
                'document_type' => 'drawing',
                'discipline' => 'structural',
                'package' => 'PKG-01',
                'status' => 'draft',
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
