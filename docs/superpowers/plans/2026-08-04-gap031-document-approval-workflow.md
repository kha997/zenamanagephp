# GAP-031 Document Approval Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify the dead Web document-approval surface with the real canonical API workflow (`SimpleDocumentController`), close the reserved-status write bypass discovered in rev 3 of the design spec, and make the Web submit/approve/reject path actually usable end to end.

**Architecture:** Extract `DocumentWorkflowService` (transactional, `lockForUpdate()`-guarded `draft→submitted→approved|rejected` transitions) as the single mutation owner. `SimpleDocumentController` (API) and a new thin `DocumentWorkflowController` (Web) both delegate to it. `store()`/`update()`/`createVersion()` on the API controller gain a reserved-status guard so the 3 workflow statuses can never be written outside the service. Dead `DocumentController::approve()/reject()` are deleted once the new adapters are green.

**Tech Stack:** Laravel 12, PHP 8.3 enums, PHPUnit Feature tests, `Symfony\Component\Process\Process` for real two-process MySQL concurrency verification (existing `RfiEscalationConcurrencyTest` pattern).

**Canonical design source:** `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` (rev 3, commit `9aa34575`). Where that file says "giữ nguyên nội dung rev 2" for a section, the actual code lives in this plan — reconstructed from `git show daab4360:docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` (rev 2, still reachable via reflog) and re-verified against current repo state during plan-writing (see Task boundaries below for the specific corrections found).

## Global Constraints

- No database migration. No new columns. Audit stays in `Document.metadata` JSON (spec §2/§15).
- No `pending` status. No 6-column `approved_*`/`rejected_*` design (spec's dead-code framing, explicitly rejected).
- `submit` stays gated by `document.update` (both API and Web). `decision`/`approve`/`reject` (and the approvals list) are gated by `document.approve` (spec §9/§16).
- `decision_note`/`note` is **always optional** at the service/API layer, on both approve and reject — required-on-reject is a Web-form-only UI rule (spec §2 "Ngoài phạm vi", §6, §8).
- API `store()`/`update()` public contract for **legacy** statuses (`draft`, `active`, `review`, ...) is unchanged — only `submitted`/`approved`/`rejected` become reserved and blocked from direct writes (spec rev 3 §2/§3/§7.2-7.4).
- `DocumentWorkflowService` contains no authorization — callers (`SimpleDocumentController`, `DocumentWorkflowController`) call `$this->authorize(...)` before invoking it (spec §7/§8).
- No raw exception messages ever reach an HTTP response or a Blade view — only `DocumentWorkflowException::reasonCode` maps to a fixed user-facing message; internals go through `report()` (spec §5/§12).
- Concurrency correctness must be proven on a real two-process MySQL run (skip with an explicit message if unavailable), not claimed from a sequential SQLite test alone (spec §17 "kiểm chứng trung thực").
- **Newly discovered during plan-writing, binding for Task 3 (amended — moved earlier than originally planned):** `document.approve` does **not** exist in `database/seeders/ZenaPermissionsSeeder.php` (only `document.view/create/update/delete` at lines 136-139). `tests/Feature/Web/ProjectStoreRbacTest::test_web_rbac_params_reference_real_permissions_or_roles` scans every web route's `rbac:` middleware param against `Permission::pluck('code')` after seeding `ZenaPermissionsSeeder` — any new `rbac:document.approve` web route will fail that invariant test unless `document.approve` is added to `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS`. **Task 3 adds it**, in the same commit that changes `documents.decision`'s middleware to `rbac:document.approve` — not deferred to Task 6, so every task commit from Task 3 onward is internally consistent and the permission exists before any code depends on it. (`PermissionSeeder.php`, a separate seeder used by API-context tests via ad-hoc fixtures, already has `document.approve` at line 46 — unaffected, no change needed there.)
- **Newly discovered during plan-amendment, binding for Task 4:** `SimpleDocumentController::buildMetadata()` (`:658-677`) merges any client-supplied nested `metadata` array wholesale into the document's metadata JSON, and `createVersion()` (`:448`) passes `$request->all()` — not validated data — into it. Neither the original Task 4 nor the reserved-status column guard protects `metadata.decision_by`/`metadata.decision_at`/`metadata.decision_note`/`metadata.submitted_by`/`metadata.submitted_at`/`metadata.status` from being overwritten by a caller who only holds `document.update`: the column-level guard added in the original Task 4 stops `status` (the column) from being forged, but `buildMetadata()`'s nested-`metadata` merge is a second, independent path into the exact same audit fields, unguarded. Since `createVersionRecord()` passes the same `$metadata` value into the new `DocumentVersion` row, a forged blob lands in both `documents.metadata` and `document_versions.metadata`. Task 4 (amended below) closes this.
- **Newly discovered during plan-amendment, binding for Task 8:** no CI workflow currently runs `RfiEscalationConcurrencyTest` or any `@group stress` test as a merge gate at all (confirmed via repo-wide search — the job `rfi-escalation-concurrency-mysql` in `.github/workflows/automated-testing.yml` runs it, but nothing currently *requires* it to pass before merge beyond that job's own pass/fail; there is no separate "stress gate" workflow). Task 8 (amended below) adds an equivalent dedicated job for `DocumentWorkflowConcurrencyTest`, modeled exactly on `scripts/ci/rfi-escalation-concurrency-mysql` and its matching job, including the same "SKIP despite reachable MySQL preflight must fail the job" contract.
- **Skip contract (binding for Task 8):** `scripts/ssot/lint_tests.sh` (via `composer ssot:lint`) enforces an exact-match baseline (`scripts/ssot/baselines/skipped_tests_baseline.txt`) of every `markTestSkipped()` call site in `tests/`, each requiring an `@group` in `{slow,load,stress,redis}` and a recognized reason token (`RUN_SLOW_TESTS|RUN_LOAD_TESTS|RUN_STRESS_TESTS|REDIS_*|dependency:`) — enforced by `scripts/ssot/collect_skip_inventory.php`, which parses test files directly in pure PHP (not `rg`-dependent, unlike 4 of this script's *other* checks — see `[[feedback_ci_rg_missing_lint_no_op]]` memory — so this specific check is reliable in CI even where `rg` is absent). `RfiEscalationConcurrencyTest`'s existing baseline entry (`RfiEscalationConcurrencyTest::skipUnlessMysqlAvailable|group=stress|reason=dependency:`) is the exact pattern `DocumentWorkflowConcurrencyTest` must match.

---

## Task 1: Shared workflow enums and exception contract

**Files:**
- Create: `app/Enums/DocumentWorkflowStatus.php`
- Create: `app/Enums/DocumentDecision.php`
- Create: `app/Exceptions/DocumentWorkflowException.php`
- Test: `tests/Unit/Enums/DocumentWorkflowStatusTest.php`
- Test: `tests/Unit/Enums/DocumentDecisionTest.php`
- Test: `tests/Unit/Exceptions/DocumentWorkflowExceptionTest.php`

**Interfaces:**
- Produces: `App\Enums\DocumentWorkflowStatus` (cases `DRAFT|SUBMITTED|APPROVED|REJECTED`, statics `reserved(): self[]`, `reservedValues(): string[]`, `isReserved(string): bool`); `App\Enums\DocumentDecision` (cases `APPROVED|REJECTED`, `toWorkflowStatus(): DocumentWorkflowStatus`); `App\Exceptions\DocumentWorkflowException` (public readonly `string $reasonCode`, statics `invalidSubmitTransition(string $currentStatus): self`, `invalidDecisionTransition(string $currentStatus): self`, `documentNotFound(): self`).
- Consumes: nothing (no dependency on earlier tasks — this is the first task).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Enums/DocumentWorkflowStatusTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DocumentWorkflowStatus;
use PHPUnit\Framework\TestCase;

class DocumentWorkflowStatusTest extends TestCase
{
    public function test_reserved_returns_submitted_approved_rejected(): void
    {
        $this->assertSame(
            [DocumentWorkflowStatus::SUBMITTED, DocumentWorkflowStatus::APPROVED, DocumentWorkflowStatus::REJECTED],
            DocumentWorkflowStatus::reserved()
        );
    }

    public function test_reserved_values_returns_string_array(): void
    {
        $this->assertSame(['submitted', 'approved', 'rejected'], DocumentWorkflowStatus::reservedValues());
    }

    public function test_is_reserved_true_for_workflow_statuses(): void
    {
        $this->assertTrue(DocumentWorkflowStatus::isReserved('submitted'));
        $this->assertTrue(DocumentWorkflowStatus::isReserved('approved'));
        $this->assertTrue(DocumentWorkflowStatus::isReserved('rejected'));
    }

    public function test_is_reserved_false_for_draft_and_legacy_statuses(): void
    {
        $this->assertFalse(DocumentWorkflowStatus::isReserved('draft'));
        $this->assertFalse(DocumentWorkflowStatus::isReserved('active'));
        $this->assertFalse(DocumentWorkflowStatus::isReserved('review'));
    }
}
```

`tests/Unit/Enums/DocumentDecisionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DocumentDecision;
use App\Enums\DocumentWorkflowStatus;
use PHPUnit\Framework\TestCase;

class DocumentDecisionTest extends TestCase
{
    public function test_approved_to_workflow_status_maps_to_approved(): void
    {
        $this->assertSame(DocumentWorkflowStatus::APPROVED, DocumentDecision::APPROVED->toWorkflowStatus());
    }

    public function test_rejected_to_workflow_status_maps_to_rejected(): void
    {
        $this->assertSame(DocumentWorkflowStatus::REJECTED, DocumentDecision::REJECTED->toWorkflowStatus());
    }

    public function test_decision_values_match_workflow_status_values(): void
    {
        $this->assertSame(DocumentWorkflowStatus::APPROVED->value, DocumentDecision::APPROVED->value);
        $this->assertSame(DocumentWorkflowStatus::REJECTED->value, DocumentDecision::REJECTED->value);
    }
}
```

`tests/Unit/Exceptions/DocumentWorkflowExceptionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\DocumentWorkflowException;
use PHPUnit\Framework\TestCase;

class DocumentWorkflowExceptionTest extends TestCase
{
    public function test_invalid_submit_transition_has_reason_code_and_current_status_in_message(): void
    {
        $e = DocumentWorkflowException::invalidSubmitTransition('approved');

        $this->assertSame('INVALID_SUBMIT_TRANSITION', $e->reasonCode);
        $this->assertStringContainsString('approved', $e->getMessage());
    }

    public function test_invalid_decision_transition_has_reason_code_and_current_status_in_message(): void
    {
        $e = DocumentWorkflowException::invalidDecisionTransition('draft');

        $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        $this->assertStringContainsString('draft', $e->getMessage());
    }

    public function test_document_not_found_has_reason_code(): void
    {
        $e = DocumentWorkflowException::documentNotFound();

        $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DocumentWorkflowStatusTest`
Expected: FAIL with `Class "App\Enums\DocumentWorkflowStatus" not found`

Run: `php artisan test --filter=DocumentDecisionTest`
Expected: FAIL with `Class "App\Enums\DocumentDecision" not found`

Run: `php artisan test --filter=DocumentWorkflowExceptionTest`
Expected: FAIL with `Class "App\Exceptions\DocumentWorkflowException" not found`

- [ ] **Step 3: Create `app/Enums/DocumentWorkflowStatus.php`**

```php
<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentWorkflowStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Chỉ DocumentWorkflowService được ghi 3 giá trị này. store()/update()/
     * createVersion() phải chặn mọi request có status đích nằm trong danh sách này.
     *
     * @return self[]
     */
    public static function reserved(): array
    {
        return [self::SUBMITTED, self::APPROVED, self::REJECTED];
    }

    /** @return string[] */
    public static function reservedValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::reserved());
    }

    public static function isReserved(string $value): bool
    {
        return in_array($value, self::reservedValues(), true);
    }
}
```

- [ ] **Step 4: Create `app/Enums/DocumentDecision.php`**

```php
<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentDecision: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function toWorkflowStatus(): DocumentWorkflowStatus
    {
        return match ($this) {
            self::APPROVED => DocumentWorkflowStatus::APPROVED,
            self::REJECTED => DocumentWorkflowStatus::REJECTED,
        };
    }
}
```

- [ ] **Step 5: Create `app/Exceptions/DocumentWorkflowException.php`**

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DocumentWorkflowException extends RuntimeException
{
    private function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidSubmitTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_SUBMIT_TRANSITION',
            "Document can only be submitted from draft status (current: {$currentStatus})."
        );
    }

    public static function invalidDecisionTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_DECISION_TRANSITION',
            "Document can only be decided from submitted status (current: {$currentStatus})."
        );
    }

    public static function documentNotFound(): self
    {
        return new self('DOCUMENT_NOT_FOUND', 'Document not found for this tenant.');
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentWorkflowStatusTest`
Expected: PASS (4/4)

Run: `php artisan test --filter=DocumentDecisionTest`
Expected: PASS (3/3)

Run: `php artisan test --filter=DocumentWorkflowExceptionTest`
Expected: PASS (3/3)

- [ ] **Step 7: Commit**

```bash
git add app/Enums/DocumentWorkflowStatus.php app/Enums/DocumentDecision.php app/Exceptions/DocumentWorkflowException.php tests/Unit/Enums/DocumentWorkflowStatusTest.php tests/Unit/Enums/DocumentDecisionTest.php tests/Unit/Exceptions/DocumentWorkflowExceptionTest.php
git commit -m "feat(documents): add shared workflow status/decision enums and exception (GAP-031 task 1)"
```

---

## Task 2: Canonical `DocumentWorkflowService`

**Files:**
- Create: `app/Services/DocumentWorkflowService.php`
- Test: `tests/Feature/Services/DocumentWorkflowServiceTest.php`

**Interfaces:**
- Consumes: `App\Enums\DocumentWorkflowStatus`, `App\Enums\DocumentDecision`, `App\Exceptions\DocumentWorkflowException` (Task 1).
- Produces: `DocumentWorkflowService::findForTenant(string $tenantId, string $documentId): ?Document`; `submit(string $tenantId, string $documentId, string $actorId): Document`; `decide(string $tenantId, string $documentId, string $actorId, DocumentDecision $decision, ?string $note): Document`. Both mutators throw `DocumentWorkflowException`. These 3 method signatures are consumed verbatim by Task 3 (API adapter) and Task 5/6 (Web adapter).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Services/DocumentWorkflowServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeDocument(array $overrides = []): Document
    {
        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->actor->id,
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
            'status' => 'draft',
            'metadata' => ['status' => 'draft'],
        ], $overrides));
    }

    public function test_submit_transitions_draft_to_submitted_with_audit_metadata(): void
    {
        $document = $this->makeDocument();

        $result = $this->service->submit((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id);

        $this->assertSame('submitted', $result->status);
        $this->assertSame('submitted', $result->metadata['status']);
        $this->assertSame((string) $this->actor->id, $result->metadata['submitted_by']);
        $this->assertNotNull($result->metadata['submitted_at']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_submit_from_non_draft_status_throws_invalid_submit_transition(): void
    {
        $document = $this->makeDocument(['status' => 'approved', 'metadata' => ['status' => 'approved']]);

        try {
            $this->service->submit((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id);
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_SUBMIT_TRANSITION', $e->reasonCode);
        }

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_submit_on_missing_document_throws_document_not_found(): void
    {
        try {
            $this->service->submit((string) $this->tenant->id, '01HZNONEXISTENTDOC00000001', (string) $this->actor->id);
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
        }
    }

    public function test_decide_approved_with_null_note_records_null_decision_note(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::APPROVED,
            null
        );

        $this->assertSame('approved', $result->status);
        $this->assertSame('approved', $result->metadata['decision']);
        $this->assertNull($result->metadata['decision_note']);
        $this->assertSame((string) $this->actor->id, $result->metadata['decision_by']);
    }

    public function test_decide_approved_with_note_records_decision_note(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::APPROVED,
            'ghi chú duyệt'
        );

        $this->assertSame('ghi chú duyệt', $result->metadata['decision_note']);
    }

    public function test_decide_rejected_with_reason_records_rejected_status(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::REJECTED,
            'lý do từ chối'
        );

        $this->assertSame('rejected', $result->status);
        $this->assertSame('rejected', $result->metadata['decision']);
        $this->assertSame('lý do từ chối', $result->metadata['decision_note']);
    }

    public function test_decide_rejected_with_null_note_is_accepted_by_service(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::REJECTED,
            null
        );

        $this->assertSame('rejected', $result->status);
        $this->assertNull($result->metadata['decision_note']);
    }

    public function test_decide_from_non_submitted_status_throws_invalid_decision_transition(): void
    {
        $document = $this->makeDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);

        try {
            $this->service->decide(
                (string) $this->tenant->id,
                (string) $document->id,
                (string) $this->actor->id,
                DocumentDecision::APPROVED,
                null
            );
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        }
    }

    public function test_decide_on_missing_document_throws_document_not_found(): void
    {
        try {
            $this->service->decide(
                (string) $this->tenant->id,
                '01HZNONEXISTENTDOC00000002',
                (string) $this->actor->id,
                DocumentDecision::APPROVED,
                null
            );
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
        }
    }

    public function test_find_for_tenant_returns_null_for_cross_tenant_document(): void
    {
        $document = $this->makeDocument();
        $otherTenant = Tenant::factory()->create();

        $this->assertNull($this->service->findForTenant((string) $otherTenant->id, (string) $document->id));
    }

    /**
     * Sequential (sqlite) — chỉ chứng minh state machine đúng ở mức application,
     * KHÔNG chứng minh khoá hàng thật (xem RfiEscalationConcurrencyTest.php:16-26
     * cho cùng lưu ý). Bằng chứng khoá hàng thật nằm ở Task 8
     * (DocumentWorkflowConcurrencyTest, 2 process MySQL độc lập).
     */
    public function test_sequential_double_decide_second_call_rejected_first_decision_persists(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $this->service->decide((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id, DocumentDecision::APPROVED, null);

        try {
            $this->service->decide((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id, DocumentDecision::REJECTED, null);
            $this->fail('Expected DocumentWorkflowException on second decide().');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        }

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'approved',
            'updated_by' => $this->actor->id,
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DocumentWorkflowServiceTest`
Expected: FAIL with `Class "App\Services\DocumentWorkflowService" not found`

- [ ] **Step 3: Create `app/Services/DocumentWorkflowService.php`**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentDecision;
use App\Enums\DocumentWorkflowStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->find($documentId);
    }

    public function submit(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId) {
            $document = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw DocumentWorkflowException::documentNotFound();
            }

            if ($document->status !== DocumentWorkflowStatus::DRAFT->value) {
                throw DocumentWorkflowException::invalidSubmitTransition($document->status);
            }

            $metadata = $document->metadata ?? [];
            $metadata['status'] = DocumentWorkflowStatus::SUBMITTED->value;
            $metadata['submitted_at'] = now()->toISOString();
            $metadata['submitted_by'] = $actorId;

            $document->forceFill([
                'status' => DocumentWorkflowStatus::SUBMITTED->value,
                'metadata' => $metadata,
                'updated_by' => $actorId,
            ])->save();

            return $document->fresh(['currentVersion']);
        });
    }

    /**
     * @param string|null $note Luôn optional — kể cả khi $decision === REJECTED.
     *   Ràng buộc "bắt buộc khi từ chối" là quy tắc form của Web, không phải
     *   quy tắc của service/API.
     */
    public function decide(
        string $tenantId,
        string $documentId,
        string $actorId,
        DocumentDecision $decision,
        ?string $note,
    ): Document {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $decision, $note) {
            $document = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw DocumentWorkflowException::documentNotFound();
            }

            if ($document->status !== DocumentWorkflowStatus::SUBMITTED->value) {
                throw DocumentWorkflowException::invalidDecisionTransition($document->status);
            }

            $metadata = $document->metadata ?? [];
            $metadata['status'] = $decision->value;
            $metadata['decision'] = $decision->value;
            $metadata['decision_at'] = now()->toISOString();
            $metadata['decision_by'] = $actorId;
            $metadata['decision_note'] = $note;

            $document->forceFill([
                'status' => $decision->value,
                'metadata' => $metadata,
                'updated_by' => $actorId,
            ])->save();

            return $document->fresh(['currentVersion']);
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentWorkflowServiceTest`
Expected: PASS (11/11)

- [ ] **Step 5: Commit**

```bash
git add app/Services/DocumentWorkflowService.php tests/Feature/Services/DocumentWorkflowServiceTest.php
git commit -m "feat(documents): add DocumentWorkflowService with transactional lockForUpdate transitions (GAP-031 task 2)"
```

---

## Task 3: Refactor `SimpleDocumentController::submit()/decision()` onto the service, switch `decision` to `document.approve`

**Files:**
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `routes/api_zena.php`
- Modify: `app/Policies/DocumentPolicy.php`
- Modify: `database/seeders/ZenaPermissionsSeeder.php` (amended — moved here from Task 6, see Global Constraints)
- Modify: `tests/Feature/Api/DocumentManagementTest.php`
- Modify: `tests/Feature/Unit/Policies/DocumentPolicyTest.php`

**Interfaces:**
- Consumes: `DocumentWorkflowService::submit()/decide()/findForTenant()` (Task 2), `DocumentWorkflowException` (Task 1), `DocumentDecision` (Task 1).
- Produces: `SimpleDocumentController::submit()/decision()` — unchanged public JSON response shape/status codes; unchanged route names (`documents.submit`, `documents.decision`); new private `resolveTenantId(): string` helper consumed by `findDocument()` and both refactored methods; `document.approve` permission code registered in `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` (consumed by Task 6's Web routes and by the `ProjectStoreRbacTest` global invariant test from this task onward).

**Verified during plan-writing:** current `routes/api_zena.php:484-485` has BOTH `submit` and `decision` on `rbac:document.update` — only `decision` (line 485) changes to `rbac:document.approve`; `submit` (line 484) is untouched. Current `DocumentPolicy::approve()` (`app/Policies/DocumentPolicy.php:113-121`) is `hasRole(['super_admin','admin','pm'])`. 3 existing canonical tests currently rely on that role check and will 403 once it becomes permission-based unless fixture permissions are updated — confirmed by reading `tests/Feature/Api/DocumentManagementTest.php:596-627,629-657,659-687`:
  - `test_canonical_decision_can_approve_submitted_document` creates the approver with `createTenantUser(..., ['admin'], ['document.view','document.update'])` — no `document.approve`.
  - `test_canonical_decision_can_reject_submitted_document` — same pattern.
  - `test_canonical_workflow_rejects_invalid_transitions` calls `$this->user->assignRole('admin')` on a user whose only granted permissions (from `setUp()`) are `document.view/create/update/delete` — `assignRole()` (`app/Traits/HasRoles.php:90-107`) attaches a role by name only, it grants **no** permissions. `document.approve` must be attached explicitly or this test's `decision()` call gets `403` instead of the `409` it asserts.

- [ ] **Step 1: Write the failing tests (fixture updates + assertions)**

In `tests/Feature/Api/DocumentManagementTest.php`, change `test_canonical_decision_can_approve_submitted_document` (around line 596-601):

```php
    public function test_canonical_decision_can_approve_submitted_document(): void
    {
        $approver = $this->createTenantUser($this->tenant, [], ['admin'], [
            'document.view',
            'document.update',
            'document.approve',
        ]);
        $this->apiAs($approver, $this->tenant);
```

Change `test_canonical_decision_can_reject_submitted_document` (around line 629-634) the same way:

```php
    public function test_canonical_decision_can_reject_submitted_document(): void
    {
        $approver = $this->createTenantUser($this->tenant, [], ['admin'], [
            'document.view',
            'document.update',
            'document.approve',
        ]);
        $this->apiAs($approver, $this->tenant);
```

Change `test_canonical_workflow_rejects_invalid_transitions` (around line 659-687) — replace the bare `assignRole()` call with an explicit `document.approve` grant on that role:

```php
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
```

In `tests/Feature/Unit/Policies/DocumentPolicyTest.php`, change `test_user_can_approve_document_with_management_role` (line 148-153) and `test_user_cannot_approve_document_without_management_role` (line 155-160) to grant/omit the permission explicitly rather than relying on role name — the policy is becoming permission-based, so these tests must attach `document.approve` via the real permission relation:

```php
    public function test_user_can_approve_document_with_management_role()
    {
        $role = $this->user->assignRole('pm');
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'document.approve'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Document approve']
        );
        $role->permissions()->syncWithoutDetaching($permission->id);

        $this->assertTrue($this->policy->approve($this->user, $this->document));
    }

    public function test_user_cannot_approve_document_without_management_role()
    {
        $this->user->assignRole('engineer');

        $this->assertFalse($this->policy->approve($this->user, $this->document));
    }
```

`test_super_admin_can_perform_all_actions` (line 162-173) also asserts `approve()` true for `super_admin` — update it the same way, granting the permission explicitly:

```php
    public function test_super_admin_can_perform_all_actions()
    {
        $role = $this->user->assignRole('super_admin');
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'document.approve'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Document approve']
        );
        $role->permissions()->syncWithoutDetaching($permission->id);

        $this->assertTrue($this->policy->view($this->user, $this->document));
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->update($this->user, $this->document));
        $this->assertTrue($this->policy->delete($this->user, $this->document));
        $this->assertTrue($this->policy->download($this->user, $this->document));
        $this->assertTrue($this->policy->approve($this->user, $this->document));
        $this->assertTrue($this->policy->forceDelete($this->user, $this->document));
    }
```

- [ ] **Step 2: Run tests to verify they fail against current code**

Run: `php artisan test --filter=DocumentManagementTest`
Expected: `test_canonical_decision_can_approve_submitted_document`, `test_canonical_decision_can_reject_submitted_document`, `test_canonical_workflow_rejects_invalid_transitions` still PASS at this point (policy hasn't changed yet — this step just proves the fixture edits didn't break anything under the OLD policy). This is expected; the RED signal for this task is the next command.

Run: `php artisan test --filter=DocumentPolicyTest`
Expected: PASS at this point too (old role-based policy still active) — proceed to Step 3 to make the actual behavior change, then re-run in Step 5.

- [ ] **Step 3: Refactor `SimpleDocumentController`**

Add imports near the top of `app/Http/Controllers/Api/SimpleDocumentController.php` (after the existing `use` block, before the class declaration):

```php
use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Services\DocumentWorkflowService;
```

Remove the 4 status constants (lines 35-38):

```php
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SUBMITTED = 'submitted';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_REJECTED = 'rejected';
```

Replace `submit()` (current lines 305-330) with:

```php
    public function submit(string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        try {
            $document = app(DocumentWorkflowService::class)->submit($tenantId, $id, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be submitted from draft status'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document submitted successfully');
    }
```

Replace `decision()` (current lines 332-369) with:

```php
    public function decision(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision' => ['required', 'string', Rule::in(array_map(fn (DocumentDecision $c) => $c->value, DocumentDecision::cases()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $decision = DocumentDecision::from($data['decision']);
        $note = $data['note'] ?? null;
        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(DocumentWorkflowService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('approve', $documentForAuth);

        try {
            $document = app(DocumentWorkflowService::class)->decide($tenantId, $id, (string) Auth::id(), $decision, $note);
        } catch (DocumentWorkflowException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('Document can only be decided from submitted status'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document decision recorded successfully');
    }
```

Add the new `resolveTenantId()` private helper and simplify `findDocument()` to use it (replace the current `findDocument()`, lines 618-627):

```php
    private function resolveTenantId(): string
    {
        $user = Auth::user();

        return (string) (app()->bound('current_tenant_id') ? app('current_tenant_id') : ($user?->tenant_id ?? ''));
    }

    private function findDocument(string $id): ?Document
    {
        return Document::query()
            ->with('currentVersion')
            ->where('tenant_id', $this->resolveTenantId())
            ->find($id);
    }
```

- [ ] **Step 4: Register `document.approve` in `ZenaPermissionsSeeder` (amended — moved from Task 6, same commit as the middleware change below)**

In `database/seeders/ZenaPermissionsSeeder.php`, insert after line 139 (`'code' => 'document.delete'`, inside the "Document management" block of `CANONICAL_PERMISSIONS`):

```php
        ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Approve or reject submitted documents'],
```

This must land in the same commit as Step 5 below — the permission must exist before (or in the same atomic change as) the moment `documents.decision` starts requiring it, and before Task 6 adds any Web route gated by `rbac:document.approve`.

- [ ] **Step 5: Change `documents.decision` middleware in `routes/api_zena.php`**

In `routes/api_zena.php`, change line 485 only (line 484 `submit` stays `rbac:document.update`, unchanged):

```php
            Route::post('/{id}/decision', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'decision'])->middleware('rbac:document.approve')->name('documents.decision');
```

- [ ] **Step 6: Change `DocumentPolicy::approve()` to permission-based**

In `app/Policies/DocumentPolicy.php`, replace `approve()` (lines 113-121):

```php
    /**
     * Determine whether the user can approve the document.
     */
    public function approve(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        return $user->hasPermission('document.approve');
    }
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentManagementTest`
Expected: PASS, all tests including `test_canonical_submit_transitions_document_from_draft_to_submitted`, `test_canonical_decision_can_approve_submitted_document`, `test_canonical_decision_can_reject_submitted_document`, `test_canonical_workflow_rejects_invalid_transitions`, `test_canonical_document_workflow_routes_are_tenant_safe`, `test_canonical_document_decision_requires_management_policy_authorization` (this last one stays green untouched — the `engineer` fixture never had `document.approve` under either policy version).

Run: `php artisan test --filter=DocumentPolicyTest`
Expected: PASS (all, including the 3 modified `approve()` tests)

Run: `php artisan test --filter=DocumentPolicySimpleTest`
Expected: PASS unchanged (this file never exercises `approve()`)

- [ ] **Step 8: Run the route/RBAC invariant test immediately (amended — per amendment #6, verification must follow the permission+route change directly, not wait until a later task)**

Run: `php artisan test --filter=ProjectStoreRbacTest`
Expected: PASS — `test_web_rbac_params_reference_real_permissions_or_roles` confirms `document.approve` (registered in Step 4) is a real seeded `Permission` code, so any Web route added in Task 6 that references `rbac:document.approve` will find it already valid. No Web route uses it yet at this point in the plan (Task 5/6 add those), so this run is a forward-consistency check, not yet exercising the new middleware itself.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Api/SimpleDocumentController.php routes/api_zena.php app/Policies/DocumentPolicy.php database/seeders/ZenaPermissionsSeeder.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Unit/Policies/DocumentPolicyTest.php
git commit -m "refactor(documents): route submit/decision through DocumentWorkflowService, gate decision by document.approve, register document.approve permission (GAP-031 task 3)"
```

---

## Task 4: Protect reserved statuses AND audit metadata on `store()`, `update()`, `createVersion()`

**Files:**
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `tests/Feature/Api/DocumentManagementTest.php`

**Interfaces:**
- Consumes: `DocumentWorkflowStatus::reservedValues()`/`isReserved()` (Task 1). No dependency on Task 2/3 code paths (this task only touches `store()`/`update()`/`createVersion()`).
- Produces: no new public interface — `store()`/`update()`/`createVersion()` keep their existing signatures and response shapes; only validation/write behavior changes for the 3 reserved status values and for a fixed set of protected audit metadata keys; new private constant `SimpleDocumentController::PROTECTED_METADATA_KEYS` consumed only by the amended `buildMetadata()`.

**Verified during plan-writing:** current `store()` validator (`:82-100`) has `'status' => 'nullable|string|max:100'` with no restriction; `update()` validator (`:521-531`) same; `createVersion()` validator (`:393-402`) same. `update()`'s unconditional write is at `:566-569` (`if (array_key_exists('status', $data)) { $updatePayload['status'] = $data['status']; $metadata['status'] = $data['status']; }`). `createVersion()`'s unconditional write is inside the `forceFill()` at `:482` (`'status' => $request->input('status', $document->status)`).

**Amended finding (audit metadata, not just the status column):** `buildMetadata(array $input, array $base = [])` (`:658-677`) does `$metadata = array_merge($metadata, $input['metadata'])` whenever `$input['metadata']` is an array — merging **any** client-supplied nested key, including `decision_by`/`decision_at`/`decision_note`/`submitted_by`/`submitted_at`/`status`, straight into the document's audit trail. `createVersion()` (`:448`) calls `$this->buildMetadata($request->all(), $document->metadata ?? [])` — the *entire raw request*, not validated data, and `createVersion()`'s validator has no `'metadata'` rule at all, so this key is currently unvalidated and unfiltered. The resulting `$metadata` is then written to **both** `documents.metadata` (via `forceFill()`) **and** the new `document_versions.metadata` row (via `createVersionRecord()`, which does `array_merge($metadata, [...])`) — so an actor holding only `document.update` could forge a fake `decision_by`/`decision_note` on a real `submitted`/`approved`/`rejected` document by calling `createVersion()` with a crafted `metadata` field, even after the column-level guard below is in place. `store()`'s validator *does* have `'metadata' => 'nullable|array'` (`:98`) and is expected to keep accepting a client-supplied metadata blob for legitimate use (existing test `test_canonical_documents_store_and_index_prove_metadata_fields` relies on `metadata.tags`/`metadata.document_type` etc. round-tripping) — so the fix must strip only the protected audit keys, not remove metadata support from `store()` altogether.

- [ ] **Step 1: Write the failing tests**

Add `use App\Enums\DocumentWorkflowStatus;` to the imports of `tests/Feature/Api/DocumentManagementTest.php` if not already present (it is not — add it after the existing `use App\Models\User;` line).

Change the 2 existing tests that currently prove the bypass. `test_can_update_document_metadata_fields` (lines 375-415) — replace `'status' => 'approved'` with `'status' => 'review'` and the matching assertions:

```php
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
```

`test_canonical_update_persists_document_metadata_fields` (lines 417-462) — same substitution:

```php
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
```

Add a new test method block (insert after `test_canonical_update_persists_document_metadata_fields`, before `test_version_history_is_retained_in_document_versions`):

```php
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

    public function test_update_on_submitted_document_silently_preserves_status_for_legacy_target(): void
    {
        $document = $this->createDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $this->apiPatch($this->namedRoute('v1.documents.update.patch', ['id' => $document->id]), [
            'status' => 'review',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

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

    public function test_create_version_on_approved_document_with_legacy_status_input_preserves_status(): void
    {
        $document = $this->createDocument(['status' => 'approved', 'metadata' => ['status' => 'approved'], 'version' => 1]);

        $this->apiPostMultipart($this->namedRoute('v1.documents.versions.store', ['id' => $document->id]), [
            'file' => $this->createValidPdfUploadedFile('approved-new-version.pdf'),
            'version' => 2,
            'status' => 'review',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_store_still_accepts_legacy_review_status(): void
    {
        $this->apiPostMultipart($this->namedRoute('v1.documents.store'), [
            'project_id' => $this->project->id,
            'title' => 'Legacy Review Store',
            'document_type' => 'drawing',
            'status' => 'review',
            'file' => $this->createValidPdfUploadedFile('legacy-review-store.pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'review');
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DocumentManagementTest`
Expected: FAIL — the 2 modified tests fail because `status` still gets written to `'approved'`/nothing-blocks-it isn't the issue (they'd actually still pass with `review` since nothing blocks legacy-to-legacy yet); the new reserved-status tests (`test_update_rejects_direct_set_of_reserved_status_approved`, `test_update_rejects_direct_set_of_reserved_status_submitted_and_rejected`, `test_update_on_submitted_document_silently_preserves_status_for_legacy_target`, `test_create_version_rejects_direct_set_of_reserved_status`, `test_create_version_on_submitted_document_preserves_status`, `test_create_version_on_approved_document_with_legacy_status_input_preserves_status`, `test_store_rejects_direct_creation_with_reserved_statuses`) FAIL because no guard exists yet (writes succeed / return 200/201 instead of 422, or status changes when it should be preserved); the 2 new audit-metadata tests (`test_create_version_cannot_forge_workflow_audit_metadata`, `test_store_strips_protected_audit_keys_from_client_supplied_metadata`) FAIL because `buildMetadata()` currently merges the forged keys straight through.

- [ ] **Step 3: Add `use App\Enums\DocumentWorkflowStatus;` import to `SimpleDocumentController`**

Add alongside the imports added in Task 3.

- [ ] **Step 4: Add the reserved-status validation rule to `store()`**

In `store()`'s validator (`:82-100`), change the `status` rule:

```php
            'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
```

No other change to `store()` — the default `'status' => $data['status'] ?? 'active'` line stays exactly as-is.

- [ ] **Step 5: Add the reserved-status guard to `update()`**

In `update()`'s validator (`:521-531`), change the `status` rule the same way:

```php
            'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
```

Replace the status-handling block (`:566-569`):

```php
        if (array_key_exists('status', $data)) {
            if (!DocumentWorkflowStatus::isReserved($document->status)) {
                $updatePayload['status'] = $data['status'];
                $metadata['status'] = $data['status'];
            }
            // Document hiện đang ở trạng thái workflow (submitted/approved/rejected) —
            // generic update KHÔNG được đổi status, kể cả sang giá trị legacy hợp lệ.
            // Field status bị bỏ qua âm thầm; các field khác trong $data vẫn áp dụng bình thường.
        }
```

- [ ] **Step 6: Add the reserved-status guard to `createVersion()`, using validated data instead of `$request->all()` (amended)**

In `createVersion()`'s validator (`:393-402`), change the `status` rule the same way:

```php
            'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())],
```

`createVersion()` currently builds `$data`/`$user` earlier in the method but calls `$this->buildMetadata($request->all(), $document->metadata ?? [])` (`:448`) using the raw request instead of `$validator->validated()`. Change that call (still at the same point in the method, before the `DB::transaction()` block) to:

```php
        $data = $validator->validated();
        $metadata = $this->buildMetadata($data, $document->metadata ?? []);
```

This alone closes the nested-`metadata` forgery vector for `createVersion()` specifically, since its validator has no `'metadata'` rule — `$data` never contains that key, so `buildMetadata()`'s nested-merge branch never triggers here. (Step 6b below adds a second, independent layer inside `buildMetadata()` itself, so this stays closed even if a future change adds a `'metadata'` validation rule to `createVersion()`.)

In the `DB::transaction()` closure, before the `forceFill()` call, compute the target status and force `metadata.status` to match it (keeping the column and the metadata JSON consistent — amended per audit-metadata finding), using it in place of the direct `$request->input('status', $document->status)` at line 482:

```php
        $document = DB::transaction(function () use ($document, $fileInfo, $fileType, $metadata, $request, $user, $versionNumber) {
            $targetStatus = DocumentWorkflowStatus::isReserved($document->status)
                ? $document->status
                : $request->input('status', $document->status);

            $metadata['status'] = $targetStatus;

            $version = $this->createVersionRecord(
                $document,
                $versionNumber,
                $user->id,
                $fileInfo['path'],
                $fileInfo['original_name'],
                $fileInfo['mime_type'],
                (int) $fileInfo['size'],
                $metadata,
                $request->input('change_notes')
            );

            $document->forceFill([
                'uploaded_by' => $user->id,
                'updated_by' => $user->id,
                'original_name' => $fileInfo['original_name'],
                'file_path' => $fileInfo['path'],
                'file_type' => $fileType,
                'mime_type' => $fileInfo['mime_type'],
                'file_size' => (int) $fileInfo['size'],
                'file_hash' => Str::ulid(),
                'document_type' => $request->input('document_type', $document->document_type),
                'discipline' => $request->input('discipline', $document->discipline),
                'package' => $request->input('package', $document->package),
                'revision' => $request->input('revision', $document->revision),
                'status' => $targetStatus,
                'category' => $request->input('document_type', $document->document_type) ?: $document->category,
                'description' => $document->description,
                'metadata' => $metadata,
                'version' => $versionNumber,
                'current_version_id' => $version->id,
            ])->save();

            return $document->fresh(['currentVersion']);
        });
```

Note `$version` is now created **before** `forceFill()` inside the closure (moved up from its original position) so `createVersionRecord()` receives `$metadata` with `status` already forced to `$targetStatus` — both the parent document row and the new `DocumentVersion` row end up with the identical, guarded status value.

- [ ] **Step 6b: Add `PROTECTED_METADATA_KEYS` and rewrite `buildMetadata()` (amended — second, independent layer of protection for the full audit key set, not only `status`)**

Add a new private constant near the existing `LINKABLE_MODELS` constant (`:39-44`):

```php
    private const PROTECTED_METADATA_KEYS = [
        'status',
        'submitted_by',
        'submitted_at',
        'decision',
        'decision_by',
        'decision_at',
        'decision_note',
    ];
```

Add the import `use Illuminate\Support\Arr;` alongside the other `use` statements.

Replace `buildMetadata()` (`:658-677`):

```php
    private function buildMetadata(array $input, array $base = []): array
    {
        $metadata = $base;

        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $metadata = array_merge($metadata, Arr::except($input['metadata'], self::PROTECTED_METADATA_KEYS));
        }

        foreach (['document_type', 'discipline', 'package', 'status', 'revision'] as $field) {
            if (array_key_exists($field, $input)) {
                $metadata[$field] = $input[$field];
            }
        }

        if (array_key_exists('tags', $input)) {
            $metadata['tags'] = $input['tags'] ?? [];
        }

        return $metadata;
    }
```

The `foreach` loop's own `$metadata['status'] = $input['status']` assignment is unaffected by `PROTECTED_METADATA_KEYS` (that list only filters the *nested* `metadata` blob's keys, not the controlled top-level `status` field, which is already validated by the `Rule::notIn()` guard added in Steps 4-6 and — for `createVersion()` — overridden again by Step 6's `$metadata['status'] = $targetStatus` after this call returns). This means `store()` keeps accepting a legitimate nested `metadata` array (e.g. `tags`, `document_type`) exactly as before — only the 7 protected keys are now always stripped from the client-supplied portion, regardless of which endpoint calls it.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentManagementTest`
Expected: PASS, all tests (existing + the 11 new ones added in Step 1: 9 reserved-status tests + 2 audit-metadata forgery tests).

- [ ] **Step 8: Run the malicious-metadata regression tests in isolation (amended — per amendment #6, an explicit focused pass immediately after the write-protection change, before moving to the next task)**

Run: `php artisan test --filter=test_create_version_cannot_forge_workflow_audit_metadata`
Expected: PASS

Run: `php artisan test --filter=test_store_strips_protected_audit_keys_from_client_supplied_metadata`
Expected: PASS

Run: `php artisan test --filter=DocumentVersioningTest`
Expected: PASS unchanged — confirms the `buildMetadata()` rewrite and the `createVersion()` reordering (version created before `forceFill()`) do not regress the pre-existing version-history behavior this file already covers.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Api/SimpleDocumentController.php tests/Feature/Api/DocumentManagementTest.php
git commit -m "fix(documents): reserve submitted/approved/rejected from store/update/createVersion, strip forged audit metadata keys (GAP-031 task 4)"
```

---

## Task 5: Web upload defaults to `draft` + Web submit surface

**Files:**
- Create: `app/Http/Controllers/Web/DocumentWorkflowController.php`
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/documents/index.blade.php`
- Test: `tests/Feature/Web/DocumentWorkflowControllerTest.php`

**Interfaces:**
- Consumes: `DocumentWorkflowService::submit()/findForTenant()` (Task 2), `DocumentWorkflowException` (Task 1). Does **not** call `SimpleDocumentController` — calls the service directly (spec §2 item 4).
- Produces: `DocumentWorkflowController::submit(string $documentId): RedirectResponse`; route name `app.documents.workflow.submit`. This class gains `approve()`/`reject()` methods in Task 6 — only `submit()` and the constructor are created here.

**Verified during plan-writing:** `DocumentController::store()` (`app/Http/Controllers/Web/DocumentController.php:82-128`) builds `$apiRequest` at `:94-101` from `$request->only(['title', 'project_id', 'document_type', 'description'])` — no `status` key, so it currently falls through to `SimpleDocumentController::store()`'s `'status' => $data['status'] ?? 'active'` default. `documents/index.blade.php` (`resources/views/documents/index.blade.php:50-63`) currently has no "Hành động" column.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Web/DocumentWorkflowControllerTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentWorkflowControllerTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        $uploader = User::factory()->create(['tenant_id' => $this->tenant->id]);

        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $uploader->id,
            'created_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'status' => 'draft',
            'metadata' => ['status' => 'draft'],
        ], $overrides));
    }

    public function test_submit_by_actor_with_document_update_transitions_draft_to_submitted(): void
    {
        $document = $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_submit_on_non_draft_document_shows_error_and_does_not_mutate(): void
    {
        $document = $this->makeDocument(['status' => 'approved', 'metadata' => ['status' => 'approved']]);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_submit_without_document_update_permission_is_blocked(): void
    {
        $document = $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['viewer'], ['document.view']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertStatus(302);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_submit_on_cross_tenant_document_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUploader = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherUploader->id,
            'created_by' => $otherUploader->id,
            'updated_by' => $otherUploader->id,
            'status' => 'draft',
        ]);

        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $foreignDocument->id]));

        $response->assertNotFound();
    }

    public function test_web_upload_creates_document_in_draft_status(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.create']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post('/app/documents', [
                'title' => 'Web Upload Draft Proof',
                'project_id' => $this->project->id,
                'document_type' => 'drawing',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('web-upload.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect('/app/documents');
        $this->assertDatabaseHas('documents', [
            'title' => 'Web Upload Draft Proof',
            'status' => 'draft',
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DocumentWorkflowControllerTest`
Expected: FAIL — `test_submit_*` fail with `Route [app.documents.workflow.submit] not defined`; `test_web_upload_creates_document_in_draft_status` FAILs its `assertDatabaseHas` (current Web `store()` still defaults to `active`).

- [ ] **Step 3: Create `app/Http/Controllers/Web/DocumentWorkflowController.php`**

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\DocumentWorkflowException;
use App\Http\Controllers\Controller;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DocumentWorkflowController extends Controller
{
    public function __construct(private readonly DocumentWorkflowService $workflow)
    {
    }

    public function submit(string $documentId): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $document = $this->workflow->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->workflow->submit($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể gửi duyệt: tài liệu không ở trạng thái nháp.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã gửi tài liệu để duyệt.');
    }
}
```

- [ ] **Step 4: Add the route in `routes/web.php`**

In the `Route::prefix('app')->name('app.')->middleware(['auth', 'tenant.isolation'])` group (same group as the existing `documents.*` routes, after line 418):

```php
    Route::post('/documents/{document}/submit', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'submit'])->middleware('rbac:document.update')->name('documents.workflow.submit');
```

- [ ] **Step 5: Make Web `store()` force `status=draft`**

In `app/Http/Controllers/Web/DocumentController.php::store()`, change the `$apiRequest` construction (`:94-101`):

```php
        // Multipart: dựng request thủ công để giữ uploaded files
        $apiRequest = Request::create(
            $request->fullUrl(),
            'POST',
            array_merge(
                $request->only(['title', 'project_id', 'document_type', 'description']),
                ['status' => \App\Enums\DocumentWorkflowStatus::DRAFT->value]
            ),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all()
        );
```

- [ ] **Step 6: Add the "Gửi duyệt" button to `resources/views/documents/index.blade.php`**

Add a "Hành động" header and column (replace the `<x-ui.data-table>` block, lines 50-63):

```blade
            <x-ui.data-table :headers="['Tài liệu', 'Dự án', 'Trạng thái', 'Người tải', 'Ngày tạo', 'Hành động']">
                @foreach ($documents as $document)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-900">{{ $document->title ?? $document->name }}</div>
                            <div class="text-sm text-slate-500">{{ $document->file_name ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">{{ $document->project?->name ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$document->status ?? 'pending'" /></td>
                        <td class="text-sm text-slate-600">{{ $document->uploader?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($document->status === 'draft')
                                <form method="POST" action="{{ route('app.documents.workflow.submit', ['document' => $document->id]) }}">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-secondary">Gửi duyệt</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentWorkflowControllerTest`
Expected: PASS (5/5)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/DocumentWorkflowController.php app/Http/Controllers/Web/DocumentController.php routes/web.php resources/views/documents/index.blade.php tests/Feature/Web/DocumentWorkflowControllerTest.php
git commit -m "feat(documents): add Web submit surface, force draft status on Web upload (GAP-031 task 5)"
```

---

## Task 6: Web approve/reject surface + `approvals()` repair

**Files:**
- Modify: `app/Http/Controllers/Web/DocumentWorkflowController.php`
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Modify: `app/Models/Document.php`
- Modify: `routes/web.php`
- Modify: `resources/views/documents/approvals.blade.php`
- Test: `tests/Feature/Web/DocumentWorkflowControllerTest.php` (extend)
- Test: `tests/Feature/Web/DocumentApprovalsPageTest.php` (new)
- Test: `tests/Unit/Models/DocumentAccessorTest.php` (new — amended, see Step 4a)

**Interfaces:**
- Consumes: `DocumentWorkflowService::decide()/findForTenant()` (Task 2), `DocumentDecision` (Task 1), `document.approve` permission (registered in Task 3 — **not** registered here; this task only consumes it).
- Produces: `DocumentWorkflowController::approve(Request, string): RedirectResponse`, `::reject(Request, string): RedirectResponse`; `Document::getDecisionByIdAttribute()/getDecisionAtAttribute()/getDecisionNoteAttribute()` accessors; `DocumentController::decisionUsersFor($paginatedDocuments, string $tenantId): Collection` helper consumed only by `approvals()`.

**Verified during plan-writing:** `document.approve` registration in `ZenaPermissionsSeeder` was moved to Task 3 (amended — see that task's Step 4 and this plan's Global Constraints); by the time this task runs, the permission already exists. Current `routes/web.php:418` route `documents.approvals` has **no** `rbac:*` middleware at all. Current `DocumentController::approvals()` (`:175-204`) filters `->where('is_active', true)` — column doesn't exist in the `documents` table (verified via migrations in prior spec research) and leaks `$e->getMessage()` into the view on any exception (`:201`).

**Amended serialization contract (per amendment #5), binding for Step 4a below:** the 3 new `Document` accessors must (a) never be added to `$appends` — the canonical API response for `documents.show`/`documents.decision`/etc. must not gain a new top-level computed field as a side effect of this task, only `data.metadata.decision_by` etc. (already present since Task 2/3) stays the audit surface; (b) never issue a User (or any) query — they read only `$this->metadata`, already loaded on the model instance; the actual `User` name lookup stays isolated in `DocumentController::decisionUsersFor()`, a controller-level, batched, tenant-scoped query, never inside the model; (c) return `null` safely for malformed or missing legacy metadata (a document created before this feature existed, or with `metadata` cast to `null`/a non-array) — no warning promoted to an error, no exception.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Web/DocumentWorkflowControllerTest.php` (inside the existing class, after `test_web_upload_creates_document_in_draft_status`):

```php
    public function test_approve_by_actor_with_document_approve_transitions_submitted_to_approved(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_reject_without_decision_note_fails_validation(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reject', ['document' => $document->id]), []);

        $response->assertSessionHasErrors('decision_note');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_reject_with_decision_note_transitions_submitted_to_rejected(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reject', ['document' => $document->id]), [
                'decision_note' => 'Thiếu chữ ký kỹ sư trưởng',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'rejected']);
    }

    public function test_approve_or_reject_without_document_approve_permission_is_blocked(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertStatus(302);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_approve_on_already_approved_document_shows_error_and_does_not_mutate(): void
    {
        $document = $this->makeDocument(['status' => 'approved', 'metadata' => ['status' => 'approved', 'decision_by' => 'someone-else']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }
```

`tests/Feature/Web/DocumentApprovalsPageTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentApprovalsPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        $uploader = User::factory()->create(['tenant_id' => $this->tenant->id]);

        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $uploader->id,
            'created_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'status' => 'submitted',
            'metadata' => ['status' => 'submitted'],
        ], $overrides));
    }

    public function test_approvals_page_loads_without_is_active_error(): void
    {
        $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
    }

    public function test_approvals_page_without_document_approve_permission_is_blocked(): void
    {
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertStatus(302);
    }

    public function test_approvals_page_does_not_leak_exception_message(): void
    {
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $this->partialMock(\App\Http\Controllers\Web\DocumentController::class, function ($mock) {
            $mock->shouldReceive('decisionUsersFor')->andThrow(new \RuntimeException('secret-internal-db-detail-should-never-leak'));
        });

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertDontSee('secret-internal-db-detail-should-never-leak');
    }

    public function test_approved_document_shows_decision_actor_and_note_in_list(): void
    {
        $approver = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nguyễn Văn Duyệt']);
        $this->makeDocument([
            'status' => 'approved',
            'metadata' => [
                'status' => 'approved',
                'decision' => 'approved',
                'decision_by' => (string) $approver->id,
                'decision_at' => now()->toISOString(),
                'decision_note' => 'Đạt yêu cầu kỹ thuật',
            ],
        ]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertSee('Nguyễn Văn Duyệt');
        $response->assertSee('Đạt yêu cầu kỹ thuật');
    }

    public function test_submitted_document_shows_approve_and_reject_actions(): void
    {
        $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertSee(route('app.documents.workflow.approve', ['document' => Document::first()->id]), false);
        $response->assertSee(route('app.documents.workflow.reject', ['document' => Document::first()->id]), false);
    }

    public function test_draft_document_shows_no_decision_actions(): void
    {
        $this->makeDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertDontSee(route('app.documents.workflow.approve', ['document' => Document::first()->id]), false);
    }

    public function test_decision_actor_preload_does_not_grow_query_count_with_document_count(): void
    {
        $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
        for ($i = 0; $i < 5; $i++) {
            $this->makeDocument([
                'status' => 'approved',
                'metadata' => ['status' => 'approved', 'decision_by' => (string) $approver->id, 'decision_at' => now()->toISOString()],
            ]);
        }
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'))
            ->assertOk();
        $queryCountFor5 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::flushQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $this->makeDocument([
                'status' => 'approved',
                'metadata' => ['status' => 'approved', 'decision_by' => (string) $approver->id, 'decision_at' => now()->toISOString()],
            ]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'))
            ->assertOk();
        $queryCountFor10 = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $this->assertSame($queryCountFor5, $queryCountFor10, 'Số query không được tăng theo số document (N+1).');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DocumentWorkflowControllerTest`
Expected: FAIL — `test_approve_*`/`test_reject_*` fail with `Route [app.documents.workflow.approve] not defined`.

Run: `php artisan test --filter=DocumentApprovalsPageTest`
Expected: FAIL — `test_approvals_page_loads_without_is_active_error` fails with a `QueryException` (`no such column: is_active`); `test_approvals_page_without_document_approve_permission_is_blocked` fails (route currently has no `rbac:*` gate, so it succeeds with 200 instead of 302).

**Note (amended):** `document.approve` registration in `ZenaPermissionsSeeder` was already done in Task 3, Step 4 — no seeder step here.

- [ ] **Step 3: Write the failing accessor-contract test (amended, per amendment #5)**

`tests/Unit/Models/DocumentAccessorTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Document;
use PHPUnit\Framework\TestCase;

class DocumentAccessorTest extends TestCase
{
    public function test_decision_accessors_return_null_for_null_metadata(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => null]);

        $this->assertNull($document->decision_by_id);
        $this->assertNull($document->decision_at);
        $this->assertNull($document->decision_note);
    }

    public function test_decision_accessors_return_null_when_metadata_missing_keys(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode(['status' => 'draft'])]);

        $this->assertNull($document->decision_by_id);
        $this->assertNull($document->decision_at);
        $this->assertNull($document->decision_note);
    }

    public function test_decision_accessors_read_present_values(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode([
            'decision_by' => 'user-123',
            'decision_at' => '2026-08-04T10:00:00+00:00',
            'decision_note' => 'ok',
        ])]);

        $this->assertSame('user-123', $document->decision_by_id);
        $this->assertSame('ok', $document->decision_note);
        $this->assertNotNull($document->decision_at);
        $this->assertSame('2026-08-04', $document->decision_at->format('Y-m-d'));
    }

    public function test_decision_accessors_are_not_appended_to_array_serialization(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode(['decision_by' => 'user-123'])]);

        $array = $document->toArray();

        $this->assertArrayNotHasKey('decision_by_id', $array);
        $this->assertArrayNotHasKey('decision_at', $array);
        $this->assertArrayNotHasKey('decision_note', $array);
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=DocumentAccessorTest`
Expected: FAIL with `Undefined property: App\Models\Document::$decision_by_id` (accessors don't exist yet).

- [ ] **Step 4a: Add accessors to `app/Models/Document.php` (amended — with explicit null-safety guards, per amendment #5)**

Add after the `currentVersion()` relation (after line 200):

```php
    public function getDecisionByIdAttribute(): ?string
    {
        return is_array($this->metadata) ? ($this->metadata['decision_by'] ?? null) : null;
    }

    public function getDecisionAtAttribute(): ?\Carbon\Carbon
    {
        $value = is_array($this->metadata) ? ($this->metadata['decision_at'] ?? null) : null;

        return $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function getDecisionNoteAttribute(): ?string
    {
        return is_array($this->metadata) ? ($this->metadata['decision_note'] ?? null) : null;
    }
```

Do **not** add `decision_by_id`, `decision_at`, or `decision_note` to `$appends` (`:138-144`) — leave that array exactly as-is. These accessors are for internal PHP/Blade use only (`decisionUsersFor()` in Step 5b below, and the `approvals.blade.php` view in Step 8); the canonical API JSON response for `Document` must not gain a new top-level computed field as a side effect of this task.

- [ ] **Step 4b: Run test to verify it passes**

Run: `php artisan test --filter=DocumentAccessorTest`
Expected: PASS (4/4)

Run: `php artisan test --filter=DocumentManagementTest`
Expected: PASS unchanged — confirms `data.decision_by_id`/`data.decision_at`/`data.decision_note` do **not** appear as new top-level keys anywhere in the existing canonical API response assertions (none of those tests assert on such keys today, and none should start appearing since `$appends` is untouched).

- [ ] **Step 5: Rewrite `DocumentController::approvals()` and add `decisionUsersFor()`**

Replace `approvals()` (`:175-204`) and add the new private helper after it:

```php
    /**
     * Show documents pending approval.
     */
    public function approvals(Request $request): View
    {
        try {
            $tenantId = (string) Auth::user()?->tenant_id;

            $query = Document::with(['project', 'uploader'])
                ->whereHas('project', fn ($projectQuery) => $projectQuery->where('tenant_id', $tenantId));

            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $documents = $query->orderBy('created_at', 'desc')->paginate(15);
            $projects = Project::query()->where('tenant_id', $tenantId)->select('id', 'name')->get();
            $decisionUsers = $this->decisionUsersFor($documents, $tenantId);

            return view('documents.approvals', compact('documents', 'projects', 'decisionUsers'));
        } catch (\Throwable $e) {
            report($e);

            return view('documents.approvals', [
                'documents' => collect(),
                'projects' => collect(),
                'decisionUsers' => collect(),
                'error' => 'Không thể tải danh sách tài liệu cần duyệt. Vui lòng thử lại sau.',
            ]);
        }
    }

    private function decisionUsersFor($paginatedDocuments, string $tenantId): \Illuminate\Support\Collection
    {
        $decisionUserIds = $paginatedDocuments->getCollection()
            ->pluck('decision_by_id')
            ->filter()
            ->unique()
            ->values();

        if ($decisionUserIds->isEmpty()) {
            return collect();
        }

        return \App\Models\User::query()->where('tenant_id', $tenantId)->whereIn('id', $decisionUserIds)->pluck('name', 'id');
    }
```

- [ ] **Step 6: Add `approve()`/`reject()` to `DocumentWorkflowController`**

Add to `app/Http/Controllers/Web/DocumentWorkflowController.php` (after `submit()`), and add the missing imports (`DocumentDecision`, `Request`):

```php
use App\Enums\DocumentDecision;
use Illuminate\Http\Request;
```

```php
    public function approve(Request $request, string $documentId): RedirectResponse
    {
        return $this->decide($request, $documentId, DocumentDecision::APPROVED, [
            'decision_note' => 'nullable|string|max:500',
        ]);
    }

    public function reject(Request $request, string $documentId): RedirectResponse
    {
        return $this->decide($request, $documentId, DocumentDecision::REJECTED, [
            'decision_note' => 'required|string|max:500',
        ]);
    }

    private function decide(Request $request, string $documentId, DocumentDecision $decision, array $rules): RedirectResponse
    {
        $data = $request->validate($rules);
        $tenantId = (string) Auth::user()?->tenant_id;

        $document = $this->workflow->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('approve', $document);

        try {
            $this->workflow->decide($tenantId, $documentId, (string) Auth::id(), $decision, $data['decision_note'] ?? null);
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể xử lý: tài liệu không ở trạng thái phù hợp (có thể đã được xử lý trước đó).',
                },
            ]);
        }

        return redirect()->back()->with('success', $decision === DocumentDecision::APPROVED
            ? 'Tài liệu đã được duyệt.'
            : 'Tài liệu đã bị từ chối.');
    }
```

- [ ] **Step 7: Add routes in `routes/web.php`**

Replace line 418 and add the 2 new routes (in the same `app.` group):

```php
    Route::get('/documents/approvals', [App\Http\Controllers\Web\DocumentController::class, 'approvals'])->middleware('rbac:document.approve')->name('documents.approvals');
    Route::post('/documents/{document}/approve', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'approve'])->middleware('rbac:document.approve')->name('documents.workflow.approve');
    Route::post('/documents/{document}/reject', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'reject'])->middleware('rbac:document.approve')->name('documents.workflow.reject');
```

- [ ] **Step 8: Update `resources/views/documents/approvals.blade.php`**

Replace the `<x-ui.data-table>` block (lines 46-58):

```blade
        <x-ui.card>
            <x-ui.data-table :headers="['Tài liệu', 'Dự án', 'Trạng thái', 'Người tải', 'Ngày tạo', 'Người xử lý', 'Hành động']">
                @foreach ($documents as $document)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $document->title ?? $document->name }}</td>
                        <td class="text-sm text-slate-600">{{ $document->project?->name ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$document->status ?? 'pending'" /></td>
                        <td class="text-sm text-slate-600">{{ $document->uploader?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-sm text-slate-600">
                            @if (in_array($document->status, ['approved', 'rejected'], true))
                                {{ $decisionUsers[$document->decision_by_id] ?? '—' }}
                                @if ($document->decision_at)
                                    <div class="text-xs text-slate-400">{{ $document->decision_at->format('d/m/Y H:i') }}</div>
                                @endif
                                @if ($document->decision_note)
                                    <div class="text-xs text-slate-500">{{ $document->decision_note }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($document->status === 'submitted')
                                <form method="POST" action="{{ route('app.documents.workflow.approve', ['document' => $document->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-primary">Duyệt</button>
                                </form>
                                <form method="POST" action="{{ route('app.documents.workflow.reject', ['document' => $document->id]) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="Từ chối qua danh sách duyệt" />
                                    <button type="submit" class="operator-button operator-button-secondary">Từ chối</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        </x-ui.card>
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --filter=DocumentWorkflowControllerTest`
Expected: PASS (10/10 — 5 from Task 5 + 5 new)

Run: `php artisan test --filter=DocumentApprovalsPageTest`
Expected: PASS (7/7)

Run: `php artisan test --filter=ProjectStoreRbacTest`
Expected: PASS (`test_web_rbac_params_reference_real_permissions_or_roles` stays green — `document.approve` was already seeded in Task 3)

Run: `php artisan test --filter=DocumentAccessorTest`
Expected: PASS unchanged (re-confirms the accessor contract holds after `approvals.blade.php` starts using `decision_by_id`/`decision_at`/`decision_note` in Step 8)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/DocumentWorkflowController.php app/Http/Controllers/Web/DocumentController.php app/Models/Document.php routes/web.php resources/views/documents/approvals.blade.php tests/Feature/Web/DocumentWorkflowControllerTest.php tests/Feature/Web/DocumentApprovalsPageTest.php tests/Unit/Models/DocumentAccessorTest.php
git commit -m "feat(documents): add Web approve/reject surface, repair approvals() is_active bug and exception leak, harden Document accessor contract (GAP-031 task 6)"
```

---

## Task 7: Remove dead `DocumentController::approve()/reject()` and lock ownership with an architecture test

**Files:**
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Create: `tests/Feature/Architecture/DocumentApprovalDeadMethodsRemovedTest.php`

**Interfaces:**
- Consumes: nothing new — this task only deletes code and adds a guard test. Depends on Task 6 being merged first (new adapters must be green before the old dead code is removed).

**Verified during plan-writing:** `DocumentController::approve()` and `::reject()` (`:209-262` as of the pre-Task-6 file) are never routed in `routes/web.php` (confirmed via `grep -n "document" routes/web.php` — no line references `'approve'` or `'reject'` on `DocumentController`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Architecture/DocumentApprovalDeadMethodsRemovedTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\DocumentWorkflowController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-031: DocumentController::approve()/reject() were dead code — unrouted,
 * writing to non-fillable/non-existent columns (approved_by/rejected_by/...),
 * using a 'pending' status that doesn't exist elsewhere. Replaced by
 * DocumentWorkflowController::approve()/reject() calling DocumentWorkflowService.
 * This guard blocks either the old methods or their route names from coming back.
 */
class DocumentApprovalDeadMethodsRemovedTest extends TestCase
{
    public function test_dead_approve_reject_methods_removed_from_document_controller(): void
    {
        $this->assertFalse(
            method_exists(DocumentController::class, 'approve'),
            'DocumentController::approve() phải bị xoá — thay bằng DocumentWorkflowController::approve().'
        );
        $this->assertFalse(
            method_exists(DocumentController::class, 'reject'),
            'DocumentController::reject() phải bị xoá — thay bằng DocumentWorkflowController::reject().'
        );
    }

    public function test_web_routes_do_not_reference_dead_document_controller_methods(): void
    {
        $source = File::get(base_path('routes/web.php'));

        $this->assertStringNotContainsString(
            "DocumentController::class, 'approve'",
            $source,
            'routes/web.php không được trỏ tới DocumentController::approve() (đã xoá).'
        );
        $this->assertStringNotContainsString(
            "DocumentController::class, 'reject'",
            $source,
            'routes/web.php không được trỏ tới DocumentController::reject() (đã xoá).'
        );
    }

    public function test_canonical_workflow_owns_decision_routes(): void
    {
        $this->assertTrue(Route::has('app.documents.workflow.submit'));
        $this->assertTrue(Route::has('app.documents.workflow.approve'));
        $this->assertTrue(Route::has('app.documents.workflow.reject'));

        $approveRoute = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'app.documents.workflow.approve');
        $rejectRoute = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'app.documents.workflow.reject');

        $this->assertSame(DocumentWorkflowController::class, $approveRoute->getControllerClass());
        $this->assertSame(DocumentWorkflowController::class, $rejectRoute->getControllerClass());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentApprovalDeadMethodsRemovedTest`
Expected: FAIL — `test_dead_approve_reject_methods_removed_from_document_controller` fails (`method_exists()` still true).

- [ ] **Step 3: Delete `approve()`/`reject()` from `DocumentController`**

In `app/Http/Controllers/Web/DocumentController.php`, delete the `approve()` method (the `/** Approve a document. */` block) and the `reject()` method (the `/** Reject a document. */` block) in their entirety — both currently sit between `approvals()` and `destroy()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DocumentApprovalDeadMethodsRemovedTest`
Expected: PASS (3/3)

Run: `php artisan test --filter=DocumentWorkflowControllerTest`
Expected: PASS (unaffected — those tests exercise `DocumentWorkflowController`, not the deleted methods)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/DocumentController.php tests/Feature/Architecture/DocumentApprovalDeadMethodsRemovedTest.php
git commit -m "chore(documents): delete dead DocumentController::approve()/reject(), guard against regression (GAP-031 task 7)"
```

---

## Task 8: Concurrency verification (real MySQL, two independent processes, wired as a CI merge gate)

**Files:**
- Create: `app/Console/Commands/Testing/DocumentConcurrencyTestDecide.php`
- Create: `tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php`
- Create: `scripts/ci/document-workflow-concurrency-mysql` (amended — merge-gate script, modeled on `scripts/ci/rfi-escalation-concurrency-mysql`)
- Modify: `.github/workflows/automated-testing.yml` (amended — new `document-workflow-concurrency-mysql` job)
- Modify: `scripts/ssot/baselines/skipped_tests_baseline.txt` (amended — skip-contract inventory entry)

**Interfaces:**
- Consumes: `DocumentWorkflowService::decide()` (Task 2), `DocumentDecision` (Task 1), `DocumentWorkflowException` (Task 1).
- Produces: artisan command `document:concurrency-test-decide {tenant_id} {document_id} {actor_id} {decision}`, hidden, prints `OK <status>` on success / `CONFLICT <message>` on `DocumentWorkflowException`, exit codes `0`/`1`; CI job `document-workflow-concurrency-mysql` that fails the merge if MySQL is reachable but the test still reports SKIP.

**Amended, per amendment #3/#4:** the original Task 8 left `DocumentWorkflowConcurrencyTest` as a local-only, optionally-skipped test — nothing in CI required it to pass before merge. This is now a binding gap: a "real MySQL two-process proof" that never actually runs in CI on a real MySQL service proves nothing about production safety. This task now also creates the CI wiring, modeled 1:1 on the pre-existing `scripts/ci/rfi-escalation-concurrency-mysql` script and its matching `rfi-escalation-concurrency-mysql` job in `.github/workflows/automated-testing.yml:397-490` (real `mysql:8.0` service container, preflight PDO connection check before running the suite, `grep -q 'skipped="[1-9]'` on the JUnit output to turn a false-skip into a hard CI failure). `DocumentWorkflowConcurrencyTest` also gets `@group stress` (already present) and must satisfy the repo's skip contract (`scripts/ssot/lint_tests.sh` / `composer ssot:lint`), which requires every `markTestSkipped()` call site to appear, byte-for-byte matched by qualified name/group/reason, in `scripts/ssot/baselines/skipped_tests_baseline.txt`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Proves DocumentWorkflowService::decide() serializes concurrent decisions via a
 * real row lock (SELECT ... FOR UPDATE on the documents row) — not merely an
 * application-level state check. Must run against real MySQL with two genuinely
 * independent OS processes; sequential in-process calls against sqlite (see
 * DocumentWorkflowServiceTest::test_sequential_double_decide_second_call_rejected_first_decision_persists)
 * cannot exercise cross-connection row locking and would be a false proof — this
 * test skips itself with an explicit message if the "mysql" connection is
 * unreachable, mirroring RfiEscalationConcurrencyTest.php.
 *
 * @group stress
 */
class DocumentWorkflowConcurrencyTest extends TestCase
{
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove real row-locking behavior, '
                . 'not sqlite. The "mysql" connection in config/database.php is not reachable in '
                . 'this environment (' . $e->getMessage() . '). Run this suite in an environment '
                . 'with MySQL configured before treating concurrency as verified.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                DB::connection('mysql')->table('documents')->delete();
                DB::connection('mysql')->table('projects')->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment — nothing to clean up.
        }
        parent::tearDown();
    }

    public function test_two_concurrent_decide_calls_on_the_same_submitted_document_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $uploader = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));

        $document = Document::on('mysql')->create(array_merge(
            Document::factory()->raw([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'uploaded_by' => $uploader->id,
                'created_by' => $uploader->id,
                'updated_by' => $uploader->id,
            ]),
            ['status' => 'submitted', 'metadata' => ['status' => 'submitted']]
        ));

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'approved',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'rejected',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame(
            [0, 1],
            $exitCodes,
            'Exactly one of the two concurrent decide() attempts must succeed and the other must conflict. '
            . 'A: ' . $procA->getOutput() . ' B: ' . $procB->getOutput()
        );

        $loserOutput = $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput();
        $this->assertStringContainsString(
            'CONFLICT',
            $loserOutput,
            'The losing process must fail with a clean DocumentWorkflowException (printed as "CONFLICT ..."), '
            . 'not an uncaught exception/deadlock: ' . $loserOutput
        );

        $finalRow = DB::connection('mysql')->table('documents')->where('id', $document->id)->first();
        $this->assertContains($finalRow->status, ['approved', 'rejected']);

        // Amended (per amendment #3): the final status and ALL decision audit
        // metadata must belong to the SAME winning process — not a mix where
        // one process's status write "won" but another's audit fields leaked
        // through, which would prove the two writes weren't properly
        // serialized even if the exit-code assertion above happened to pass.
        $finalMetadata = json_decode($finalRow->metadata, true);
        $this->assertSame($finalRow->status, $finalMetadata['decision'] ?? null);
        $this->assertSame((string) $uploader->id, $finalMetadata['decision_by'] ?? null);
        $this->assertNotNull($finalMetadata['decision_at'] ?? null);

        $third = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'approved',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $third->run();

        $this->assertSame(1, $third->getExitCode());
        $this->assertStringContainsString('CONFLICT', $third->getOutput());
    }
}
```

- [ ] **Step 2: Run test to verify it fails or skips correctly**

Run: `php artisan test --filter=DocumentWorkflowConcurrencyTest`
Expected: either FAIL with `Command "document:concurrency-test-decide" is not defined` (if MySQL is reachable in this environment) or SKIPPED with the explicit MySQL-unavailable message (if not) — both are valid RED states proving the command doesn't exist yet.

- [ ] **Step 3: Create `app/Console/Commands/Testing/DocumentConcurrencyTestDecide.php`**

```php
<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Services\DocumentWorkflowService;
use Illuminate\Console\Command;

/**
 * Test-support command: invokes DocumentWorkflowService::decide() from a genuinely
 * separate OS process, so concurrency tests can prove real row-locking behavior
 * instead of simulating it with sequential calls in one PHPUnit process.
 */
class DocumentConcurrencyTestDecide extends Command
{
    protected $signature = 'document:concurrency-test-decide {tenant_id} {document_id} {actor_id} {decision}';

    protected $hidden = true;

    public function handle(DocumentWorkflowService $service): int
    {
        $decision = DocumentDecision::from($this->argument('decision'));

        try {
            $document = $service->decide(
                $this->argument('tenant_id'),
                $this->argument('document_id'),
                $this->argument('actor_id'),
                $decision,
                null
            );
            $this->line('OK ' . $document->status);

            return self::SUCCESS;
        } catch (DocumentWorkflowException $e) {
            $this->line('CONFLICT ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes or skips correctly**

Run: `php artisan test --filter=DocumentWorkflowConcurrencyTest`
Expected: PASS if a real `mysql` connection is reachable in this environment; SKIPPED (not FAILED) with the explicit message otherwise. Either outcome is acceptable — a FAIL is not.

- [ ] **Step 5: Add `document.workflow.concurrency` to the skip-contract baseline (amended, per amendment #4)**

Run: `php scripts/ssot/collect_skip_inventory.php --tests-dir tests --sources-out /tmp/skip_sources.txt --inventory-out /tmp/skip_inventory.txt --violations-out /tmp/skip_violations.txt`
Expected: `/tmp/skip_violations.txt` is empty (the class-level `@group stress` docblock and the `dependency:` token already present in `skipUnlessMysqlAvailable()`'s message satisfy the contract — no violation).

Run: `grep DocumentWorkflowConcurrencyTest /tmp/skip_inventory.txt`
Expected: `DocumentWorkflowConcurrencyTest::skipUnlessMysqlAvailable|group=stress|reason=dependency:`

In `scripts/ssot/baselines/skipped_tests_baseline.txt`, insert the new line in alphabetical order — between `ButtonFormSubmissionTest::setUp|group=slow|reason=RUN_SLOW_TESTS` and `FinalSystemTest::skipUnlessStressTestsEnabled|group=stress|reason=RUN_STRESS_TESTS`:

```
DocumentWorkflowConcurrencyTest::skipUnlessMysqlAvailable|group=stress|reason=dependency:
```

- [ ] **Step 6: Run `ssot:lint` to verify the baseline is exact (amended, per amendment #6 — required immediately after adding the skip)**

Run: `composer ssot:lint`
Expected: PASS — `skipped_tests_inventory` count/diff check reports no missing/extra entries against the baseline. (4 of this script's other checks — `hardcoded_api_paths`/`denylist_hits`/`raw_user_create`/`raw_model_create*` — depend on `rg` being on `PATH` and silently no-op if it isn't; the skip-inventory check does **not** depend on `rg` — it's driven entirely by `scripts/ssot/collect_skip_inventory.php`, pure PHP — so this specific check is reliable evidence in any environment, run it before trusting a clean `composer ssot:lint` as proof for this baseline entry specifically.)

- [ ] **Step 7: Create `scripts/ci/document-workflow-concurrency-mysql` (amended — merge-gate script, per amendment #3)**

```bash
#!/usr/bin/env bash
set -euo pipefail

# Runs tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php against a
# real MySQL connection in CI. Modeled 1:1 on scripts/ci/rfi-escalation-concurrency-mysql
# (GAP-031 concurrency verification must be a real merge gate, not an
# optionally-skipped local test — see docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
# Task 8). This script's only job is to make sure MySQL IS reachable whenever
# this runs in CI, so the test actually executes for real instead of
# perpetually skipping.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

export APP_ENV=testing
export DB_CONNECTION=mysql
export PCOV_ENABLED=0
export ZENA_INVARIANTS_DB=mysql

resolve_with_precedence() {
    local default_value="$1"
    shift
    local env_var
    for env_var in "$@"; do
        local env_value
        env_value="${!env_var:-}"
        if [[ -n "$env_value" ]]; then
            printf '%s' "$env_value"
            return 0
        fi
    done
    printf '%s' "$default_value"
}

resolve_host_with_fallback() {
    local host="$1"
    if [[ "$host" != "mysql" ]]; then
        printf '%s' "$host"
        return 0
    fi

    if python3 -c 'import socket, sys; socket.gethostbyname(sys.argv[1])' "$host" >/dev/null 2>&1; then
        printf '%s' "$host"
    else
        printf '127.0.0.1'
    fi
}

RESOLVED_DB_HOST=$(resolve_with_precedence "mysql" MYSQL_HOST ZENA_MYSQL_HOST DB_HOST)
RESOLVED_DB_HOST=$(resolve_host_with_fallback "$RESOLVED_DB_HOST")
RESOLVED_DB_PORT=$(resolve_with_precedence "3306" MYSQL_PORT ZENA_MYSQL_PORT DB_PORT)
RESOLVED_DB_DATABASE=$(resolve_with_precedence "zenamanage_test" MYSQL_DATABASE ZENA_MYSQL_DATABASE DB_DATABASE)
RESOLVED_DB_USERNAME=$(resolve_with_precedence "root" MYSQL_USERNAME ZENA_MYSQL_USERNAME DB_USERNAME)
RESOLVED_DB_PASSWORD=$(resolve_with_precedence "" MYSQL_PASSWORD ZENA_MYSQL_PASSWORD DB_PASSWORD)

export DB_HOST="$RESOLVED_DB_HOST"
export DB_PORT="$RESOLVED_DB_PORT"
export DB_DATABASE="$RESOLVED_DB_DATABASE"
export DB_USERNAME="$RESOLVED_DB_USERNAME"
export DB_PASSWORD="$RESOLVED_DB_PASSWORD"

mysql_preflight_connection() {
    php -r '
try {
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: 3306;
    $db = getenv("DB_DATABASE") ?: "zenamanage_test";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $db);
    new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    printf("Preflight MySQL connection succeeded (%s:%s/%s)\n", $host, $port, $db);
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL connection preflight failed: " . $e->getMessage() . "\n");
    exit(1);
}
'
}

mysql_preflight_connection

mkdir -p resources/views storage/framework/views storage/framework/cache bootstrap/cache

php artisan optimize:clear
php artisan migrate:fresh --force

JUNIT_OUT="${RUNNER_TEMP:-/tmp}/document-workflow-concurrency-junit.xml"
./vendor/bin/phpunit tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php --log-junit "$JUNIT_OUT"

# Same contract as scripts/ci/rfi-escalation-concurrency-mysql: this script
# guarantees MySQL IS reachable via the preflight check above, so a SKIPPED
# result here means the test's own connection check disagrees with our
# preflight — fail loudly instead of reporting a false green.
if grep -q 'skipped="[1-9]' "$JUNIT_OUT" 2>/dev/null; then
    echo "ERROR: DocumentWorkflowConcurrencyTest reported a SKIP despite MySQL being reachable per preflight — this must never be a silent pass." >&2
    echo "Skip reason(s) from the junit report:" >&2
    grep -A2 '<skipped' "$JUNIT_OUT" >&2 || true
    exit 1
fi
```

Make it executable:

```bash
chmod +x scripts/ci/document-workflow-concurrency-mysql
```

- [ ] **Step 8: Add the `document-workflow-concurrency-mysql` job to `.github/workflows/automated-testing.yml` (amended, per amendment #3)**

Add a new job, placed immediately after the existing `rfi-escalation-concurrency-mysql` job (after line 490, before `feature-tests:`):

```yaml
  document-workflow-concurrency-mysql:
    name: Document Workflow Concurrency (real MySQL)
    runs-on: ubuntu-latest
    env:
      APP_ENV: testing
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: laravel
      DB_USERNAME: root
      DB_PASSWORD: root
      SUITE_NAME: Document workflow concurrency (real MySQL)

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: laravel
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=10

    steps:
    - name: Checkout code
      uses: actions/checkout@v5

    - name: Docs lint (no unsupported commands)
      run: bash scripts/ci/docs-lint.sh

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ env.PHP_VERSION }}
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, redis, gd, zip, bcmath, opcache

    - name: Cache Composer dependencies
      uses: actions/cache@v5
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-

    - name: Install Composer dependencies
      run: composer install --prefer-dist --no-progress --no-suggest

    - name: 🧱 Environment prep
      run: |
        echo "::group::Environment prep"
        cp .env.example .env
        sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
        sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
        php artisan key:generate
        for i in {1..30}; do
          if mysqladmin ping -h 127.0.0.1 -proot --silent; then
            echo 'MySQL ready'
            mysql_ready=true
            break
          fi
          sleep 2
        done
        if [ "${mysql_ready:-}" != "true" ]; then
          echo 'MySQL did not become ready in time' >&2
          exit 1
        fi
        mysql -h 127.0.0.1 -uroot -proot -e "CREATE DATABASE IF NOT EXISTS laravel;"
        echo "::endgroup::"

    - name: 🧪 Document workflow concurrency (real MySQL)
      run: |
        echo "::group::Document workflow concurrency (real MySQL)"
        ./scripts/ci/document-workflow-concurrency-mysql
        echo "::endgroup::"

    - name: Failure context
      if: failure()
      run: |
        echo "::group::Failure context — laravel.log"
        if [ -f storage/logs/laravel.log ]; then
          echo "---- storage/logs/laravel.log (last 200 lines) ----"
          tail -n 200 storage/logs/laravel.log
        else
          echo "storage/logs/laravel.log not present"
        fi
        echo "::endgroup::"

    - name: 🧾 Job summary
      if: always()
      run: |
        {
          echo "## Job summary"
          echo "- Job: ${{ job.name }}"
          echo "- PHP version: ${{ env.PHP_VERSION }}"
          echo "- Suite: ${SUITE_NAME:-n/a}"
          echo "- DB connection: ${DB_CONNECTION:-n/a}"
          echo "- Command: ./scripts/ci/document-workflow-concurrency-mysql"
        } >> "$GITHUB_STEP_SUMMARY"
```

This job runs on every push/PR exactly like `rfi-escalation-concurrency-mysql` already does (same workflow triggers, inherited from the top of `automated-testing.yml` — no separate trigger config needed since it's a job within the same workflow file) — making it a real merge gate: if MySQL is reachable in CI (it always is, via the `services.mysql` container) and the test reports SKIP instead of running for real, `document-workflow-concurrency-mysql` fails the job per the script's own check, which fails the workflow, which blocks merge under this repo's required-checks convention.

- [ ] **Step 9: Run the full local verification sequence before committing**

Run: `php artisan test --filter=DocumentWorkflowConcurrencyTest`
Expected: PASS or SKIPPED (per Step 4 — local environment may not have MySQL; this is expected and fine locally, the CI job in Step 8 is what makes it a hard gate).

Run: `composer ssot:lint`
Expected: PASS (re-confirms Step 6 after the CI file additions — those files aren't test files so they don't affect the skip inventory, this is a final sanity re-run before commit).

- [ ] **Step 10: Commit**

```bash
git add app/Console/Commands/Testing/DocumentConcurrencyTestDecide.php tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php scripts/ci/document-workflow-concurrency-mysql .github/workflows/automated-testing.yml scripts/ssot/baselines/skipped_tests_baseline.txt
git commit -m "test(documents): add real two-process MySQL concurrency proof for DocumentWorkflowService::decide as a CI merge gate (GAP-031 task 8)"
```

---

## Task 9: Operational gap register update

**Files:**
- Modify: `OPERATIONAL_GAP_REGISTER.md`

**Interfaces:**
- Consumes: nothing (documentation-only task, reflects the implemented behavior from Tasks 1-8).

**Verified during plan-writing:** current `GAP-031` row (`OPERATIONAL_GAP_REGISTER.md:39`) describes the original "missing fillable approver fields" framing, which rev 1-3 of the design spec superseded with the real divergence/authorization/integrity finding.

- [ ] **Step 1: Rewrite the `GAP-031` row**

In `OPERATIONAL_GAP_REGISTER.md`, replace line 39 (the entire `GAP-031` table row) with:

```
| GAP-031 | **RESOLVED.** Web document-approval surface (`DocumentController::approve()/reject()`) was dead, unrouted code using a `pending` status and non-fillable audit columns, fully diverged from the real canonical API workflow in `SimpleDocumentController` (`draft→submitted→approved\|rejected`, audit in `metadata` JSON). Additionally, `SimpleDocumentController::update()`/`createVersion()` permitted direct writes of `submitted`/`approved`/`rejected` status, bypassing `document.approve`/`DocumentPolicy::approve()`/`DocumentWorkflowService`/decision audit entirely — a transition-integrity hole discovered during design (rev 3). Fixed via `DocumentWorkflowService` (transactional, `lockForUpdate()`-guarded) as the single mutation owner, consumed by both `SimpleDocumentController` (API) and the new `DocumentWorkflowController` (Web); `document.approve` permission gates all decision actions and the approvals list; `store()`/`update()`/`createVersion()` reject direct writes of the 3 reserved workflow statuses. | **RESOLVED (verified)** | `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` (rev 3, design); `docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md` (implementation plan); `app/Services/DocumentWorkflowService.php`, `app/Http/Controllers/Api/SimpleDocumentController.php`, `app/Http/Controllers/Web/DocumentWorkflowController.php` (implementation) | **GAP-031 does not make `Document` eligible for Today Workspace personalized "Action Required"** — that requires a per-document designated approver, which is GAP-033, explicitly out of scope here. See GAP-032 for the broader generic-vs-workflow status semantics investigation (not closed by this gap — only the acute reserved-status bypass is closed). |
```

- [ ] **Step 2: Add the `GAP-032` row**

Add a new row immediately after the `GAP-031` row (same Tier section):

```
| GAP-032 | `Document.status` serves two incompatible purposes: "generic status" a client can set to any string via `store()`/`update()` (`active`, `review`, and any other legacy value), and "workflow status" of the `draft/submitted/approved/rejected` state machine. GAP-031 closed the acute bypass (reserved statuses can no longer be written directly), but did NOT resolve what legacy statuses like `active`/`review` should mean inside the workflow, whether they need a "re-entry" step (e.g. `active → draft`) to participate in `submit()`/`decide()`, or whether legacy status values should be normalized/migrated. | **OPEN (verified)** | `app/Http/Controllers/Api/SimpleDocumentController.php` (`store()`/`update()` still accept arbitrary non-reserved status strings); `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` §16 | Not implemented in GAP-031 by design — changing the `store()`/`update()` public contract further is a breaking change requiring its own spec. |
```

- [ ] **Step 3: Add the `GAP-033` row**

Add a new row immediately after the `GAP-032` row:

```
| GAP-033 | `Document` has no mechanism to designate a specific approver/action-owner per document — `document.approve` is a tenant/role-wide permission ("anyone with this permission in the tenant"), not "this exact person is responsible for deciding on this specific document." This is the reason Document approval was excluded from Today Workspace's personalized "Action Required" section (`docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §6.4/§7 — that section requires an actor identifiable by a specific, individual query condition, which a tenant-wide permission cannot provide). GAP-031's workflow correctness (authorization, transition integrity, audit) does not change this fact. | **OPEN (verified)** | `docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §6.4/§7; `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` §16 | Proposed direction (not implemented): a designated-approver field/table per document, or per-project-type default approver assignment — needs its own spec before Document can participate in personalized "cần tôi duyệt" surfaces. |
```

- [ ] **Step 4: Verify the register is internally consistent**

Run: `grep -n "GAP-031\|GAP-032\|GAP-033" OPERATIONAL_GAP_REGISTER.md`
Expected: 3 rows, GAP-031 marked `RESOLVED (verified)`, GAP-032 and GAP-033 marked `OPEN (verified)`, no leftover reference to the old "missing fillable fields" framing.

- [ ] **Step 5: Commit**

```bash
git add OPERATIONAL_GAP_REGISTER.md
git commit -m "docs: close GAP-031, register GAP-032/GAP-033 per implemented document approval workflow (GAP-031 task 9)"
```

---

## Task 10: Full regression, static analysis, and browser acceptance walkthrough

**Files:** none created or modified — this task only runs verification and records evidence. If the full-suite run surfaces a regression, fix it in the smallest file that owns the failure and note the fix in the task's commit (no new files expected).

**Interfaces:** consumes everything from Tasks 1-9 as a whole-branch check.

- [ ] **Step 1: Run static analysis**

Run: `./vendor/bin/phpstan analyse`
Expected: no new errors introduced by `app/Enums/DocumentWorkflowStatus.php`, `app/Enums/DocumentDecision.php`, `app/Exceptions/DocumentWorkflowException.php`, `app/Services/DocumentWorkflowService.php`, `app/Http/Controllers/Api/SimpleDocumentController.php`, `app/Http/Controllers/Web/DocumentController.php`, `app/Http/Controllers/Web/DocumentWorkflowController.php`, `app/Models/Document.php`, `app/Console/Commands/Testing/DocumentConcurrencyTestDecide.php`. If PHPStan flags anything (e.g. missing `@return` on the new accessors), add the minimal `@property`/`@return` docblock consistent with the codebase's existing PHPStan-without-Larastan convention and re-run.

- [ ] **Step 2: Run the full architecture-test suite**

Run: `php artisan test tests/Feature/Architecture`
Expected: PASS, including the pre-existing `test_web_rbac_params_reference_real_permissions_or_roles` (via `ProjectStoreRbacTest`, which also lives in `tests/Feature/Web` — run separately in Step 4) and the new `DocumentApprovalDeadMethodsRemovedTest`.

- [ ] **Step 3: Run every Document-related Feature/Unit test file**

Run: `php artisan test --filter="Document"`
Expected: PASS — covers `DocumentManagementTest`, `DocumentWorkflowServiceTest`, `DocumentWorkflowControllerTest`, `DocumentApprovalsPageTest`, `DocumentApprovalDeadMethodsRemovedTest`, `DocumentPolicyTest`, `DocumentPolicySimpleTest`, `DocumentWorkflowStatusTest`, `DocumentDecisionTest`, `DocumentWorkflowExceptionTest`, and the pre-existing `DocumentVersioningTest`/`DocumentVersioningSimpleTest`/`DocumentVersioningNoFKTest`/`DocumentVersioningDebugTest`/`DocumentApiTest`/`DocumentUploadValidationTest` (unaffected — confirm they stay green since `createVersion()`/`store()` behavior for non-reserved statuses is unchanged).

- [ ] **Step 4: Run the full suite**

Run: `php artisan test`
Expected: PASS, 0 failures. This is the authoritative regression check — it also re-confirms `ProjectStoreRbacTest` and any other route-invariant test that scans all routes globally.

- [ ] **Step 5: Run the MySQL concurrency group explicitly if a MySQL environment is available**

Run: `./vendor/bin/phpunit --group stress`
Expected: PASS for both `RfiEscalationConcurrencyTest` (pre-existing, unaffected) and `DocumentWorkflowConcurrencyTest` (Task 8) if MySQL is reachable; otherwise both SKIP with their explicit messages — record which outcome occurred, do not claim PASS if the actual outcome was SKIP.

- [ ] **Step 6: Browser acceptance walkthrough (manual, Chrome MCP or computer-use — not a substitute for Steps 1-5, confirms real UX)**

Perform this full sequence against a running dev server, using a tenant/user pair created for the walkthrough (clean up afterward, per this session's established pattern from the Today Workspace browser verification):

1. Log in as a user with `document.create` (not `document.approve`). Go to `/app/documents/create`, upload a file. Confirm redirect to `/app/documents` and the new row shows a `draft` badge.
2. On that row, click "Gửi duyệt". Confirm the row now shows `submitted` and the "Gửi duyệt" button is gone.
3. Log out, log in as a user with `document.approve`. Go to `/app/documents/approvals`. Confirm the just-submitted document appears with "Duyệt"/"Từ chối" buttons.
4. Click "Duyệt" (or "Từ chối" with a reason). Confirm the row now shows the final status, the approver's name, the timestamp, and the note (if any).
5. Attempt to POST directly to the same approve/reject URL again (e.g. via a saved form or curl with the session cookie). Confirm a generic error message is shown (not an exception message) and the document's status does not change.
6. As the `document.approve` user, confirm a document that is still `draft` or in a legacy status (e.g. `review`) shows no approve/reject buttons in `/app/documents/approvals`.
7. As a user with neither `document.update` nor `document.approve`, confirm direct navigation to `/app/documents/approvals` and direct POST to the submit/approve/reject routes are all blocked (redirect, not a 200 with the form).
8. Confirm no data from another tenant appears anywhere in this walkthrough (cross-check the approvals list and the document list against a second tenant's documents, created separately, which must never appear).

Record the outcome of each of the 8 steps in the task's completion report. Delete any demo tenant/users/documents created for this walkthrough afterward.

- [ ] **Step 7: Commit (only if Step 1 or Step 4 required a fix)**

```bash
git add -A
git commit -m "fix(documents): address regression found in full-suite verification (GAP-031 task 10)"
```

If no fix was needed, skip this step — Task 10 produces no commit on its own.

---

## Plan Self-Review (rev 2 — after 7-point amendment)

**1. Spec coverage (rev 3, every numbered section mapped to a task):**
- §1/§2 (bối cảnh, phạm vi) → Tasks 1-9 collectively implement every "trong phạm vi" item; every "ngoài phạm vi" item is respected (no migration, no `store()`/`update()` contract break for legacy statuses, no required `note`/`decision_note` at API/service level).
- §3 (state machine, reserved-status protection on all generic write paths) → Task 4 (store/update/createVersion guard, amended to also cover audit metadata) + Task 3 (submit/decision via service).
- §4 (enums) → Task 1.
- §5 (`DocumentWorkflowException`) → Task 1.
- §6 (`DocumentWorkflowService`) → Task 2.
- §7.1 (submit/decision refactor) → Task 3. §7.2-7.4 (reserved-status guards) → Task 4.
- §8 (`DocumentWorkflowController`, incl. Web submit) → Task 5 (submit) + Task 6 (approve/reject).
- §9 (routes) → Task 3 (`documents.decision` middleware + `document.approve` permission registration, amended), Task 5 (`documents.workflow.submit`), Task 6 (`documents.approvals` gate + `documents.workflow.approve/reject`).
- §10 (`DocumentPolicy::approve()`, 3+3 fixture updates, production role-mapping caveat) → Task 3 (policy + fixtures + permission seed) + Task 10 Step 6 note; the production SQL verification itself is an operational step outside code, called out as a rollout risk in Task 3.
- §11 (Web `store()` ép draft) → Task 5.
- §12 (`approvals()` no leak) → Task 6.
- §13 (audit preload, no N+1, serialization contract) → Task 6 (accessors with null-safety guards + `decisionUsersFor()` + N+1 test + `$appends`/no-query/API-shape regression tests, all amended per amendment #5).
- §14 (UI) → Task 5 (index.blade.php) + Task 6 (approvals.blade.php).
- §15 (compatibility/risk, 2 tests to fix) → Task 4.
- §16 (GAP-031/032/033 boundaries) → Task 9.
- §17 (full test plan) → every test listed in spec §17 has a corresponding test in Tasks 1-8, plus the amended audit-metadata-forgery tests (Task 4) and accessor-contract tests (Task 6); the 2 modified tests are in Task 4; the concurrency tests are split correctly (sequential in Task 2, MySQL-real in Task 8) with the "don't claim race-safety from sqlite" caveat preserved verbatim in both docblocks, and Task 8's MySQL test now also asserts the winning process's decision audit metadata, not just its status; the 5-step browser scenario is expanded to 8 steps in Task 10.

**2. Placeholder scan:** no "TBD"/"TODO"/"add appropriate error handling"/"similar to Task N" anywhere in this plan — every step has complete code or an exact command with an expected result, including the newly added CI script/workflow/baseline content in Task 8.

**3. Class/route/signature consistency across tasks:**
- `DocumentWorkflowService::submit(string $tenantId, string $documentId, string $actorId): Document` and `decide(string $tenantId, string $documentId, string $actorId, DocumentDecision $decision, ?string $note): Document` — identical signature used in Task 2 (definition), Task 3 (`SimpleDocumentController`), Task 5/6 (`DocumentWorkflowController`), Task 8 (concurrency command).
- Route names: `documents.submit`/`documents.decision` (API, unchanged), `app.documents.workflow.submit`/`app.documents.workflow.approve`/`app.documents.workflow.reject`/`app.documents.approvals` (Web) — used consistently in Tasks 5-7 route definitions, view `route()` calls, and the Task 7 architecture test.
- `DocumentWorkflowStatus::reservedValues()`/`isReserved()` — defined in Task 1, consumed identically in Task 4 (guard sites) with no signature drift; `SimpleDocumentController::PROTECTED_METADATA_KEYS` — defined and consumed only within Task 4's amended `buildMetadata()`, no other task references it.
- `document.approve` permission code — registered exactly once, in Task 3 Step 4; every later task (5, 6) only *consumes* it, confirmed by removing the duplicate registration that the original plan had placed in Task 6.

**4. GAP-032/GAP-033 not silently absorbed into GAP-031:** Task 9 writes 3 distinct rows with distinct scopes; GAP-031 marked RESOLVED only for what Tasks 1-8 actually implement (divergence, authorization, transition integrity, decision-metadata audit integrity — expanded per amendment #2 to explicitly include the audit-metadata forgery closure) — GAP-032 (legacy status semantics) and GAP-033 (designated approver) stay explicitly OPEN with their own evidence pointers, matching spec §16.

**5. Reserved-status protection covers all 3 write paths, AND audit metadata (amended scope):** confirmed in Task 4 Steps 4-6 (`store()`, `update()`, `createVersion()`, column-level) plus Steps 6-6b (`buildMetadata()` + `createVersion()`'s validated-data switch, metadata-level) plus the corresponding 11 new tests (9 column-level + 2 audit-metadata-forgery) — no path left unguarded at either the column or the metadata-JSON layer, and the fix is verified to reach both `documents.metadata` and the sibling `document_versions.metadata` row.

**6. Web submit path exists before Web approval path:** Task 5 (submit) is ordered before Task 6 (approve/reject) — matches the spec's own reasoning (a document that can never reach `submitted` makes the approval UI unreachable in practice).

**7. API response compatibility explicitly tested:** Task 3 keeps `submit()`/`decision()` response shape/status codes identical (verified by the pre-existing, unmodified assertions in `test_canonical_submit_transitions_document_from_draft_to_submitted` and `test_canonical_document_workflow_routes_are_tenant_safe`, which are not touched by this plan); Task 4's new tests assert `422`/preserved-status behavior without touching the success-path response shape for legacy statuses (`test_store_still_accepts_legacy_review_status`, `test_update_legacy_to_legacy_status_change_still_works`); Task 6's amended Step 3/4a/4b explicitly test that the 3 new `Document` accessors do **not** add a new top-level computed field to `toArray()`/API JSON output.

**8. Query preloading avoids N+1:** Task 6 Step 4a (accessors, pure metadata reads, no query, now with explicit `is_array()` null-safety) + Step 5 (`decisionUsersFor()`, single `whereIn` query) + explicit N+1 regression test (`test_decision_actor_preload_does_not_grow_query_count_with_document_count`).

**9. Real concurrency verification not represented by SQLite-only tests, AND now wired as a real CI merge gate (amended scope):** Task 2's sequential test docblock explicitly disclaims row-lock proof and points to Task 8; Task 8's test is a real two-process MySQL run modeled directly on the existing `RfiEscalationConcurrencyTest` pattern, with an explicit skip (not silent pass) when MySQL is unavailable locally — and, per amendment #3, Task 8 now also adds `scripts/ci/document-workflow-concurrency-mysql` + a dedicated `document-workflow-concurrency-mysql` CI job with a real `mysql:8.0` service container, so the proof runs on every push/PR, not only when a developer happens to have MySQL configured locally. The CI script fails loudly (not silently) if MySQL is reachable but the test still reports SKIP, mirroring the pre-existing RFI job's contract exactly.

**10. Every task ends with a focused commit:** Tasks 1-9 each have a final commit step scoped to that task's files only; Task 10 has a conditional commit only if verification surfaced a fix, otherwise explicitly produces no commit. Task 8's commit now also includes the CI script, workflow file, and skip baseline — all three are part of that task's single atomic change, not split across tasks.

**11. Skip contract respected end-to-end (new, per amendment #4):** `DocumentWorkflowConcurrencyTest` carries `@group stress` (class-level docblock) and its `markTestSkipped()` message contains the literal `dependency:` token — both required by `scripts/ssot/collect_skip_inventory.php`'s `ALLOWED_GROUPS`/`REASON_TOKENS` contract; Task 8 Step 5 adds the exact resulting inventory line to `scripts/ssot/baselines/skipped_tests_baseline.txt` in the file's existing alphabetical order, and Step 6 runs `composer ssot:lint` to prove the baseline is exact before the task's commit — not deferred to Task 10.

**12. Route/RBAC and malicious-metadata verification ordered immediately after their triggering change (new, per amendment #6):** Task 3 Step 8 runs `ProjectStoreRbacTest` right after the permission+route change (Steps 4-6), not deferred to Task 6 or Task 10; Task 4 Step 8 runs the 2 audit-metadata-forgery tests in isolation right after the `buildMetadata()`/`createVersion()` rewrite (Steps 6-6b), not folded silently into the task's general "run everything" step; Task 8 Step 6 runs `composer ssot:lint` immediately after the baseline edit (Step 5), before the CI script/job exist; Task 8 Step 9 runs the concurrency test locally one more time immediately before the CI wiring is committed.

## Implementation risks discovered while writing this plan

1. **`document.approve` permission code did not exist in `ZenaPermissionsSeeder.php`** (only `PermissionSeeder.php`, a different, apparently production-facing seeder). Registering it late (originally planned for Task 6) would have left Tasks 3-5's commits in a state where `rbac:document.approve` was already live on the API route (Task 3) without the Web-route invariant test's dependency existing yet — not itself broken (the invariant only scans `web`-middleware routes, and Task 3 adds no Web route), but inconsistent with "register a permission before anything depends on it." Per amendment #1, registration now happens in Task 3, in the same commit as the middleware/policy change, with an explicit `ProjectStoreRbacTest` run immediately after.
2. **3 existing tests silently depended on `DocumentPolicy::approve()` being role-based**, not just the 3 spec already flagged for `decision()` fixture permission updates: `tests/Feature/Unit/Policies/DocumentPolicyTest.php` has its own independent `approve()` assertions (`test_user_can_approve_document_with_management_role`, `test_user_cannot_approve_document_without_management_role`, `test_super_admin_can_perform_all_actions`) that the spec's file:line evidence (scoped to `DocumentManagementTest.php`) did not mention. Task 3 now updates this file too.
3. **`assignRole()` grants no permissions** (`app/Traits/HasRoles.php:90-107` — role-attach only). The spec's rev-2 evidence for `test_canonical_workflow_rejects_invalid_transitions` said the fix was to "gán `document.approve` trước dòng này" without specifying the mechanism; Task 3 now spells out the exact `Permission::firstOrCreate()` + `syncWithoutDetaching()` call needed, matching the pattern already used internally by `TenantUserFactoryTrait::ensurePermissionAttached()` (which is `private` and not directly callable from the test).
4. **Rollout risk carried forward from the spec, not resolved by this plan:** the spec's §10 SQL verification ("confirm which real production roles hold `document.approve`") is an operational step that must happen against real tenant data before this branch is deployed — it cannot be expressed as an automated test in this repo. Task 10 Step 6's browser walkthrough exercises the mechanism correctly but does not substitute for that production role-mapping check.
5. **`decision()`'s validation rule changed from a hardcoded `'in:approved,rejected'` string to `Rule::in(array_map(...))` derived from `DocumentDecision::cases()`** rather than Laravel's `Illuminate\Validation\Rules\Enum` class used in the spec's original code sample — this codebase has no existing precedent for the `Enum` validation rule anywhere (verified via repo-wide grep), so Task 3 uses the `Rule::in()` style already established by every other validator in this file, which is behaviorally identical but stays consistent with surrounding code.
6. **(New, discovered during this amendment) `buildMetadata()`'s nested-`metadata` merge was a second, independent forgery vector the original Task 4 missed entirely** — the column-level `Rule::notIn()`/`isReserved()` guard closes `status`, but does nothing for `metadata.decision_by`/`metadata.decision_note`/etc., which flow through a completely separate code path (`buildMetadata()`'s `array_merge($metadata, $input['metadata'])`). Closed via two independent layers: `createVersion()` now passes only `$validator->validated()` (which has no `'metadata'` key at all) instead of `$request->all()`, and `buildMetadata()` itself now strips `PROTECTED_METADATA_KEYS` from any nested `metadata` blob regardless of caller — so the fix holds even if a future change reintroduces a `'metadata'` validation rule to `createVersion()`, or if some other caller of `buildMetadata()` is added later.
7. **(New) No CI job previously made any `@group stress` test — including the pre-existing `RfiEscalationConcurrencyTest` — a hard merge-blocking gate beyond "this one job passed."** This plan does not change the RFI job's behavior, but establishes the identical pattern for `DocumentWorkflowConcurrencyTest` per amendment #3: a dedicated job with a real MySQL service container and a preflight-vs-skip contradiction check that fails loudly. This is now the second job following this pattern in the repo; a future generalization (e.g. one shared script parameterized by test file) is a reasonable follow-up but out of scope for this plan (would touch the already-passing RFI job, violating the minimal-change principle for unrelated, working code).
8. **(New) The skip-contract baseline file (`scripts/ssot/baselines/skipped_tests_baseline.txt`) is an exact-match baseline** (`check_exact_baseline`, not `check_with_baseline`'s allow-new-entries mode) — an easy mistake during execution would be to add the test without updating this file, which fails `composer ssot:lint` with a count-mismatch error that gives no hint the fix is "add one line, alphabetically, to a baseline file." Task 8 Steps 5-6 make this explicit and ordered.
