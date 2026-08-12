<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentDecision;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class DocumentLifecycleActionsTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;
    use RouteNameTrait;

    private Tenant $tenant;
    private Project $project;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['designer'], [
            'document.view',
            'document.update',
            'document.approve',
        ]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->actor->id,
        ]);
        $this->apiAs($this->actor, $this->tenant);
    }

    public function test_publish_allows_draft_or_in_review_with_not_submitted(): void
    {
        foreach ([DocumentLifecycleStatus::DRAFT, DocumentLifecycleStatus::IN_REVIEW] as $lifecycle) {
            $document = $this->makeDocument($lifecycle, DocumentApprovalStatus::NOT_SUBMITTED);

            $this->apiPost($this->zena('documents.publish', ['id' => $document->id]))
                ->assertOk()
                ->assertJsonPath('data.lifecycle_status', 'published')
                ->assertJsonPath('data.approval_status', 'not-submitted')
                ->assertJsonPath('data.status', 'published')
                ->assertJsonPath('data.metadata.status', 'published');

            $this->assertCanonicalState($document, DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED);
        }
    }

    public function test_publish_allows_draft_or_in_review_with_approved(): void
    {
        foreach ([DocumentLifecycleStatus::DRAFT, DocumentLifecycleStatus::IN_REVIEW] as $lifecycle) {
            $document = $this->makeDocument($lifecycle, DocumentApprovalStatus::APPROVED);

            $this->apiPost($this->zena('documents.publish', ['id' => $document->id]))
                ->assertOk()
                ->assertJsonPath('data.lifecycle_status', 'published')
                ->assertJsonPath('data.approval_status', 'approved')
                ->assertJsonPath('data.status', 'approved')
                ->assertJsonPath('data.metadata.status', 'approved');

            $this->assertCanonicalState($document, DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::APPROVED);
        }
    }

    public function test_publish_rejects_awaiting_approval_and_rejected(): void
    {
        foreach ([DocumentApprovalStatus::AWAITING_APPROVAL, DocumentApprovalStatus::REJECTED] as $approval) {
            $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, $approval);

            $this->apiPost($this->zena('documents.publish', ['id' => $document->id]))
                ->assertConflict()
                ->assertJsonPath('error.code', 'E409.CONFLICT');

            $this->assertCanonicalState($document, DocumentLifecycleStatus::DRAFT, $approval);
        }
    }

    public function test_archive_requires_published_and_preserves_approval(): void
    {
        foreach ([DocumentApprovalStatus::NOT_SUBMITTED, DocumentApprovalStatus::APPROVED] as $approval) {
            $document = $this->makeDocument(DocumentLifecycleStatus::PUBLISHED, $approval);

            $this->apiPost($this->zena('documents.archive', ['id' => $document->id]))
                ->assertOk()
                ->assertJsonPath('data.lifecycle_status', 'archived')
                ->assertJsonPath('data.approval_status', $approval->value);

            $this->assertCanonicalState($document, DocumentLifecycleStatus::ARCHIVED, $approval);
        }

        $draft = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $this->apiPost($this->zena('documents.archive', ['id' => $draft->id]))->assertConflict();
        $this->assertCanonicalState($draft, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
    }

    public function test_reopen_requires_approved_or_rejected(): void
    {
        foreach ([DocumentDecision::APPROVED, DocumentDecision::REJECTED] as $decision) {
            $document = $this->completedApprovalCycle($decision);

            $this->apiPost($this->zena('documents.reopen', ['id' => $document->id]))
                ->assertOk()
                ->assertJsonPath('data.lifecycle_status', 'draft')
                ->assertJsonPath('data.approval_status', 'not-submitted')
                ->assertJsonPath('data.status', 'draft');

            $this->assertCanonicalState($document, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        }

        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $this->apiPost($this->zena('documents.reopen', ['id' => $document->id]))->assertConflict();
    }

    public function test_reactivate_requires_archived(): void
    {
        $archived = $this->makeDocument(DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::APPROVED);

        $this->apiPost($this->zena('documents.reactivate', ['id' => $archived->id]))
            ->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'draft')
            ->assertJsonPath('data.approval_status', 'not-submitted')
            ->assertJsonPath('data.status', 'draft');
        $this->assertCanonicalState($archived, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);

        $published = $this->makeDocument(DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED);
        $this->apiPost($this->zena('documents.reactivate', ['id' => $published->id]))->assertConflict();
        $this->assertCanonicalState($published, DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED);
    }

    public function test_unresolved_legacy_row_is_ineligible_for_submit_publish_archive_approve_reject_reopen_and_reactivate(): void
    {
        $document = $this->makeUnresolvedLegacyDocument();

        foreach (['submit', 'publish', 'archive', 'reopen', 'reactivate'] as $action) {
            $this->apiPost($this->zena("documents.{$action}", ['id' => $document->id]))->assertConflict();
        }

        foreach (['approved', 'rejected'] as $decision) {
            $this->apiPost($this->zena('documents.decision', ['id' => $document->id]), [
                'decision' => $decision,
            ])->assertConflict();
        }

        $fresh = $document->fresh();
        self::assertSame('legacy-custom-state', $fresh->getRawOriginal('status'));
        self::assertNull($fresh->getRawOriginal('lifecycle_status'));
        self::assertNull($fresh->getRawOriginal('approval_status'));
    }

    public function test_lifecycle_actions_require_document_update_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['document-lifecycle-viewer'], ['document.view']);
        $this->apiAs($viewer, $this->tenant);

        $publishable = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $archivable = $this->makeDocument(DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED);

        $this->apiPost($this->zena('documents.publish', ['id' => $publishable->id]))->assertForbidden();
        $this->apiPost($this->zena('documents.archive', ['id' => $archivable->id]))->assertForbidden();

        $this->assertCanonicalState($publishable, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $this->assertCanonicalState($archivable, DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED);
    }

    public function test_reopen_and_reactivate_require_document_update_permission(): void
    {
        $reopenable = $this->completedApprovalCycle(DocumentDecision::APPROVED);
        $reactivatable = $this->makeDocument(DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::APPROVED);
        $viewer = $this->createTenantUser($this->tenant, [], ['document-revision-viewer'], ['document.view']);
        $this->apiAs($viewer, $this->tenant);

        $this->apiPost($this->zena('documents.reopen', ['id' => $reopenable->id]))->assertForbidden();
        $this->apiPost($this->zena('documents.reactivate', ['id' => $reactivatable->id]))->assertForbidden();

        $this->assertCanonicalState($reopenable, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::APPROVED);
        $this->assertCanonicalState($reactivatable, DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::APPROVED);
    }

    public function test_cross_tenant_action_is_not_found_and_cannot_mutate_document(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherActor = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id, 'created_by' => $otherActor->id]);

        $documents = [
            'publish' => $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $otherTenant, $otherProject, $otherActor),
            'archive' => $this->makeDocument(DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED, $otherTenant, $otherProject, $otherActor),
            'reopen' => $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::APPROVED, $otherTenant, $otherProject, $otherActor),
            'reactivate' => $this->makeDocument(DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::APPROVED, $otherTenant, $otherProject, $otherActor),
        ];

        foreach ($documents as $action => $document) {
            $before = $document->getRawOriginal('status');
            $this->apiPost($this->zena("documents.{$action}", ['id' => $document->id]))->assertNotFound();
            self::assertSame($before, $document->fresh()->getRawOriginal('status'));
        }
    }

    public function test_action_routes_keep_expected_names_middleware_and_compatibility_surface(): void
    {
        foreach (['publish', 'archive', 'reopen', 'reactivate'] as $action) {
            $zena = Route::getRoutes()->getByName("api.zena.documents.{$action}");
            self::assertNotNull($zena);
            self::assertSame("api/zena/documents/{id}/{$action}", $zena->uri());
            self::assertContains('auth:sanctum', $zena->gatherMiddleware());
            self::assertContains('tenant.isolation', $zena->gatherMiddleware());
            self::assertContains('rbac:document.update', $zena->gatherMiddleware());

            $compatibility = collect(Route::getRoutes()->getRoutes())->filter(
                fn ($route): bool => $route->uri() === "api/documents/{document}/{$action}"
                    && in_array('POST', $route->methods(), true)
            );
            self::assertCount(1, $compatibility);
            self::assertNull($compatibility->first()?->getName());
            self::assertContains('rbac:document.update', $compatibility->first()?->gatherMiddleware() ?? []);
        }
    }

    public function test_project_manager_can_assign_approver(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $this->tenant, $this->project, $pm);

        $this->apiAs($pm, $this->tenant);
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $eligible->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.approver_id', $eligible->id);
    }

    public function test_designer_without_pm_or_admin_role_cannot_assign_approver(): void
    {
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        // $this->actor is a 'designer', not the project's pm and not admin.
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $eligible->id,
        ])->assertForbidden();
    }

    public function test_assigning_a_target_without_document_approve_permission_returns_conflict(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $ineligible = $this->createTenantUser($this->tenant, [], ['document-approver-ineligible'], ['document.view']);
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $this->tenant, $this->project, $pm);

        $this->apiAs($pm, $this->tenant);
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $ineligible->id,
        ])->assertStatus(409);
    }

    private function completedApprovalCycle(DocumentDecision $decision): Document
    {
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $workflow = $this->app->make(DocumentWorkflowService::class);
        $workflow->submit((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id);

        return $workflow->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            $decision,
            $decision === DocumentDecision::REJECTED ? 'Revision required' : null,
        );
    }

    private function makeUnresolvedLegacyDocument(): Document
    {
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $document->forceFill([
            'status' => 'legacy-custom-state',
            'lifecycle_status' => null,
            'approval_status' => null,
            'metadata' => ['status' => 'legacy-custom-state'],
        ])->saveQuietly();

        return $document->fresh();
    }

    private function makeDocument(
        DocumentLifecycleStatus $lifecycle,
        DocumentApprovalStatus $approval,
        ?Tenant $tenant = null,
        ?Project $project = null,
        ?User $actor = null,
    ): Document {
        $tenant ??= $this->tenant;
        $project ??= $this->project;
        $actor ??= $this->actor;
        $legacy = $this->projectedStatus($lifecycle, $approval);

        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => $legacy,
            'lifecycle_status' => $lifecycle->value,
            'approval_status' => $approval->value,
            'metadata' => ['status' => $legacy],
            'current_version_id' => null,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => "documents/{$document->id}/v1.pdf",
            'storage_driver' => 'local',
            'comment' => 'Initial version',
            'metadata' => ['version' => 1],
            'created_by' => $actor->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

        return $document->fresh();
    }

    private function assertCanonicalState(
        Document $document,
        DocumentLifecycleStatus $lifecycle,
        DocumentApprovalStatus $approval,
    ): void {
        $fresh = $document->fresh();
        self::assertSame($lifecycle->value, $fresh->getRawOriginal('lifecycle_status'));
        self::assertSame($approval->value, $fresh->getRawOriginal('approval_status'));
        self::assertSame($this->projectedStatus($lifecycle, $approval), $fresh->getRawOriginal('status'));
        self::assertSame($fresh->getRawOriginal('status'), $fresh->metadata['status'] ?? null);
    }

    private function projectedStatus(DocumentLifecycleStatus $lifecycle, DocumentApprovalStatus $approval): string
    {
        return match ($approval) {
            DocumentApprovalStatus::AWAITING_APPROVAL => 'submitted',
            DocumentApprovalStatus::APPROVED => 'approved',
            DocumentApprovalStatus::REJECTED => 'rejected',
            DocumentApprovalStatus::NOT_SUBMITTED => match ($lifecycle) {
                DocumentLifecycleStatus::DRAFT => 'draft',
                DocumentLifecycleStatus::IN_REVIEW => 'review',
                DocumentLifecycleStatus::PUBLISHED => 'published',
                DocumentLifecycleStatus::ARCHIVED => 'archived',
            },
        };
    }
}
