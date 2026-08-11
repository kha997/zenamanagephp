<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\Component;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Enums\DocumentWorkflowStatus;
use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
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
            'status' => 'draft',
            'revision' => 'A',
            'tags' => ['ifc', 'steel'],
            'description' => 'Issued for coordination',
            'file' => $this->createValidPdfUploadedFile('a1-structural.pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'drawing')
            ->assertJsonPath('data.discipline', 'structural')
            ->assertJsonPath('data.package', 'PKG-A1')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revision', 'A');

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'tenant_id' => $this->tenant->id,
            'document_type' => 'drawing',
            'discipline' => 'structural',
            'package' => 'PKG-A1',
            'status' => 'draft',
            'revision' => 'A',
        ]);

        $this->apiGet($this->namedRoute('v1.documents.index', query: [
            'discipline' => 'structural',
            'package' => 'PKG-A1',
            'status' => 'draft',
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

    public function test_canonical_document_can_attach_to_task_on_document_owner_path(): void
    {
        $document = $this->createDocument();
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->apiPost($this->zena('documents.link.attach', ['id' => $document->id]), [
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $task->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.linked_entity_type', Document::ENTITY_TYPE_TASK)
            ->assertJsonPath('data.linked_entity_id', $task->id);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $task->id,
        ]);

        $this->apiGet($this->zena('documents.index', query: [
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $task->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id);
    }

    public function test_canonical_document_can_attach_to_component_on_document_owner_path(): void
    {
        $document = $this->createDocument();
        $component = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);
        $componentId = (string) $component->id;

        $this->apiPost($this->zena('documents.link.attach', ['id' => $document->id]), [
            'linked_entity_type' => Document::ENTITY_TYPE_COMPONENT,
            'linked_entity_id' => $componentId,
        ])
            ->assertOk()
            ->assertJsonPath('data.linked_entity_type', Document::ENTITY_TYPE_COMPONENT)
            ->assertJsonPath('data.linked_entity_id', $componentId);

        $this->apiGet($this->zena('documents.index', query: [
            'linked_entity_type' => Document::ENTITY_TYPE_COMPONENT,
            'linked_entity_id' => $componentId,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id);
    }

    public function test_canonical_document_can_attach_to_change_request_on_document_owner_path(): void
    {
        $document = $this->createDocument();
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => ChangeRequest::STATUS_DRAFT,
        ]);

        $this->apiPost($this->zena('documents.link.attach', ['id' => $document->id]), [
            'linked_entity_type' => Document::ENTITY_TYPE_CR,
            'linked_entity_id' => $changeRequest->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.linked_entity_type', Document::ENTITY_TYPE_CR)
            ->assertJsonPath('data.linked_entity_id', $changeRequest->id);

        $this->apiGet($this->zena('documents.index', query: [
            'linked_entity_type' => Document::ENTITY_TYPE_CR,
            'linked_entity_id' => $changeRequest->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id);
    }

    public function test_canonical_document_link_can_be_detached_and_writes_audit_evidence(): void
    {
        $document = $this->createDocument([
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => '01HZDETACHLINKTASK0000000001',
        ]);

        $this->apiDelete($this->zena('documents.link.detach', ['id' => $document->id]))
            ->assertOk()
            ->assertJsonPath('data.linked_entity_type', null)
            ->assertJsonPath('data.linked_entity_id', null);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'linked_entity_type' => null,
            'linked_entity_id' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->user->id,
            'action' => 'zena.document.link.detach',
            'entity_type' => 'document',
            'entity_id' => (string) $document->id,
            'project_id' => (string) $this->project->id,
            'route' => 'api/zena/documents/' . $document->id . '/link',
            'method' => 'DELETE',
            'status_code' => 200,
        ]);
    }

    public function test_canonical_document_link_attach_is_tenant_safe_for_foreign_targets(): void
    {
        $document = $this->createDocument();

        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['designer'], [
            'task.view',
            'task.create',
        ]);
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);
        $foreignTask = Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'created_by' => $otherUser->id,
            'assigned_to' => $otherUser->id,
        ]);

        $this->apiPost($this->zena('documents.link.attach', ['id' => $document->id]), [
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $foreignTask->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'linked_entity_type' => null,
            'linked_entity_id' => null,
        ]);
    }

    public function test_canonical_document_link_attach_writes_audit_evidence(): void
    {
        $document = $this->createDocument();
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->apiPost($this->zena('documents.link.attach', ['id' => $document->id]), [
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
            'linked_entity_id' => $task->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->user->id,
            'action' => 'zena.document.link.attach',
            'entity_type' => 'document',
            'entity_id' => (string) $document->id,
            'project_id' => (string) $this->project->id,
            'route' => 'api/zena/documents/' . $document->id . '/link',
            'method' => 'POST',
            'status_code' => 200,
        ]);
    }

    public function test_canonical_documents_store_and_index_prove_metadata_fields(): void
    {
        $response = $this->apiPostMultipart($this->zena('documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'A2 Architectural Set',
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'active',
            'revision' => 'B',
            'tags' => ['issued', 'coordination'],
            'description' => 'Canonical metadata proof',
            'file' => $this->createValidPdfUploadedFile('a2-architectural.pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'specification')
            ->assertJsonPath('data.discipline', 'architectural')
            ->assertJsonPath('data.package', 'SPEC-A2')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revision', 'B')
            ->assertJsonPath('data.metadata.document_type', 'specification')
            ->assertJsonPath('data.metadata.discipline', 'architectural')
            ->assertJsonPath('data.metadata.package', 'SPEC-A2')
            ->assertJsonPath('data.metadata.status', 'draft')
            ->assertJsonPath('data.metadata.revision', 'B')
            ->assertJsonPath('data.metadata.tags.0', 'issued');

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'tenant_id' => $this->tenant->id,
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'draft',
            'revision' => 'B',
        ]);

        $this->apiGet($this->zena('documents.index', query: [
            'document_type' => 'specification',
            'discipline' => 'architectural',
            'package' => 'SPEC-A2',
            'status' => 'draft',
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
            'status' => 'review',
            'revision' => '1',
            'tags' => ['approved'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Spec')
            ->assertJsonPath('data.discipline', 'interior')
            ->assertJsonPath('data.package', 'SPEC-02')
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.revision', '1');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Updated Spec',
            'discipline' => 'interior',
            'package' => 'SPEC-02',
            'status' => 'review',
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
            'status' => 'review',
            'revision' => '1',
            'tags' => ['approved'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Canonical Spec')
            ->assertJsonPath('data.discipline', 'interior')
            ->assertJsonPath('data.package', 'SPEC-02')
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.revision', '1')
            ->assertJsonPath('data.metadata.discipline', 'interior')
            ->assertJsonPath('data.metadata.package', 'SPEC-02')
            ->assertJsonPath('data.metadata.status', 'review')
            ->assertJsonPath('data.metadata.revision', '1')
            ->assertJsonPath('data.metadata.tags.0', 'approved');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Canonical Spec',
            'discipline' => 'interior',
            'package' => 'SPEC-02',
            'status' => 'review',
            'revision' => '1',
        ]);
    }

    public function test_update_rejects_direct_set_of_reserved_status_approved(): void
    {
        $document = $this->createDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'approved',
        ])->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_update_rejects_direct_set_of_reserved_status_submitted_and_rejected(): void
    {
        $document = $this->createDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'submitted',
        ])->assertStatus(422);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'rejected',
        ])->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_update_on_submitted_document_rejects_generic_lifecycle_target(): void
    {
        $document = $this->createDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'review',
        ])
            ->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_update_on_approved_document_still_updates_other_fields(): void
    {
        $document = $this->createDocument(['status' => 'approved', 'metadata' => ['status' => 'approved']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'title' => 'Tên mới sau khi đã duyệt',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Tên mới sau khi đã duyệt')
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Tên mới sau khi đã duyệt',
            'status' => 'approved',
        ]);
    }

    public function test_update_legacy_to_legacy_status_change_still_works(): void
    {
        $document = $this->createDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'review',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'review');

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'review']);
    }

    public function test_create_version_rejects_direct_set_of_reserved_status(): void
    {
        $create = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Reserved Version Guard',
            'document_type' => 'drawing',
            'status' => 'draft',
            'file' => $this->createValidPdfUploadedFile('reserved-guard-v1.pdf'),
        ])->assertCreated();

        $documentId = $create->json('data.id');

        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $documentId]), [
            'file' => $this->createValidPdfUploadedFile('reserved-guard-v2.pdf'),
            'version' => 2,
            'status' => 'approved',
        ])->assertStatus(422);
    }

    public function test_create_version_on_submitted_document_preserves_status(): void
    {
        $document = $this->createDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted'], 'version' => 1]);

        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('submitted-new-version.pdf'),
            'version' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_create_version_on_approved_document_rejects_generic_lifecycle_input(): void
    {
        $document = $this->createDocument(['status' => 'approved', 'metadata' => ['status' => 'approved'], 'version' => 1]);

        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('approved-new-version.pdf'),
            'version' => 2,
            'status' => 'review',
        ])
            ->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_store_rejects_legacy_review_status(): void
    {
        $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Legacy Review Store',
            'document_type' => 'drawing',
            'status' => 'review',
            'file' => $this->createValidPdfUploadedFile('legacy-review-store.pdf'),
        ])
            ->assertStatus(422);
    }

    public function test_api_create_defaults_to_draft_and_not_submitted_with_matching_legacy_projection(): void
    {
        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Default canonical state',
            'document_type' => 'drawing',
            'file' => $this->createValidPdfUploadedFile('default-canonical.pdf'),
        ])->assertCreated();

        $this->assertDatabaseHas('documents', [
            'id' => $response->json('data.id'),
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
        ]);
    }

    public function test_api_create_active_alias_still_materializes_draft_and_not_submitted(): void
    {
        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Active compatibility alias',
            'document_type' => 'drawing',
            'status' => 'active',
            'file' => $this->createValidPdfUploadedFile('active-alias.pdf'),
        ])->assertCreated();

        $this->assertDatabaseHas('documents', [
            'id' => $response->json('data.id'),
            'status' => 'draft',
            'lifecycle_status' => 'draft',
            'approval_status' => 'not-submitted',
        ]);
    }

    public function test_api_create_rejects_review_published_archived_and_all_approval_values(): void
    {
        foreach (['review', 'published', 'archived', 'submitted', 'awaiting-approval', 'approved', 'rejected', 'pending'] as $status) {
            $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
                'project_id' => $this->project->id,
                'title' => 'Rejected create ' . $status,
                'document_type' => 'drawing',
                'status' => $status,
                'file' => $this->createValidPdfUploadedFile('rejected-create-' . $status . '.pdf'),
            ])->assertStatus(422);
        }
    }

    public function test_generic_update_maps_active_to_draft_and_review_to_in_review_without_changing_approval(): void
    {
        $document = $this->createDocument(['status' => 'active', 'metadata' => ['status' => 'active']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['status' => 'review'])
            ->assertOk();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'review',
            'lifecycle_status' => 'in-review',
            'approval_status' => 'not-submitted',
        ]);
    }

    public function test_generic_update_rejects_submitted_awaiting_approval_approved_rejected_and_pending(): void
    {
        $document = $this->createDocument();

        foreach (['submitted', 'awaiting-approval', 'approved', 'rejected', 'pending'] as $status) {
            $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['status' => $status])
                ->assertStatus(422);
        }
    }

    public function test_generic_write_rejects_unknown_status_without_mutating_existing_unknown_legacy_rows(): void
    {
        $document = $this->createDocument(['status' => 'legacy-unknown', 'metadata' => ['status' => 'legacy-unknown']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['status' => 'unrecognized'])
            ->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'legacy-unknown', 'lifecycle_status' => null, 'approval_status' => null]);
    }

    public function test_generic_lifecycle_edit_cannot_reset_legacy_workflow_approval_state(): void
    {
        $document = $this->createDocument(['status' => 'approved', 'metadata' => ['status' => 'approved']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['status' => 'draft'])
            ->assertStatus(422);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved', 'lifecycle_status' => null, 'approval_status' => null]);
    }

    public function test_store_update_and_create_version_strip_forged_canonical_columns_and_metadata_keys(): void
    {
        $create = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Canonical forgery guard',
            'document_type' => 'drawing',
            'lifecycle_status' => 'archived',
            'approval_status' => 'approved',
            'metadata' => ['lifecycle_status' => 'archived', 'approval_status' => 'approved'],
            'file' => $this->createValidPdfUploadedFile('forgery-store.pdf'),
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $id]), [
            'lifecycle_status' => 'archived',
            'approval_status' => 'approved',
            'status' => 'review',
        ])->assertOk();
        $version = $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $id]), [
            'file' => $this->createValidPdfUploadedFile('forgery-version.pdf'),
            'version' => 2,
            'lifecycle_status' => 'archived',
            'approval_status' => 'approved',
            'metadata' => ['lifecycle_status' => 'archived', 'approval_status' => 'approved'],
        ])->assertCreated();

        $document = Document::findOrFail($id);
        $this->assertSame('in-review', $document->lifecycle_status);
        $this->assertSame('not-submitted', $document->approval_status);
        $this->assertArrayNotHasKey('lifecycle_status', $document->metadata);
        $this->assertArrayNotHasKey('approval_status', $document->metadata);
        $versionMetadata = DocumentVersion::findOrFail($version->json('data.current_version_id'))->metadata;
        $this->assertArrayNotHasKey('lifecycle_status', $versionMetadata);
        $this->assertArrayNotHasKey('approval_status', $versionMetadata);
    }

    public function test_unrelated_api_edit_does_not_materialize_or_normalize_untouched_legacy_status(): void
    {
        $document = $this->createDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['title' => 'Retitled only'])
            ->assertOk();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted', 'lifecycle_status' => null, 'approval_status' => null]);
    }

    public function test_unrelated_edit_preserves_unknown_legacy_status_and_null_canonical_columns(): void
    {
        $document = $this->createDocument(['status' => 'legacy-unknown', 'metadata' => ['status' => 'legacy-unknown']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['description' => 'An unrelated edit'])
            ->assertOk();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'legacy-unknown', 'lifecycle_status' => null, 'approval_status' => null]);
    }

    public function test_update_and_create_version_authorize_the_tenant_scoped_document_before_the_locked_mutation(): void
    {
        $document = $this->createDocument();
        $viewOnlyUser = $this->createTenantUser($this->tenant, [], ['engineer'], ['document.view']);
        $this->apiAs($viewOnlyUser, $this->tenant);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), ['status' => 'review'])->assertForbidden();
        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('unauthorized-version.pdf'),
            'version' => 2,
        ])->assertForbidden();
    }

    public function test_cross_tenant_update_and_create_version_return_not_found_without_mutation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['designer'], ['document.view', 'document.update']);
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id, 'created_by' => $otherUser->id]);
        $foreign = Document::factory()->create(['tenant_id' => $otherTenant->id, 'project_id' => $otherProject->id, 'status' => 'draft', 'version' => 1]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $foreign->id]), ['status' => 'review'])->assertNotFound();
        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $foreign->id]), [
            'file' => $this->createValidPdfUploadedFile('cross-tenant-version.pdf'),
            'version' => 2,
        ])->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $foreign->id, 'status' => 'draft', 'version' => 1]);
    }

    public function test_store_still_accepts_draft_status(): void
    {
        $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Draft Store',
            'document_type' => 'drawing',
            'status' => 'draft',
            'file' => $this->createValidPdfUploadedFile('draft-store.pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_store_rejects_direct_creation_with_reserved_statuses(): void
    {
        foreach (DocumentWorkflowStatus::reservedValues() as $reservedStatus) {
            $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
                'project_id' => $this->project->id,
                'title' => 'Reserved Store Attempt ' . $reservedStatus,
                'document_type' => 'drawing',
                'status' => $reservedStatus,
                'file' => $this->createValidPdfUploadedFile('reserved-store-' . $reservedStatus . '.pdf'),
            ])->assertStatus(422);
        }
    }

    /**
     * Amended (audit metadata forgery, not just the status column): a document
     * already has real decision audit from a genuine DocumentWorkflowService::decide()
     * call. An actor calling createVersion() with a crafted nested `metadata`
     * blob must not be able to overwrite any of the 6 protected audit keys —
     * on EITHER the parent document's metadata OR the new DocumentVersion row's
     * metadata, since createVersionRecord() shares the same $metadata value.
     */
    public function test_create_version_cannot_forge_workflow_audit_metadata(): void
    {
        $document = $this->createDocument([
            'status' => 'approved',
            'metadata' => [
                'status' => 'approved',
                'decision' => 'approved',
                'decision_by' => (string) $this->user->id,
                'decision_at' => now()->subHour()->toISOString(),
                'decision_note' => 'Đạt yêu cầu (quyết định thật)',
                'submitted_by' => (string) $this->user->id,
                'submitted_at' => now()->subHours(2)->toISOString(),
            ],
            'version' => 1,
        ]);

        $forgedActorId = '01HZFORGEDACTORID000000001';

        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('forged-audit-version.pdf'),
            'version' => 2,
            'metadata' => [
                'status' => 'rejected',
                'decision' => 'rejected',
                'decision_by' => $forgedActorId,
                'decision_at' => now()->toISOString(),
                'decision_note' => 'Bị hủy bởi kẻ giả mạo',
                'submitted_by' => $forgedActorId,
                'submitted_at' => now()->toISOString(),
            ],
        ])->assertCreated();

        $document->refresh();
        $this->assertSame('approved', $document->status);
        $this->assertSame('approved', $document->metadata['status']);
        $this->assertSame('approved', $document->metadata['decision']);
        $this->assertSame((string) $this->user->id, $document->metadata['decision_by']);
        $this->assertSame('Đạt yêu cầu (quyết định thật)', $document->metadata['decision_note']);
        $this->assertSame((string) $this->user->id, $document->metadata['submitted_by']);

        $newVersionId = $response->json('data.current_version_id');
        $versionMetadata = DocumentVersion::findOrFail($newVersionId)->metadata;
        $this->assertSame('approved', $versionMetadata['status'] ?? null);
        $this->assertSame('approved', $versionMetadata['decision'] ?? null);
        $this->assertSame((string) $this->user->id, $versionMetadata['decision_by'] ?? null);
        $this->assertNotSame($forgedActorId, $versionMetadata['decision_by'] ?? null);
    }

    /**
     * Same forgery vector via store() — a brand-new document has no prior audit
     * to protect, but the client must still be unable to plant a forged
     * decision_by/decision_note pair at creation time (protects data
     * consistency: a document must never claim a decision that never
     * happened via DocumentWorkflowService).
     */
    public function test_store_strips_protected_audit_keys_from_client_supplied_metadata(): void
    {
        $response = $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Forged Audit At Creation',
            'document_type' => 'drawing',
            'status' => 'draft',
            'metadata' => [
                'decision_by' => '01HZFORGEDATCREATE00000001',
                'decision_note' => 'Giả mạo lúc tạo mới',
                'tags' => ['legit-tag'],
            ],
            'file' => $this->createValidPdfUploadedFile('forged-audit-store.pdf'),
        ])->assertCreated();

        $documentId = $response->json('data.id');
        $document = Document::findOrFail($documentId);

        $this->assertArrayNotHasKey('decision_by', $document->metadata);
        $this->assertArrayNotHasKey('decision_note', $document->metadata);
        $this->assertSame(['legit-tag'], $document->metadata['tags'] ?? null);
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
            'document.approve',
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
            'document.approve',
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

        $adminRole = $this->user->assignRole('admin');
        $approvePermission = \App\Models\Permission::firstOrCreate(
            ['name' => 'document.approve'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Document approve']
        );
        $adminRole->permissions()->syncWithoutDetaching($approvePermission->id);
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

        $approvePermission = \App\Models\Permission::firstOrCreate(
            ['name' => 'document.approve'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Document approve']
        );
        $this->user->roles->each(function ($role) use ($approvePermission) {
            $role->permissions()->syncWithoutDetaching($approvePermission->id);
        });

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
