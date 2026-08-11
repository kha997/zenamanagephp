<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentDecision;
use App\Enums\DocumentLifecycleStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use App\Models\DocumentApprovalEvent;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class DocumentWorkflowServiceTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;
    use RouteNameTrait;

    private DocumentWorkflowService $service;
    private Tenant $tenant;
    private Project $project;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentWorkflowService::class);
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->actor->id,
        ]);
    }

    public function test_submit_accepts_draft_or_in_review_only_when_not_submitted_and_preserves_lifecycle(): void
    {
        foreach ([DocumentLifecycleStatus::DRAFT, DocumentLifecycleStatus::IN_REVIEW] as $lifecycle) {
            $document = $this->makeVersionedDocument($this->canonicalState($lifecycle, DocumentApprovalStatus::NOT_SUBMITTED));

            $result = $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());

            self::assertSame($lifecycle->value, $result->lifecycle_status);
            self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $result->approval_status);
            self::assertSame('submitted', $result->status);
            self::assertSame('submitted', $result->metadata['status']);
            self::assertSame($this->actorId(), $result->metadata['submitted_by']);
            self::assertNotNull($result->metadata['submitted_at']);
        }
    }

    public function test_submit_rejects_each_invalid_lifecycle_or_approval_combination(): void
    {
        $cases = [
            [DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED],
            [DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::NOT_SUBMITTED],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::AWAITING_APPROVAL],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::APPROVED],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::REJECTED],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::AWAITING_APPROVAL],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::APPROVED],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::REJECTED],
        ];

        foreach ($cases as [$lifecycle, $approval]) {
            $document = $this->makeVersionedDocument($this->canonicalState($lifecycle, $approval));

            $this->assertWorkflowException(
                'INVALID_SUBMIT_TRANSITION',
                fn (): Document => $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId())
            );

            self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
        }
    }

    public function test_submit_rejects_untouched_active_until_explicit_normalization_action(): void
    {
        $document = $this->makeVersionedDocument($this->legacyState('active'));

        $this->assertWorkflowException(
            'INVALID_SUBMIT_TRANSITION',
            fn (): Document => $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId())
        );

        self::assertNull($document->fresh()->getRawOriginal('lifecycle_status'));
        self::assertSame('active', $document->fresh()->getRawOriginal('status'));
    }

    public function test_submit_rejects_untouched_review_until_explicit_normalization_action(): void
    {
        $document = $this->makeVersionedDocument($this->legacyState('review'));

        $this->assertWorkflowException(
            'INVALID_SUBMIT_TRANSITION',
            fn (): Document => $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId())
        );

        self::assertNull($document->fresh()->getRawOriginal('lifecycle_status'));
        self::assertSame('review', $document->fresh()->getRawOriginal('status'));
    }

    public function test_unknown_legacy_status_cannot_submit_publish_or_use_generic_lifecycle_normalization(): void
    {
        $document = $this->makeVersionedDocument($this->legacyState('mystery-state'));

        $this->assertWorkflowException(
            'INVALID_CANONICAL_STATE',
            fn (): Document => $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId())
        );

        $fresh = $document->fresh();
        self::assertNull($fresh->lifecycle_status, 'An unresolved row is ineligible for later canonical lifecycle actions such as publish.');
        self::assertNull($fresh->approval_status);
        self::assertSame('mystery-state', $fresh->getRawOriginal('status'));
        self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    public function test_submit_fails_closed_when_document_has_no_current_version(): void
    {
        $documents = [$this->makeDocument()];

        $foreignDocument = $this->makeVersionedDocument();
        $sameTenantForeign = $this->makeDocument();
        $sameTenantForeign->forceFill(['current_version_id' => $foreignDocument->current_version_id])->saveQuietly();
        $documents[] = $sameTenantForeign;

        $otherTenant = Tenant::factory()->create();
        $otherActor = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id, 'created_by' => $otherActor->id]);
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherActor->id,
            'created_by' => $otherActor->id,
            'updated_by' => $otherActor->id,
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'metadata' => ['status' => 'draft'],
        ]);
        $otherVersion = DocumentVersion::query()->create([
            'document_id' => $otherDocument->id,
            'version_number' => 1,
            'file_path' => "documents/{$otherDocument->id}/v1.pdf",
            'storage_driver' => 'local',
            'metadata' => [],
            'created_by' => $otherActor->id,
        ]);
        $crossTenantForeign = $this->makeDocument();
        $crossTenantForeign->forceFill(['current_version_id' => $otherVersion->id])->saveQuietly();
        $documents[] = $crossTenantForeign;

        foreach ($documents as $document) {
            $this->assertWorkflowException(
                'INVALID_CURRENT_VERSION',
                fn (): Document => $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId())
            );

            $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
            self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
        }
    }

    public function test_submit_event_is_bound_to_locked_current_version(): void
    {
        $document = $this->makeVersionedDocument();
        $versionId = (string) $document->current_version_id;

        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());

        $event = DocumentApprovalEvent::query()->where('document_id', $document->id)->sole();
        self::assertSame('submitted', $event->event);
        self::assertSame($versionId, $event->document_version_id);
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $event->from_approval_status);
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $event->to_approval_status);
        self::assertSame($this->actorId(), $event->context['submitted_by']);
        self::assertSame($document->fresh()->metadata['submitted_at'], $event->context['submitted_at']);
    }

    public function test_approve_and_reject_require_awaiting_approval_and_preserve_lifecycle(): void
    {
        foreach ([DocumentDecision::APPROVED, DocumentDecision::REJECTED] as $decision) {
            $document = $this->makeVersionedDocument($this->canonicalState(
                DocumentLifecycleStatus::IN_REVIEW,
                DocumentApprovalStatus::NOT_SUBMITTED
            ));
            $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());

            $result = $this->service->decide(
                $this->tenantId(),
                (string) $document->id,
                $this->actorId(),
                $decision,
                $decision === DocumentDecision::REJECTED ? 'Needs revision' : null
            );

            self::assertSame(DocumentLifecycleStatus::IN_REVIEW->value, $result->lifecycle_status);
            self::assertSame($decision->value, $result->approval_status);
            self::assertSame($decision->value, $result->status);
        }

        $draft = $this->makeVersionedDocument();
        $this->assertWorkflowException(
            'INVALID_DECISION_TRANSITION',
            fn (): Document => $this->service->decide(
                $this->tenantId(),
                (string) $draft->id,
                $this->actorId(),
                DocumentDecision::APPROVED,
                null
            )
        );
    }

    public function test_approve_event_uses_the_same_version_as_the_submitted_event(): void
    {
        $this->assertDecisionUsesSubmittedVersion(DocumentDecision::APPROVED);
    }

    public function test_reject_event_uses_the_same_version_as_the_submitted_event(): void
    {
        $this->assertDecisionUsesSubmittedVersion(DocumentDecision::REJECTED);
    }

    public function test_legacy_submitted_without_version_bound_submit_event_cannot_be_decided(): void
    {
        $document = $this->makeVersionedDocument($this->canonicalState(
            DocumentLifecycleStatus::DRAFT,
            DocumentApprovalStatus::AWAITING_APPROVAL
        ));

        $this->assertWorkflowException(
            'LEGACY_APPROVAL_RECONCILIATION_REQUIRED',
            fn (): Document => $this->service->decide(
                $this->tenantId(),
                (string) $document->id,
                $this->actorId(),
                DocumentDecision::APPROVED,
                null
            )
        );

        $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::AWAITING_APPROVAL);
    }

    public function test_reopen_approved_resets_to_draft_not_submitted_and_preserves_historical_audit(): void
    {
        $this->assertReopenPreservesAudit(DocumentDecision::APPROVED, null);
    }

    public function test_reopen_rejected_resets_to_draft_not_submitted_and_preserves_historical_audit(): void
    {
        $this->assertReopenPreservesAudit(DocumentDecision::REJECTED, 'First rejection note');
    }

    public function test_reopen_event_preserves_the_decided_version_reference(): void
    {
        $document = $this->completedCycle(DocumentDecision::APPROVED);
        $decisionEvent = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->where('event', 'approved')
            ->sole();

        $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId());

        $reopen = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->where('event', 'reopened')
            ->sole();
        self::assertSame($decisionEvent->document_version_id, $reopen->document_version_id);
    }

    public function test_legacy_approved_without_version_bound_decision_event_cannot_be_reopened(): void
    {
        $this->assertLegacyDecisionCannotReopen(DocumentApprovalStatus::APPROVED);
    }

    public function test_legacy_rejected_without_version_bound_decision_event_cannot_be_reopened(): void
    {
        $this->assertLegacyDecisionCannotReopen(DocumentApprovalStatus::REJECTED);
    }

    public function test_reactivate_archived_resets_to_draft_not_submitted_and_preserves_historical_audit(): void
    {
        $document = $this->makeVersionedDocument($this->canonicalState(
            DocumentLifecycleStatus::ARCHIVED,
            DocumentApprovalStatus::APPROVED,
            ['decision' => 'approved', 'decision_by' => $this->actorId(), 'decision_note' => 'historical']
        ));
        $versionId = (string) $document->current_version_id;

        $result = $this->service->reactivateForRevision($this->tenantId(), (string) $document->id, $this->actorId());

        $this->assertCanonicalState($result, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        self::assertArrayNotHasKey('decision', $result->metadata);
        self::assertArrayNotHasKey('decision_by', $result->metadata);
        self::assertArrayNotHasKey('decision_note', $result->metadata);
        $event = DocumentApprovalEvent::query()->where('document_id', $document->id)->sole();
        self::assertSame('reactivated', $event->event);
        self::assertSame($versionId, $event->document_version_id);
        self::assertSame(DocumentApprovalStatus::APPROVED->value, $event->from_approval_status);
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $event->to_approval_status);
        self::assertSame([], $event->context);
    }

    public function test_reactivate_legacy_archived_document_without_current_version_records_explicit_unbound_legacy_event(): void
    {
        $document = $this->makeDocument($this->legacyState('archived'));

        $result = $this->service->reactivateForRevision($this->tenantId(), (string) $document->id, $this->actorId());

        $this->assertCanonicalState($result, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        self::assertNull($result->current_version_id);
        self::assertSame(0, DocumentVersion::query()->where('document_id', $document->id)->count());
        $event = DocumentApprovalEvent::query()->where('document_id', $document->id)->sole();
        self::assertSame('reactivated', $event->event);
        self::assertNull($event->document_version_id);
        self::assertSame(['legacy_without_current_version' => true], $event->context);
    }

    public function test_each_formal_approval_cycle_event_is_version_bound(): void
    {
        $document = $this->completedCycle(DocumentDecision::APPROVED);
        $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId());
        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
        $this->service->decide(
            $this->tenantId(),
            (string) $document->id,
            $this->actorId(),
            DocumentDecision::REJECTED,
            'Second-cycle rejection'
        );
        $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId());

        $events = DocumentApprovalEvent::query()->where('document_id', $document->id)->get();
        self::assertCount(6, $events);
        self::assertSame([], $events->whereNull('document_version_id')->all());
    }

    public function test_two_approval_cycles_preserve_the_first_cycle_event_bytes_after_reopen_and_resubmit(): void
    {
        $document = $this->completedCycle(DocumentDecision::REJECTED, 'Cycle one note');
        $firstCycleIds = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->pluck('id');
        $before = DocumentApprovalEvent::query()
            ->whereKey($firstCycleIds)
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentApprovalEvent $event): array => $event->getRawOriginal())
            ->all();

        $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId());
        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
        $this->service->decide(
            $this->tenantId(),
            (string) $document->id,
            $this->actorId(),
            DocumentDecision::APPROVED,
            'Cycle two note'
        );

        $after = DocumentApprovalEvent::query()
            ->whereKey($firstCycleIds)
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentApprovalEvent $event): array => $event->getRawOriginal())
            ->all();
        self::assertSame($before, $after);
        self::assertSame(5, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    public function test_failed_transition_rolls_back_state_projection_and_approval_event_together(): void
    {
        $document = $this->makeVersionedDocument();
        Document::saving(static function (Document $saving) use ($document): void {
            if ((string) $saving->getKey() === (string) $document->getKey()
                && ($saving->getAttributes()['approval_status'] ?? null) === DocumentApprovalStatus::AWAITING_APPROVAL->value) {
                throw new RuntimeException('Injected document save failure after event append.');
            }
        });

        try {
            $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
            self::fail('Expected the injected save failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected document save failure after event append.', $exception->getMessage());
        }

        $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    public function test_cross_tenant_workflow_actions_return_document_not_found_without_mutation(): void
    {
        $document = $this->makeVersionedDocument();
        $otherTenant = Tenant::factory()->create();
        $calls = [
            fn (): Document => $this->service->submit((string) $otherTenant->id, (string) $document->id, $this->actorId()),
            fn (): Document => $this->service->decide((string) $otherTenant->id, (string) $document->id, $this->actorId(), DocumentDecision::APPROVED, null),
            fn (): Document => $this->service->reopenForRevision((string) $otherTenant->id, (string) $document->id, $this->actorId()),
            fn (): Document => $this->service->reactivateForRevision((string) $otherTenant->id, (string) $document->id, $this->actorId()),
        ];

        foreach ($calls as $call) {
            $this->assertWorkflowException('DOCUMENT_NOT_FOUND', $call);
        }

        $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    public function test_approve_and_reject_require_document_approve_permission_and_policy_authorization(): void
    {
        $document = $this->makeVersionedDocument();
        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
        $nonApprover = $this->createTenantUser($this->tenant, [], ['engineer'], ['document.view']);
        self::assertFalse(Gate::forUser($nonApprover)->allows('approve', $document));

        $this->apiAs($nonApprover, $this->tenant);
        foreach (DocumentDecision::cases() as $decision) {
            $this->apiPost($this->zena('documents.decision', ['id' => $document->id]), [
                'decision' => $decision->value,
            ])->assertForbidden();
        }

        $approver = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        self::assertTrue(Gate::forUser($approver)->allows('approve', $document));
        $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::AWAITING_APPROVAL);
        self::assertSame(1, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    private function assertDecisionUsesSubmittedVersion(DocumentDecision $decision): void
    {
        $document = $this->makeVersionedDocument();
        $submittedVersionId = (string) $document->current_version_id;
        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
        $newVersion = $this->makeVersion($document, 2);
        $document->forceFill(['current_version_id' => $newVersion->id])->saveQuietly();

        $this->service->decide(
            $this->tenantId(),
            (string) $document->id,
            $this->actorId(),
            $decision,
            $decision === DocumentDecision::REJECTED ? 'Rejected' : null
        );

        $event = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->where('event', $decision->value)
            ->sole();
        self::assertSame($submittedVersionId, $event->document_version_id);
        self::assertNotSame((string) $newVersion->id, $event->document_version_id);
    }

    private function assertReopenPreservesAudit(DocumentDecision $decision, ?string $note): void
    {
        $document = $this->completedCycle($decision, $note);
        $historical = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentApprovalEvent $event): array => $event->getRawOriginal())
            ->all();

        $result = $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId());

        $this->assertCanonicalState($result, DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        self::assertArrayNotHasKey('decision', $result->metadata);
        self::assertArrayNotHasKey('decision_by', $result->metadata);
        self::assertArrayNotHasKey('decision_at', $result->metadata);
        self::assertArrayNotHasKey('decision_note', $result->metadata);
        $preserved = DocumentApprovalEvent::query()
            ->whereIn('id', array_column($historical, 'id'))
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentApprovalEvent $event): array => $event->getRawOriginal())
            ->all();
        self::assertSame($historical, $preserved);

        $events = DocumentApprovalEvent::query()->where('document_id', $document->id)->orderBy('id')->get();
        self::assertCount(3, $events);
        self::assertSame('submitted', $events[0]->event);
        self::assertSame($this->actorId(), $events[0]->actor_id);
        self::assertNotNull($events[0]->created_at);
        self::assertSame($decision->value, $events[1]->event);
        self::assertSame($this->actorId(), $events[1]->actor_id);
        self::assertSame($note, $events[1]->note);
        self::assertNotNull($events[1]->created_at);
        self::assertSame('reopened', $events[2]->event);
    }

    private function assertLegacyDecisionCannotReopen(DocumentApprovalStatus $approval): void
    {
        $document = $this->makeVersionedDocument($this->canonicalState(DocumentLifecycleStatus::DRAFT, $approval));

        $this->assertWorkflowException(
            'LEGACY_APPROVAL_RECONCILIATION_REQUIRED',
            fn (): Document => $this->service->reopenForRevision($this->tenantId(), (string) $document->id, $this->actorId())
        );

        $this->assertCanonicalState($document->fresh(), DocumentLifecycleStatus::DRAFT, $approval);
        self::assertSame(0, DocumentApprovalEvent::query()->where('document_id', $document->id)->count());
    }

    private function completedCycle(DocumentDecision $decision, ?string $note = null): Document
    {
        $document = $this->makeVersionedDocument();
        $this->service->submit($this->tenantId(), (string) $document->id, $this->actorId());
        $this->service->decide(
            $this->tenantId(),
            (string) $document->id,
            $this->actorId(),
            $decision,
            $note
        );

        return $document->fresh();
    }

    private function makeVersionedDocument(array $overrides = []): Document
    {
        $document = $this->makeDocument($overrides);
        $version = $this->makeVersion($document, 1);
        $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

        return $document->fresh();
    }

    private function makeVersion(Document $document, int $number): DocumentVersion
    {
        return DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => $number,
            'file_path' => "documents/{$document->id}/v{$number}.pdf",
            'storage_driver' => 'local',
            'comment' => "Version {$number}",
            'metadata' => ['version' => $number],
            'created_by' => $this->actor->id,
        ]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->actor->id,
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'metadata' => ['status' => 'draft'],
            'current_version_id' => null,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function canonicalState(
        DocumentLifecycleStatus $lifecycle,
        DocumentApprovalStatus $approval,
        array $metadata = []
    ): array {
        $status = match ($approval) {
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

        return [
            'status' => $status,
            'lifecycle_status' => $lifecycle->value,
            'approval_status' => $approval->value,
            'metadata' => ['status' => $status] + $metadata,
        ];
    }

    /** @return array<string, mixed> */
    private function legacyState(string $status): array
    {
        return [
            'status' => $status,
            'lifecycle_status' => null,
            'approval_status' => null,
            'metadata' => ['status' => $status],
        ];
    }

    private function assertCanonicalState(
        Document $document,
        DocumentLifecycleStatus $lifecycle,
        DocumentApprovalStatus $approval
    ): void {
        self::assertSame($lifecycle->value, $document->getRawOriginal('lifecycle_status'));
        self::assertSame($approval->value, $document->getRawOriginal('approval_status'));
    }

    /** @param callable(): mixed $callback */
    private function assertWorkflowException(string $reasonCode, callable $callback): void
    {
        try {
            $callback();
            self::fail("Expected DocumentWorkflowException with reason {$reasonCode}.");
        } catch (DocumentWorkflowException $exception) {
            self::assertSame($reasonCode, $exception->reasonCode);
        }
    }

    private function tenantId(): string
    {
        return (string) $this->tenant->id;
    }

    private function actorId(): string
    {
        return (string) $this->actor->id;
    }
}
