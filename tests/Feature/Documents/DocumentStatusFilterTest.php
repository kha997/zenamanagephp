<?php declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private DocumentStatusService $service;
    private Tenant $tenant;
    private User $actor;
    /** @var array<string, Document> */
    private array $documents = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'lifecycle_status')) {
                $table->string('lifecycle_status')->nullable();
            }

            if (! Schema::hasColumn('documents', 'approval_status')) {
                $table->string('approval_status')->nullable();
            }
        });

        $this->service = app(DocumentStatusService::class);
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->seedDocuments();
    }

    public function test_all_filter_values_match_materialized_and_untouched_rows_without_cross_tenant_results(): void
    {
        $lifecycleCases = [
            [DocumentLifecycleStatus::DRAFT, ['materialized_draft', 'materialized_approved', 'legacy_active', 'legacy_draft']],
            [DocumentLifecycleStatus::IN_REVIEW, ['materialized_in_review', 'materialized_rejected', 'legacy_review']],
            [DocumentLifecycleStatus::PUBLISHED, ['materialized_published', 'materialized_awaiting', 'legacy_published']],
            [DocumentLifecycleStatus::ARCHIVED, ['materialized_archived', 'legacy_archived']],
        ];

        foreach ($lifecycleCases as [$status, $expected]) {
            self::assertSame($this->ids($expected), $this->filteredLifecycleIds($status));
        }

        $approvalCases = [
            [DocumentApprovalStatus::NOT_SUBMITTED, ['materialized_draft', 'materialized_in_review', 'materialized_published', 'materialized_archived', 'legacy_active', 'legacy_draft', 'legacy_review', 'legacy_published', 'legacy_archived']],
            [DocumentApprovalStatus::AWAITING_APPROVAL, ['materialized_awaiting', 'legacy_submitted']],
            [DocumentApprovalStatus::APPROVED, ['materialized_approved', 'legacy_approved']],
            [DocumentApprovalStatus::REJECTED, ['materialized_rejected', 'legacy_rejected']],
        ];

        foreach ($approvalCases as [$status, $expected]) {
            self::assertSame($this->ids($expected), $this->filteredApprovalIds($status));
        }
    }

    public function test_unknown_legacy_status_does_not_match_any_canonical_lifecycle_filter(): void
    {
        foreach (DocumentLifecycleStatus::cases() as $status) {
            self::assertNotContains($this->documents['legacy_unknown']->id, $this->filteredLifecycleIds($status));
        }
    }

    public function test_unknown_legacy_status_does_not_match_any_canonical_approval_filter(): void
    {
        foreach (DocumentApprovalStatus::cases() as $status) {
            self::assertNotContains($this->documents['legacy_unknown']->id, $this->filteredApprovalIds($status));
        }
    }

    public function test_legacy_workflow_statuses_do_not_match_any_canonical_lifecycle_filter(): void
    {
        foreach (DocumentLifecycleStatus::cases() as $status) {
            $ids = $this->filteredLifecycleIds($status);
            self::assertNotContains($this->documents['legacy_submitted']->id, $ids);
            self::assertNotContains($this->documents['legacy_approved']->id, $ids);
            self::assertNotContains($this->documents['legacy_rejected']->id, $ids);
        }
    }

    public function test_legacy_submitted_matches_awaiting_approval_filter(): void
    {
        self::assertContains(
            $this->documents['legacy_submitted']->id,
            $this->filteredApprovalIds(DocumentApprovalStatus::AWAITING_APPROVAL)
        );
    }

    public function test_legacy_approved_and_rejected_match_their_approval_filters(): void
    {
        self::assertContains($this->documents['legacy_approved']->id, $this->filteredApprovalIds(DocumentApprovalStatus::APPROVED));
        self::assertContains($this->documents['legacy_rejected']->id, $this->filteredApprovalIds(DocumentApprovalStatus::REJECTED));
    }

    public function test_legacy_status_filter_can_still_exact_match_unknown_legacy_value(): void
    {
        $ids = $this->service->applyLegacyStatusFilter(
            Document::query()->where('tenant_id', $this->tenant->id),
            'legacy-custom-state'
        )->pluck('id')->sort()->values()->all();

        self::assertSame([$this->documents['legacy_unknown']->id], $ids);
    }

    private function seedDocuments(): void
    {
        foreach ([
            'materialized_draft' => ['draft', 'draft', 'not-submitted'],
            'materialized_in_review' => ['review', 'in-review', 'not-submitted'],
            'materialized_published' => ['published', 'published', 'not-submitted'],
            'materialized_archived' => ['archived', 'archived', 'not-submitted'],
            'materialized_awaiting' => ['submitted', 'published', 'awaiting-approval'],
            'materialized_approved' => ['approved', 'draft', 'approved'],
            'materialized_rejected' => ['rejected', 'in-review', 'rejected'],
            'legacy_active' => ['active', null, null],
            'legacy_draft' => ['draft', null, null],
            'legacy_review' => ['review', null, null],
            'legacy_published' => ['published', null, null],
            'legacy_archived' => ['archived', null, null],
            'legacy_submitted' => ['submitted', null, null],
            'legacy_approved' => ['approved', null, null],
            'legacy_rejected' => ['rejected', null, null],
            'legacy_unknown' => ['legacy-custom-state', null, null],
        ] as $name => [$legacy, $lifecycle, $approval]) {
            $this->documents[$name] = $this->makeDocument($legacy, $lifecycle, $approval);
        }

        $otherTenant = Tenant::factory()->create();
        $otherActor = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->documents['other_tenant_unknown'] = $this->makeDocument('legacy-custom-state', null, null, $otherTenant, $otherActor);
    }

    private function makeDocument(string $legacy, ?string $lifecycle, ?string $approval, ?Tenant $tenant = null, ?User $actor = null): Document
    {
        $tenant ??= $this->tenant;
        $actor ??= $this->actor;

        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => $legacy,
            'metadata' => ['status' => $legacy],
        ]);

        DB::table('documents')->where('id', $document->id)->update([
            'lifecycle_status' => $lifecycle,
            'approval_status' => $approval,
        ]);

        return $document->fresh();
    }

    /** @param array<int, string> $names */
    private function ids(array $names): array
    {
        return collect($names)->map(fn (string $name) => $this->documents[$name]->id)->sort()->values()->all();
    }

    private function filteredLifecycleIds(DocumentLifecycleStatus $status): array
    {
        return $this->service->applyLifecycleFilter(
            Document::query()->where('tenant_id', $this->tenant->id),
            $status
        )->pluck('id')->sort()->values()->all();
    }

    private function filteredApprovalIds(DocumentApprovalStatus $status): array
    {
        return $this->service->applyApprovalFilter(
            Document::query()->where('tenant_id', $this->tenant->id),
            $status
        )->pluck('id')->sort()->values()->all();
    }
}
