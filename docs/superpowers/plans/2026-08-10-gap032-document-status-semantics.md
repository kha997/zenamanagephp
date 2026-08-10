---
work_id: GAP-032
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-032/02-design.md
---

# GAP-032 Document Status Semantics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved separate Document Lifecycle and Approval dimensions without rewriting untouched legacy rows or weakening GAP-031 workflow, tenant, authorization, audit, concurrency, and compatibility guarantees.

**Architecture:** Use Architecture C, the accepted phased hybrid representation: nullable indexed `documents.lifecycle_status` and `documents.approval_status` columns are canonical once a document is created or explicitly touched by a supported status/workflow action; untouched rows retain `NULL` columns and resolve only recognized legacy meanings from `documents.status`. A side-effect-free `DocumentStatusResolver` owns nullable resolution and compatibility projection; `DocumentStatusService` composes that resolver with SQL predicates and canonical writes; `DocumentWorkflowService` remains the only approval-transition owner and performs locked transactional writes.

**Tech Stack:** PHP 8.2, Laravel 11, Eloquent, MySQL production migrations, SQLite feature tests, PHPUnit, Blade.

## Global Constraints

- Approved business design is Option B: separate Lifecycle and Approval dimensions.
- Lifecycle values are exactly `draft`, `in-review`, `published`, `archived`.
- Approval values are exactly `not-submitted`, `awaiting-approval`, `approved`, `rejected`.
- New API and Web documents resolve to Lifecycle `draft` and Approval `not-submitted`.
- Approval changes only through explicit Submit, Approve, Reject, Reopen-for-revision, or Reactivate-for-revision actions owned by `DocumentWorkflowService`.
- Generic writes must reject `submitted`, `awaiting-approval`, `approved`, `rejected`, and `pending`, and must strip canonical status fields/metadata keys supplied by clients.
- Every persisted compatibility projection writes identical `documents.status` and `metadata.status` values.
- Untouched legacy rows are not backfilled or normalized; unrelated edits do not materialize canonical columns or rewrite either legacy projection.
- An unrecognized legacy value resolves to nullable canonical dimensions and is ineligible for every canonical action until an explicit supported lifecycle/status action materializes canonical state.
- All state transitions are tenant-scoped, authorized, transactional, and protected with `lockForUpdate()`.
- Historical `submitted_*` and `decision_*` metadata survives reopen/reactivate; the current approval state may reset, but audit evidence is append-preserved.
- Gate 3 authorizes release/merge/deploy only; this plan does not create a Gate 3 packet or start GAP-033.

## Representation and compatibility contract

`DocumentStatusResolver` is a final, side-effect-free class with no database, request, authorization, service-container lookup, or mutation dependency. It exposes these exact interfaces:

```php
public function lifecycle(?string $rawLifecycleStatus, string $legacyStatus): ?DocumentLifecycleStatus;
public function approval(?string $rawApprovalStatus, string $legacyStatus): ?DocumentApprovalStatus;
public function project(DocumentLifecycleStatus $lifecycle, DocumentApprovalStatus $approval): string;
public function compatibilityStatus(?DocumentLifecycleStatus $lifecycle, ?DocumentApprovalStatus $approval, string $legacyStatus): string;
```

`DocumentStatusService` constructor-injects `DocumentStatusResolver` and exposes:

```php
public function lifecycle(Document $document): ?DocumentLifecycleStatus;
public function approval(Document $document): ?DocumentApprovalStatus;
public function applyLegacyStatusFilter(Builder $query, string $status): Builder;
public function applyLifecycleFilter(Builder $query, DocumentLifecycleStatus $status): Builder;
public function applyApprovalFilter(Builder $query, DocumentApprovalStatus $status): Builder;
public function writeState(Document $document, DocumentLifecycleStatus $lifecycle, DocumentApprovalStatus $approval, string $actorId): void;
```

The service is constructor-injected into controllers and workflow/lifecycle services, never into the model. `Document::toArray()` and its canonical attribute accessors instantiate/call only the pure `DocumentStatusResolver`; they never call `app()`, resolve `DocumentStatusService`, query, authorize, or mutate. The call graph is exactly `SimpleDocumentController → zenaSuccessResponse() → Eloquent Document/paginator item → Document::toArray() → DocumentStatusResolver`. `DocumentStatusService → DocumentStatusResolver` is the separate service path for transitions and filters. `writeState()` mutates the already locked model but does not save it; the owning transaction appends audit data and calls `save()` exactly once. Transition methods return `Document` refreshed after commit.

Raw and resolved attributes are deliberately distinct. The resolver and every write/precondition path read `getRawOriginal('lifecycle_status')`, `getRawOriginal('approval_status')`, and `getRawOriginal('status')`; they never read the canonical accessors recursively. `Document::lifecycle_status` and `Document::approval_status` are resolved serialized strings or `null`. `Document::toArray()` uses the resolver once to set `lifecycle_status`, `approval_status`, `status`, and `metadata.status` in the output array without saving. For an untouched recognized row the two raw canonical columns remain `NULL` while serialization exposes recognized resolved values. For an untouched unrecognized row they remain `NULL`, both serialized canonical fields are `null`, and serialized `status`/`metadata.status` remain the original legacy compatibility string. Here `null` means **legacy state not yet canonically classified**; it does not mean `draft`, `not-submitted`, or an invalid row.

Fallback for an untouched row (`lifecycle_status IS NULL` and/or `approval_status IS NULL`) is deterministic:

| Legacy `status` | Lifecycle fallback | Approval fallback |
|---|---|---|
| `active`, `draft`, `submitted`, `approved`, `rejected` | `draft` | `submitted` → `awaiting-approval`; `approved` → `approved`; `rejected` → `rejected`; otherwise `not-submitted` |
| `review` | `in-review` | `not-submitted` |
| `published` | `published` | `not-submitted` |
| `archived` | `archived` | `not-submitted` |

Any unrecognized legacy string resolves to Lifecycle `null` and Approval `null`. It remains byte-for-byte unchanged in storage and in serialized `status`/`metadata.status`; reads never rewrite the database. No `unknown` enum case is introduced.

Projection after an explicit state action is exact:

```php
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
```

`data.lifecycle_status` and `data.approval_status` expose resolved canonical strings for recognized/materialized rows and `null` for unrecognized untouched rows. `data.status` and `data.metadata.status` remain identical compatibility values, including the original unknown legacy string. Raw database columns may be `NULL` only for untouched legacy rows.

`status=pending` is read/filter-only and means Approval `awaiting-approval`. SQL filtering must remain database-side:

```php
$query->where(function (Builder $query): void {
    $query->where('approval_status', 'awaiting-approval')
        ->orWhere(function (Builder $legacy): void {
            $legacy->whereNull('approval_status')->where('status', 'submitted');
        });
});
```

Equivalent fallback predicates are required for every accepted legacy or canonical filter; no filter may load all Documents into PHP. The existing `status` query parameter remains explicitly a legacy-projection filter. New `lifecycle_status` and `approval_status` parameters query the independent canonical dimensions, because an approval-first projection can hide Lifecycle. The existing `client_approved` field means client visibility approval and is not part of GAP-032's formal Approval dimension.

Filter inputs are exact:

| Parameter | Accepted values | Untouched-row fallback |
|---|---|---|
| `status` | Any structurally valid string within the existing maximum length | Known aliases/concepts are resolver-aware: `pending|awaiting-approval → submitted`, `in-review → review`, and the other recognized values use their compatibility mapping. Every other safe string performs the historical exact `documents.status = <input>` match only for rows whose canonical columns are both `NULL`; it gains no canonical meaning. |
| `lifecycle_status` | `draft`, `in-review`, `published`, `archived` | `draft`: `active|draft|submitted|approved|rejected`; `in-review`: `review`; other values match themselves. Unknown strings match no lifecycle filter. |
| `approval_status` | `not-submitted`, `awaiting-approval`, `approved`, `rejected` | `active|draft|review|published|archived` → not-submitted; `submitted` → awaiting; `approved|rejected` → themselves. Unknown strings match no approval filter. |

Invalid canonical filter values return the existing 422 validation envelope. The legacy `status` parameter validates only structural safety (`string` plus the repository's existing maximum length), not enum membership. Every canonical fallback branch additionally requires the relevant canonical column to be `NULL`; exact-match unknown legacy filtering requires both canonical columns to be `NULL`; tenant scoping remains the outer model/query constraint.

---

### Task 1: Canonical enums, resolver, projection, and SQL filters

**Files:**
- Create: `app/Enums/DocumentLifecycleStatus.php`
- Create: `app/Enums/DocumentApprovalStatus.php`
- Create: `app/Services/DocumentStatusResolver.php`
- Create: `app/Services/DocumentStatusService.php`
- Create: `tests/Unit/Services/DocumentStatusServiceTest.php`
- Create: `tests/Feature/Documents/DocumentStatusFilterTest.php`

**Interfaces:**
- Consumes: `App\Models\Document`, `Illuminate\Database\Eloquent\Builder`.
- Produces: the pure resolver and status-service methods specified in the representation contract above.

- [ ] **Step 1: Write resolver/projection tests before implementation**

Add table-driven tests named:

```php
test_legacy_statuses_resolve_to_deterministic_canonical_dimensions()
test_unknown_legacy_status_remains_unresolved_without_being_rewritten()
test_all_canonical_combinations_project_to_the_binding_legacy_status()
test_all_filter_values_match_materialized_and_untouched_rows_without_cross_tenant_results()
test_unknown_legacy_status_does_not_match_any_canonical_lifecycle_filter()
test_unknown_legacy_status_does_not_match_any_canonical_approval_filter()
test_legacy_status_filter_can_still_exact_match_unknown_legacy_value()
test_write_state_sets_both_columns_and_identical_status_projections()
```

The Unit projection test must enumerate all 16 Lifecycle × Approval combinations and assert approval precedence. The Unit legacy test must include `active`, `draft`, `review`, `published`, `archived`, `submitted`, `approved`, `rejected`, and an unknown string whose two results are `null`. The Feature filter test uses `RefreshDatabase`, inserts materialized/recognized untouched rows plus unknown untouched same-tenant and other-tenant rows. It proves unknown rows match no canonical filter, while a bounded `status=<unknown-string>` exact-matches only the untouched same-tenant legacy row.

- [ ] **Step 2: Run the focused test and verify red**

Run: `php artisan test tests/Unit/Services/DocumentStatusServiceTest.php tests/Feature/Documents/DocumentStatusFilterTest.php`

Expected: FAIL because the two enums and `DocumentStatusService` do not exist.

- [ ] **Step 3: Implement the minimal enums and service**

Define backed string enums with only the approved values. Implement the pure `DocumentStatusResolver` and compose it into `DocumentStatusService` exactly as specified above. Unknown legacy input returns nullable resolution, never an invented enum. `writeState()` must use `forceFill()` with:

```php
$metadata = $document->metadata ?? [];
$legacy = $this->project($lifecycle, $approval);
$metadata['status'] = $legacy;
$document->forceFill([
    'lifecycle_status' => $lifecycle->value,
    'approval_status' => $approval->value,
    'status' => $legacy,
    'metadata' => $metadata,
    'updated_by' => $actorId,
]);
```

The caller owns the transaction and final `save()` so workflow audit keys and all four state representations commit atomically.

- [ ] **Step 4: Run the focused test and verify green**

Run: `php artisan test tests/Unit/Services/DocumentStatusServiceTest.php tests/Feature/Documents/DocumentStatusFilterTest.php`

Expected: PASS, including all 16 projections and SQL fallback predicates.

- [ ] **Step 5: Commit the independently reviewable resolver**

```bash
git add app/Enums/DocumentLifecycleStatus.php app/Enums/DocumentApprovalStatus.php app/Services/DocumentStatusResolver.php app/Services/DocumentStatusService.php tests/Unit/Services/DocumentStatusServiceTest.php tests/Feature/Documents/DocumentStatusFilterTest.php
git commit -m "feat(documents): define canonical status dimensions"
```

### Task 2: Add nullable canonical persistence with no backfill

**Files:**
- Create: `database/migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php`
- Create: `database/migrations/2026_08_10_230100_create_document_approval_events_table.php`
- Modify: `app/Models/Document.php`
- Create: `app/Models/DocumentApprovalEvent.php`
- Create: `tests/Feature/Documents/DocumentStatusMigrationTest.php`

**Interfaces:**
- Consumes: the enum values and resolver from Task 1.
- Produces: nullable `lifecycle_status` and `approval_status` model attributes, tenant-scoped indexes, and append-only/version-bound workflow history.

- [ ] **Step 1: Write migration tests before the migration**

Add tests:

```php
test_migration_adds_nullable_lifecycle_and_approval_columns_without_rewriting_legacy_row()
test_migration_round_trip_preserves_existing_status_and_metadata_on_sqlite()
test_document_model_exposes_resolved_canonical_values_for_null_legacy_columns()
test_unknown_legacy_status_serializes_null_canonical_dimensions_and_original_legacy_projection()
test_approval_events_schema_is_tenant_scoped_append_only_and_version_bound()
```

Insert a legacy row with `status=review`, `metadata.status=review`, run the migration, and assert both new columns are `NULL` and legacy bytes are unchanged. The round-trip test must call `down()` then `up()` using SQLite.

- [ ] **Step 2: Run the focused test and verify red**

Run: `php artisan test tests/Feature/Documents/DocumentStatusMigrationTest.php`

Expected: FAIL because the migration and model attributes do not exist.

- [ ] **Step 3: Implement safe nullable columns and model accessors**

Migration `up()`:

```php
Schema::table('documents', function (Blueprint $table): void {
    $table->string('lifecycle_status', 32)->nullable()->after('status');
    $table->string('approval_status', 32)->nullable()->after('lifecycle_status');
    $table->index(['tenant_id', 'lifecycle_status'], 'documents_tenant_lifecycle_status_index');
    $table->index(['tenant_id', 'approval_status'], 'documents_tenant_approval_status_index');
});
```

`down()` drops the named indexes before the columns. Do not execute any `UPDATE`, backfill, normalization, `NOT NULL`, enum alteration, or production-data query. Add the columns to model documentation and casts, but do not make them client mass-assignable. Canonical accessors and `Document::toArray()` call only `DocumentStatusResolver` as defined in the representation contract. They pass raw values obtained with `getRawOriginal()` and never read `$this->lifecycle_status`/`$this->approval_status` from inside their own resolution path. Tests assert recognized and unknown legacy rows keep raw canonical columns `NULL`; recognized rows serialize mapped values, while unknown rows serialize two canonical `null`s plus unchanged `status`/`metadata.status`.

The second migration creates `document_approval_events` with ULID `id`, indexed `tenant_id`, foreign `document_id`, nullable foreign `document_version_id`, `event` (`submitted|approved|rejected|reopened|reactivated`), `from_approval_status`, `to_approval_status`, `actor_id`, nullable `note`, JSON `context`, and timestamps. `DocumentApprovalEvent` exposes creation only; no update/delete workflow method may be added. Model/service validation enforces: `submitted`, `approved`, `rejected`, and `reopened` require `document_version_id`; `reactivated` permits it to be `NULL` only for an explicitly marked legacy no-current-version context. The FK stays nullable solely for that exception. Version ownership is validated against the same `document_id` and tenant before event insertion.

- [ ] **Step 4: Run SQLite migration and full migration smoke tests**

Run:

```bash
php artisan test tests/Feature/Documents/DocumentStatusMigrationTest.php
php artisan migrate:fresh --env=testing --force
```

Expected: PASS and migration exit 0 on SQLite. Then run `DB_CONNECTION=mysql php artisan test tests/Feature/Documents/DocumentStatusMigrationTest.php`; if the repository's testing MySQL credentials are unavailable, record MySQL migration parity as a blocking verification item rather than claiming it.

- [ ] **Step 5: Commit the persistence boundary**

```bash
git add database/migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php database/migrations/2026_08_10_230100_create_document_approval_events_table.php app/Models/Document.php app/Models/DocumentApprovalEvent.php tests/Feature/Documents/DocumentStatusMigrationTest.php
git commit -m "feat(documents): add nullable canonical status columns"
```

### Task 3: Protect canonical state and normalize new/API generic lifecycle input

**Files:**
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `app/Http/Controllers/Api/DesignItemController.php`
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Modify: `routes/web.php`
- Create: `app/Services/DocumentCreationService.php`
- Modify: `tests/Feature/Api/DocumentManagementTest.php`
- Modify: `tests/Feature/Api/DesignItemApiTest.php`
- Create: `tests/Feature/Web/DocumentCreationTest.php`

**Interfaces:**
- Consumes: `DocumentStatusService::writeState()`, nullable resolution from `DocumentStatusResolver`, and the canonical enums.
- Produces: `DocumentCreationService::create(array $attributes, string $tenantId, string $actorId): Document`, forgery-safe generic writes, and uniform new-document state across canonical, Web, and Design Item uploads.

- [ ] **Step 1: Write failing API security and creation tests**

Add tests:

```php
test_api_create_defaults_to_draft_and_not_submitted_with_matching_legacy_projection()
test_api_create_active_alias_still_materializes_draft_and_not_submitted()
test_api_create_rejects_review_published_archived_and_all_approval_values()
test_generic_update_maps_active_to_draft_and_review_to_in_review_without_changing_approval()
test_generic_update_rejects_submitted_awaiting_approval_approved_rejected_and_pending()
test_generic_write_rejects_unknown_status_without_mutating_existing_unknown_legacy_rows()
test_explicit_draft_action_materializes_unknown_legacy_row_as_draft_not_submitted()
test_store_update_and_create_version_strip_forged_canonical_columns_and_metadata_keys()
test_unrelated_api_edit_does_not_materialize_or_normalize_untouched_legacy_status()
test_unrelated_edit_preserves_unknown_legacy_status_and_null_canonical_columns()
test_update_and_create_version_authorize_the_tenant_scoped_locked_document()
test_cross_tenant_update_and_create_version_return_not_found_without_mutation()
test_web_create_persists_draft_not_submitted_matching_projections_and_version_snapshot()
test_every_web_document_create_route_requires_document_create_permission()
test_web_create_without_document_create_permission_is_forbidden_without_persistence()
test_design_item_first_upload_uses_canonical_creation_boundary_and_draft_not_submitted_state()
test_design_item_upload_cannot_create_or_find_a_cross_tenant_document()
```

Forgery payloads must include top-level `lifecycle_status`/`approval_status` and nested `metadata.lifecycle_status`/`metadata.approval_status`. Assert no forged value reaches the parent Document or `DocumentVersion.metadata`.

- [ ] **Step 2: Run the named tests and verify red**

Run: `php artisan test tests/Feature/Api/DocumentManagementTest.php tests/Feature/Api/DesignItemApiTest.php tests/Feature/Web/DocumentCreationTest.php`

Expected: FAIL on current default `active`, missing canonical state, or forgery protection.

- [ ] **Step 3: Implement minimal protected generic-write behavior**

Extend `PROTECTED_METADATA_KEYS` with `lifecycle_status` and `approval_status`. Never accept the top-level canonical columns in validated/mass-assigned payloads. `DocumentCreationService` accepts only server-built attributes, forces tenant/actor, and atomically materializes `draft`/`not-submitted` plus both legacy projections. `SimpleDocumentController`, Web creation, and routed `DesignItemController::uploadDocument()` must use it; Design Item lookup for an existing Document includes `tenant_id` and entity identity. Remove the duplicate unprotected Web `POST /documents` registration at `routes/web.php:541-543` (or add the identical `rbac:document.create` middleware if route reconciliation requires retaining it), and assert the final unique route requires auth, tenant isolation, and `document.create`. Omitted status, `draft`, and compatibility alias `active` are accepted at the external canonical API, while other create values fail validation. Generic update may map only `active|draft → draft` and `review|in-review → in-review`; explicitly supplying one of those supported values is user intent and materializes both canonical columns even when the existing legacy value is unknown. Therefore `status=draft` on an unknown row writes Lifecycle `draft` and Approval `not-submitted`, while `status=in-review` writes Lifecycle `in-review` and Approval `not-submitted`; aliases `active` and `review` remain equivalent. A recognized/materialized row preserves its resolved current Approval. The action invokes `writeState()` inside a transaction with a tenant-scoped `lockForUpdate()` re-read, then authorizes `update` against that locked row before mutation. `published` and `archived` require explicit actions. Unknown existing strings remain untouched on unrelated edits, but new unknown generic status writes return 422. `createVersion()` performs the same tenant-scoped locked re-read and post-lock authorization before status/snapshot work.

- [ ] **Step 4: Run API regression tests**

Run:

```bash
php artisan test tests/Feature/Api/DocumentManagementTest.php
php artisan test tests/Feature/Api/DesignItemApiTest.php
php artisan test tests/Feature/Web/DocumentCreationTest.php
php artisan test tests/Unit/Enums/DocumentWorkflowStatusTest.php
```

Expected: PASS; existing GAP-031 reserved-value and audit-forgery tests remain green.

- [ ] **Step 5: Commit the generic-write boundary**

```bash
git add app/Http/Controllers/Api/SimpleDocumentController.php app/Http/Controllers/Api/DesignItemController.php app/Http/Controllers/Web/DocumentController.php app/Services/DocumentCreationService.php routes/web.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Api/DesignItemApiTest.php tests/Feature/Web/DocumentCreationTest.php
git commit -m "fix(documents): protect canonical status writes"
```

### Task 4: Adapt locked approval workflow and preserve audit history

**Files:**
- Modify: `app/Services/DocumentWorkflowService.php`
- Modify: `app/Exceptions/DocumentWorkflowException.php`
- Modify: `app/Models/DocumentApprovalEvent.php`
- Modify: `tests/Feature/Services/DocumentWorkflowServiceTest.php`
- Create: `tests/Feature/Services/DocumentWorkflowConcurrencyTest.php`

**Interfaces:**
- Consumes: canonical resolver/projection from Task 1.
- Produces: `submit()`, `decide()`, `reopenForRevision()`, and `reactivateForRevision()` as tenant-scoped locked transactions.

- [ ] **Step 1: Write failing workflow matrix tests**

Add tests:

```php
test_submit_accepts_draft_or_in_review_only_when_not_submitted_and_preserves_lifecycle()
test_submit_rejects_each_invalid_lifecycle_or_approval_combination()
test_submit_rejects_untouched_active_until_explicit_normalization_action()
test_submit_rejects_untouched_review_until_explicit_normalization_action()
test_unknown_legacy_status_cannot_submit_or_publish_without_explicit_normalization()
test_submit_fails_closed_when_document_has_no_current_version()
test_submit_event_is_bound_to_locked_current_version()
test_approve_and_reject_require_awaiting_approval_and_preserve_lifecycle()
test_approve_event_uses_the_same_version_as_the_submitted_event()
test_reject_event_uses_the_same_version_as_the_submitted_event()
test_reopen_approved_resets_to_draft_not_submitted_and_preserves_historical_audit()
test_reopen_rejected_resets_to_draft_not_submitted_and_preserves_historical_audit()
test_reopen_event_preserves_the_decided_version_reference()
test_reactivate_archived_resets_to_draft_not_submitted_and_preserves_historical_audit()
test_reactivate_legacy_archived_document_without_current_version_records_explicit_unbound_legacy_event()
test_each_formal_approval_cycle_event_is_version_bound()
test_two_approval_cycles_preserve_the_first_cycle_event_bytes_after_reopen_and_resubmit()
test_failed_transition_rolls_back_state_projection_and_approval_event_together()
test_cross_tenant_workflow_actions_return_document_not_found_without_mutation()
test_approve_and_reject_require_document_approve_permission_and_policy_authorization()
test_concurrent_submit_allows_exactly_one_transition()
test_concurrent_approve_and_reject_allow_exactly_one_decision()
test_concurrent_submit_and_generic_update_cannot_undo_approval_entry()
test_concurrent_submit_and_version_create_cannot_commit_mixed_state()
test_concurrent_approve_or_reject_and_version_create_cannot_commit_mixed_state()
test_concurrent_version_change_cannot_change_version_under_active_approval_cycle()
```

Historical assertions must retain old `submitted_by`, `submitted_at`, `decision`, `decision_by`, `decision_at`, and `decision_note` in immutable `DocumentApprovalEvent` rows. Mutable metadata may describe the current/latest cycle, but it is not the historical source of truth. Reopen/reactivate may clear current-cycle decision keys only after the append-only event exists in the same transaction.

- [ ] **Step 2: Run service tests and verify red**

Run: `php artisan test tests/Feature/Services/DocumentWorkflowServiceTest.php`

Expected: FAIL because canonical preconditions and reopen/reactivate do not exist.

- [ ] **Step 3: Implement the minimal locked transitions**

Every method must query by both `tenant_id` and `id` inside `DB::transaction()`, call `lockForUpdate()`, resolve both dimensions after the lock, validate the exact approved precondition, append the approval event, update current audit metadata, call `writeState()`, and save once. Any unresolved dimension fails closed with an explicit domain error; an untouched unknown/`active`/`review` row must first pass through the explicit generic lifecycle normalization action in Task 3.

`submit()` accepts Lifecycle `draft|in-review` and Approval `not-submitted`, and additionally requires `documents.current_version_id !== null`. It locks/loads `Document::currentVersion()` by that exact ID and verifies the version's `document_id` equals the locked Document ID and its parent Document tenant equals the requested tenant. Missing, foreign-document, or cross-tenant identity fails closed with `DocumentWorkflowException::invalidCurrentVersion()`; Submit never fabricates a version. The `submitted` event records that locked `current_version_id`.

`decide()` accepts only `awaiting-approval`. It locates the active cycle's append-only `submitted` event under the same document lock and writes the `approved` or `rejected` event with that event's `document_version_id`, not whatever version happens to be current; missing or inconsistent submitted-version evidence fails closed. `reopenForRevision()` accepts only `approved|rejected` and writes `reopened.document_version_id` from the decision event being reopened before resetting Lifecycle to `draft` and Approval to `not-submitted`. Historical event/version identity never changes.

`reactivateForRevision()` accepts only Lifecycle `archived`. For a materialized/legacy archived record with a current version, `reactivated.document_version_id` uses `documents.current_version_id`. For a genuine legacy archived record with `current_version_id = null`, reactivation remains allowed, writes `document_version_id = null`, and sets event context exactly to `legacy_without_current_version: true`; no synthetic `DocumentVersion` is created. No other event may use a null version reference. Do not move authorization into the service or weaken adapter policy/middleware checks.

The concurrency test follows the repository's MySQL process pattern: it fails/blocks verification unless `DB_CONNECTION=mysql`, creates two independent application processes/connections synchronized at a barrier before their writes, records both exit/result payloads, and asserts exactly one winner plus one domain transition error. It covers decide-v-decide, submit-v-update, submit-v-version, approve-v-version, reject-v-version, and concurrent version-v-version.

- [ ] **Step 4: Verify SQLite behavior and real MySQL concurrency**

Run:

```bash
php artisan test tests/Feature/Services/DocumentWorkflowServiceTest.php
php artisan test tests/Feature/Services/DocumentWorkflowConcurrencyTest.php
```

Expected: service suite PASS. Concurrency proof is valid only with the test's two independent MySQL connections/processes; an SQLite or sequential substitute must be reported as insufficient.

- [ ] **Step 5: Commit workflow adaptation**

```bash
git add app/Services/DocumentWorkflowService.php app/Exceptions/DocumentWorkflowException.php app/Models/DocumentApprovalEvent.php tests/Feature/Services/DocumentWorkflowServiceTest.php tests/Feature/Services/DocumentWorkflowConcurrencyTest.php
git commit -m "feat(documents): separate approval workflow state"
```

### Task 5: Add explicit lifecycle and revision actions with adapter authorization

**Files:**
- Create: `app/Services/DocumentLifecycleService.php`
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `app/Http/Controllers/Web/DocumentWorkflowController.php`
- Modify: `routes/api_zena.php`
- Modify: `routes/api.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Api/DocumentLifecycleActionsTest.php`
- Modify: `tests/Feature/Web/DocumentWorkflowTest.php`

**Interfaces:**
- Consumes: `DocumentStatusService` and `DocumentWorkflowService::reopenForRevision()/reactivateForRevision()`.
- Produces: explicit `publish()`, `archive()`, `reopen()`, and `reactivate()` API/Web actions.

- [ ] **Step 1: Write failing action, tenant, and authorization tests**

Add tests:

```php
test_publish_allows_draft_or_in_review_with_not_submitted()
test_publish_allows_draft_or_in_review_with_approved()
test_publish_rejects_awaiting_approval_and_rejected()
test_archive_requires_published_and_preserves_approval()
test_reopen_requires_approved_or_rejected()
test_reactivate_requires_archived()
test_unresolved_legacy_row_is_ineligible_for_submit_publish_archive_approve_reject_reopen_and_reactivate()
test_lifecycle_actions_require_document_update_permission()
test_reopen_and_reactivate_require_document_update_permission()
test_cross_tenant_action_is_not_found_and_cannot_mutate_document()
test_web_actions_delegate_to_the_same_services_and_rules()
```

- [ ] **Step 2: Run focused tests and verify red**

Run: `php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php tests/Feature/Web/DocumentWorkflowTest.php`

Expected: FAIL because action methods/routes do not exist.

- [ ] **Step 3: Implement explicit action adapters and locked service transitions**

`DocumentLifecycleService::publish()` and `archive()` must use the same tenant-scoped transaction/lock pattern as approval workflow. Every action first requires non-null resolved Lifecycle and Approval; an unresolved legacy row fails closed and must use the explicit `draft|in-review` normalization path in Task 3 before any Submit, Publish, Archive, Approve, Reject, Reopen, or Reactivate action. API and Web adapters must find tenant-scoped documents and call `$this->authorize('update', $document)`; routes remain under existing authentication and `rbac:document.update` groups. Register actions on both `routes/api_zena.php` and the compatibility surface in `routes/api.php`; API-Zena names use its existing `api.zena.` group prefix, Web names use `documents.*`, and compatibility routes do not collide with either. No generic status write invokes these actions.

- [ ] **Step 4: Run action and route guardrail tests**

Run:

```bash
php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php tests/Feature/Web/DocumentWorkflowTest.php
php artisan test --filter RouteHygieneTest
php artisan test --filter TenantIsolationProjectsTest
php artisan route:list --json | php scripts/ci/route-guard.php
```

Expected: PASS; route middleware and authorization are asserted.

- [ ] **Step 5: Commit explicit actions**

```bash
git add app/Services/DocumentLifecycleService.php app/Http/Controllers/Api/SimpleDocumentController.php app/Http/Controllers/Web/DocumentWorkflowController.php routes/api_zena.php routes/api.php routes/web.php tests/Feature/Api/DocumentLifecycleActionsTest.php tests/Feature/Web/DocumentWorkflowTest.php
git commit -m "feat(documents): add explicit lifecycle actions"
```

### Task 6: Canonical serialization, deterministic filters, and Web compatibility

**Files:**
- Modify: `app/Models/Document.php`
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Modify: `resources/views/documents/index.blade.php`
- Modify: `resources/views/documents/approvals.blade.php`
- Modify: `tests/Feature/Api/DocumentManagementTest.php`
- Modify: `tests/Feature/Web/DocumentWorkflowTest.php`

**Interfaces:**
- Consumes: resolved model attributes and the three `DocumentStatusService` filter methods.
- Produces: canonical read fields, matching legacy projections, and the `pending` read alias.

- [ ] **Step 1: Write failing read/filter/UI tests**

Add tests:

```php
test_api_and_web_creation_expose_draft_and_not_submitted()
test_raw_model_serialization_exposes_resolved_canonical_dimensions_for_legacy_rows()
test_unknown_legacy_status_serializes_null_canonical_dimensions_and_original_legacy_projection()
test_every_projection_returns_equal_data_status_and_metadata_status()
test_pending_filter_returns_canonical_awaiting_and_untouched_submitted_rows_only()
test_legacy_status_filter_matches_projection_while_canonical_filters_remain_independent()
test_legacy_status_filter_can_still_exact_match_unknown_legacy_value()
test_web_waiting_filter_uses_pending_alias_without_persisting_pending()
test_web_buttons_use_canonical_dimensions_for_action_visibility()
```

- [ ] **Step 2: Run focused tests and verify red**

Run: `php artisan test tests/Feature/Api/DocumentManagementTest.php tests/Feature/Web/DocumentWorkflowTest.php --filter='(serialization|projection|pending_filter|status_filters|action_visibility|creation_expose)'`

Expected: FAIL because current reads and filters use `where('status', ...)` directly.

- [ ] **Step 3: Implement resolved serialization and database-side filters**

Implement the exact raw-model serialization call graph from the representation contract: `Document::toArray()` calls `parent::toArray()`, passes `getRawOriginal('lifecycle_status')`, `getRawOriginal('approval_status')`, and `getRawOriginal('status')` to the pure `DocumentStatusResolver`, and replaces only the returned array's `lifecycle_status`, `approval_status`, `status`, and `metadata.status`. Recognized untouched rows expose resolved canonical values while their raw columns remain null; unknown untouched rows expose canonical nulls and their original compatibility value in both legacy fields. No serialization read saves or mutates the model, and no accessor calls `DocumentStatusService` or the container. Replace direct filters with `applyLegacyStatusFilter()`, `applyLifecycleFilter()`, and `applyApprovalFilter()` according to the requested parameter. Known aliases use resolver-aware SQL predicates; any other bounded safe `status` string uses exact legacy comparison only and never canonical interpretation. Views branch on resolved nullable Lifecycle/Approval attributes and hide all canonical action buttons for unresolved rows; keep the Vietnamese `pending` option as a read alias. The approvals page defaults to `approval_status=awaiting-approval`; an explicit filter may show decided rows.

- [ ] **Step 4: Run API/Web compatibility suites**

Run:

```bash
php artisan test tests/Feature/Api/DocumentManagementTest.php
php artisan test tests/Feature/Web/DocumentWorkflowTest.php
```

Expected: PASS with `data.status === data.metadata.status` for all combinations.

- [ ] **Step 5: Commit read compatibility**

```bash
git add app/Models/Document.php app/Http/Controllers/Api/SimpleDocumentController.php app/Http/Controllers/Web/DocumentController.php resources/views/documents/index.blade.php resources/views/documents/approvals.blade.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Web/DocumentWorkflowTest.php
git commit -m "feat(documents): expose canonical status compatibility"
```

### Task 7: Version snapshot, audit, and unrelated-edit regression boundary

**Files:**
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `app/Http/Controllers/Api/DesignItemController.php`
- Create: `app/Services/DocumentVersionService.php`
- Modify: `app/Models/Document.php`
- Modify: `app/Models/DocumentVersion.php`
- Modify: `tests/Feature/Api/DocumentManagementTest.php`
- Modify: `tests/Feature/Api/DesignItemApiTest.php`
- Modify: `tests/Feature/Services/DocumentWorkflowConcurrencyTest.php`
- Create: `tests/Architecture/DocumentMutationOwnershipTest.php`

**Interfaces:**
- Consumes: resolved lifecycle/approval and legacy projection.
- Produces: `DocumentVersionService::createVersion(string $tenantId, string $documentId, string $actorId, array $versionData): DocumentVersion` and coherent immutable version snapshots without approval bypass.

- [ ] **Step 1: Write failing version/audit regression tests**

Add tests:

```php
test_version_snapshot_contains_resolved_lifecycle_approval_and_matching_legacy_projection()
test_version_creation_is_blocked_while_awaiting_approval()
test_version_creation_after_approval_or_rejection_requires_explicit_reopen()
test_version_creation_after_reopen_preserves_prior_version_bound_decision_history()
test_version_payload_cannot_forge_status_or_workflow_audit_metadata()
test_unrelated_edit_of_untouched_legacy_row_preserves_null_columns_and_exact_legacy_values()
test_version_creation_racing_with_workflow_transition_cannot_commit_mixed_snapshot()
test_concurrent_version_creation_allocates_distinct_numbers_under_lock()
test_design_item_version_upload_is_blocked_for_awaiting_approved_and_rejected_documents()
test_design_item_version_upload_is_tenant_scoped_and_produces_coherent_snapshot()
test_routed_document_mutators_use_governed_creation_version_lifecycle_or_workflow_services()
test_every_routed_app_document_mutator_uses_governed_services_or_an_evidence_allowlist()
```

- [ ] **Step 2: Run focused tests and verify red**

Run: `php artisan test tests/Feature/Api/DocumentManagementTest.php tests/Feature/Api/DesignItemApiTest.php tests/Feature/Services/DocumentWorkflowConcurrencyTest.php tests/Architecture/DocumentMutationOwnershipTest.php`

Expected: FAIL because version metadata lacks canonical dimensions and version creation is not locked against workflow state changes.

- [ ] **Step 3: Implement coherent locked snapshots**

Move version persistence into `DocumentVersionService`. It performs the tenant-scoped Document re-read, `lockForUpdate()`, adapter authorization before invocation, and `nextVersionNumber()` computation inside one transaction before resolving state or creating database records. Both `SimpleDocumentController::createVersion()` and `DesignItemController::uploadDocument()` delegate after their route middleware and resource authorization. Block version creation while Approval is `awaiting-approval`, `approved`, or `rejected`; approved/rejected content must pass through explicit Reopen first, and waiting content must complete its decision before Reopen is available. Snapshot `lifecycle_status`, `approval_status`, projected `status`, and the current approval-event/version reference into `DocumentVersion.metadata`; strip protected client keys first. Remove public `Document::createNewVersion()` and `revertToVersion()` as production mutation APIs, migrating every routed caller to the service; test fixtures may create versions directly. The architecture test builds a route→controller→service inventory and permits a direct mutation only when an explicit allowlist entry proves the class has no route, command, listener, job, or container call site. Initial candidates requiring that proof include `SecureUploadService`, `App\Http\Controllers\DocumentController`, and `Web\DocumentManagementController`; `App\Services\DocumentService` is separately classified because it uses the divergent `Src\DocumentManagement\Models\Document`. Any reachable caller must migrate, not be allowlisted. An unrelated edit with no status request must not call `writeState()`.

- [ ] **Step 4: Run version and real-concurrency tests**

Run:

```bash
php artisan test tests/Feature/Api/DocumentManagementTest.php
php artisan test tests/Feature/Api/DesignItemApiTest.php
php artisan test tests/Feature/Services/DocumentWorkflowConcurrencyTest.php
php artisan test tests/Architecture/DocumentMutationOwnershipTest.php
```

Expected: PASS, with the concurrency caveat from Task 4 enforced.

- [ ] **Step 5: Commit snapshot integrity**

```bash
git add app/Http/Controllers/Api/SimpleDocumentController.php app/Http/Controllers/Api/DesignItemController.php app/Services/DocumentVersionService.php app/Models/Document.php app/Models/DocumentVersion.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Api/DesignItemApiTest.php tests/Feature/Services/DocumentWorkflowConcurrencyTest.php tests/Architecture/DocumentMutationOwnershipTest.php
git commit -m "fix(documents): keep version status snapshots coherent"
```

### Task 8: Full verification, compatibility proof, and implementation review

**Files:**
- Modify only if verification finds an in-scope defect: files already listed in Tasks 1-7.

**Interfaces:**
- Consumes: the complete implementation.
- Produces: fresh evidence for engineering review; does not create Gate 3 or release authorization.

- [ ] **Step 1: Run focused status suites**

```bash
php artisan test tests/Unit/Services/DocumentStatusServiceTest.php
php artisan test tests/Feature/Documents/DocumentStatusMigrationTest.php
php artisan test tests/Feature/Documents/DocumentStatusFilterTest.php
php artisan test tests/Feature/Services/DocumentWorkflowServiceTest.php
php artisan test tests/Feature/Services/DocumentWorkflowConcurrencyTest.php
php artisan test tests/Feature/Api/DocumentManagementTest.php
php artisan test tests/Feature/Api/DesignItemApiTest.php
php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php
php artisan test tests/Feature/Web/DocumentWorkflowTest.php
php artisan test tests/Feature/Web/DocumentCreationTest.php
php artisan test tests/Architecture/DocumentMutationOwnershipTest.php
```

Expected: all PASS; concurrency result explicitly identifies MySQL driver and two independent connections/processes.

- [ ] **Step 2: Run repository-wide verification**

```bash
php artisan test
./vendor/bin/phpstan analyse
php scripts/ssot/owner_governance_lint.php
php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering
php artisan test --filter RouteHygieneTest
php artisan test --filter TenantIsolationProjectsTest
php artisan route:list --json | php scripts/ci/route-guard.php
DB_CONNECTION=mysql php artisan test tests/Feature/Documents/DocumentStatusMigrationTest.php tests/Feature/Services/DocumentWorkflowConcurrencyTest.php
git diff --check
```

Expected: all exit 0. Classify any pre-existing baseline failure separately; do not call the implementation ready while a new failure remains.

- [ ] **Step 3: Verify schema and forbidden-change boundaries**

```bash
git diff --name-only origin/main...HEAD
git diff -- database/migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php
rg -n "lifecycle_status|approval_status|pending|submitted" app tests database/migrations resources/views
rg -n "Document::(create|query\(\)->create)|DocumentVersion::create|createNewVersion\(|revertToVersion\(" app routes
```

Confirm: additive nullable columns plus an append-only audit-table migration, no backfill, no production query, no GAP-033 code, no Gate-3 packet, canonical fields absent from client mass-assignment, every approval mutation owned by `DocumentWorkflowService`, every routed new Document owned by `DocumentCreationService`, and every routed version mutation owned by `DocumentVersionService`.

- [ ] **Step 4: Request independent implementation review**

Dispatch a fresh reviewer with the approved Gate-2 spec, this plan, `origin/main` base SHA, implementation HEAD SHA, and the full diff. Require Critical/Important/Minor findings and fix every confirmed Critical or Important issue before presenting engineering readiness.

- [ ] **Step 5: Commit only review-driven corrections**

```bash
git add app/Enums/DocumentLifecycleStatus.php app/Enums/DocumentApprovalStatus.php app/Services/DocumentStatusResolver.php app/Services/DocumentStatusService.php app/Services/DocumentCreationService.php app/Services/DocumentVersionService.php app/Services/DocumentWorkflowService.php app/Services/DocumentLifecycleService.php app/Exceptions/DocumentWorkflowException.php app/Models/Document.php app/Models/DocumentVersion.php app/Models/DocumentApprovalEvent.php app/Http/Controllers/Api/SimpleDocumentController.php app/Http/Controllers/Api/DesignItemController.php app/Http/Controllers/Web/DocumentController.php app/Http/Controllers/Web/DocumentWorkflowController.php database/migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php database/migrations/2026_08_10_230100_create_document_approval_events_table.php routes/api_zena.php routes/api.php routes/web.php resources/views/documents/index.blade.php resources/views/documents/approvals.blade.php tests/Unit/Services/DocumentStatusServiceTest.php tests/Feature/Documents/DocumentStatusMigrationTest.php tests/Feature/Documents/DocumentStatusFilterTest.php tests/Feature/Services/DocumentWorkflowServiceTest.php tests/Feature/Services/DocumentWorkflowConcurrencyTest.php tests/Feature/Api/DocumentManagementTest.php tests/Feature/Api/DesignItemApiTest.php tests/Feature/Api/DocumentLifecycleActionsTest.php tests/Feature/Web/DocumentWorkflowTest.php tests/Feature/Web/DocumentCreationTest.php tests/Architecture/DocumentMutationOwnershipTest.php
git commit -m "fix(documents): address GAP-032 implementation review"
```

If review finds no required correction, do not create an empty commit.

## Production evidence decision

**Production DB queried: NO. Pre-implementation production evidence required: NO.** The selected migration adds two nullable columns and indexes only; it does not read, reinterpret, update, normalize, or constrain an existing row. Untouched rows retain their existing `status` and metadata exactly: recognized values use the documented fallback, while unrecognized values remain canonically unresolved until an explicit supported status action materializes state. A separately authorized production-distribution query becomes mandatory before any future backfill, `NOT NULL` conversion, destructive normalization, unknown-value classification, or removal of legacy fallback/projection.

Because current generic validation historically accepted arbitrary non-reserved strings and the approved spec says `pending` client usage is unknown, release evidence must still inventory known integrations/fixtures and, if separately authorized, production telemetry before Gate 3. That is a compatibility release gate, not a prerequisite for implementing the additive nullable architecture; implementation must preserve existing unknown rows even while rejecting new unknown writes.

## Architecture alternatives rejected

- **Metadata-only canonical state:** rejected because canonical approval queries would rely on JSON expressions without the repository's existing tenant/status indexes, the same JSON object is client-supplied on multiple paths, version snapshots duplicate it, and drift among canonical JSON keys, `documents.status`, and `metadata.status` creates avoidable security and compatibility risk.
- **Dedicated columns with immediate backfill/normalization:** rejected because production distribution is unknown and Gate 2 requires legacy rows not be invalidated retroactively. Nullable columns provide queryability and atomic canonical writes without requiring a data rewrite.
- **Selected Architecture C hybrid/phased representation:** dedicated nullable canonical columns for new/touched records, recognized legacy fallback plus unresolved unknown legacy values for untouched records, and an identical legacy projection for old clients. This has the lowest total correctness, workflow-integrity, compatibility, queryability, and data-safety risk.
