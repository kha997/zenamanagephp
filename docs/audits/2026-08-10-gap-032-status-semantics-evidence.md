# GAP-032 — Engineering Evidence Layer: `Document.status` semantics (current `main`)

> Fact-finding pass for Gate 1. Records **FACTS** only from current `main`. No solutions recommended.

## Attestation

- **Work ID:** GAP-032
- **Baseline:** `origin/main` HEAD `af443a20570e6931bc0ab64f659496a96ec513b0`
  (commit `af443a2` = Merge PR #255 OWN-2026-007). OWN-2026-007 merge confirmed to be an ancestor of HEAD.
- **Branch:** `docs/GAP-032-document-status-semantics`, created directly off `af443a2`. No reuse of GAP-031/GAP-034/OWN-2026-007 branches.
- **Method:** repository file inspection only (migrations, models, enums, controllers, service, policy, routes, views, factory, seeder, tests, governance docs). No runtime execution. Production DB **not** queried.

## Evidence provenance key

- `CANONICAL MIGRATION/SCHEMA EVIDENCE` — statements in `database/migrations/*.php` (DBAL).
- `RUNTIME/CANONICAL CODE EVIDENCE` — statements in `app/`, `src/` (live `App\` namespace only).
- `TEST EVIDENCE` — `tests/**`.
- `PRODUCTION FACTS UNKNOWN` — value of production only; stated explicitly when not derivable from repo contract.

---

## 4.1 Current persistence contract (`documents.status`)

### Canonical migration/schema evidence

- `database/migrations/2025_09_14_160324_create_zena_documents_table.php:30` — original canonical definition:
  `$table->string('status')->default('active');`
  - Type: `string` (`varchar`).
  - Default: `'active'`.
  - No enum. No `CHECK` constraint. No DB-level value restriction.
- `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` — renames `zena_documents` → `documents`. Does **not** alter the `status` column (rename only).
- `database/migrations/2026_02_06_120000_fix_documents_project_foreign_key.php:145` — SQLite rebuild path re-declares `$table->string('status')->default('active');` (identical type/default); MySQL branch only re-points `project_id` FK. No change to `status` semantics.
- `database/migrations/2025_09_20_071043_add_missing_performance_indexes.php:33-36` — adds indexes referencing `status` (`documents_project_status_index`, `documents_uploader_status_index`, `documents_type_status_index`, `documents_hash_status_index`). Indexes only; no column/constraint change.
- `database/migrations/2025_09_22_012416_optimize_documents_table_schema.php:17-18` — adds composite indexes `documents_tenant_status_index` (`tenant_id,status`) and `documents_project_category_status_index`. Indexes only; no column/constraint change.
- `database/migrations/2026_03_16_090000_add_document_center_metadata_columns.php:37` — adds index on (`tenant_id`,`status`). Index only.

**FACT:** Across every migration touching `documents`, `status` remains `varchar` `default('active')` with NO enum and NO database constraint. No migration changes the `status` column's type, default, or adds a value constraint. Values are constrained only at the application layer.

### Runtime/model evidence

- `app/Models/Document.php:30` — `@property string $status` (plain string, **no enum cast**).
- `app/Models/Document.php:123` — `'status'` is in `$fillable` (mass-assignable via `Document::create`/`->update`).
- `app/Models/Document.php:130-138` — `$casts` does **not** include `status` (no casting to enum/instance); only `metadata` is cast to `array`.
- `app/Enums/DocumentWorkflowStatus.php:5-33` — a string enum exists (`draft`,`submitted`,`approved`,`rejected`) but the model does **not** cast to it; the enum is referenced only by the controller/service for guard logic.
- **FACT:** No status machine lives on the model; transitions are enforced in controller/service code, not at the model/persistence layer.

### Runtime representation duplication (`OBSERVED CONTRACT`)

- `SimpleDocumentController::store()` writes the value to **both** the `status` column and `metadata.status` (see `buildMetadata`, §4.2). `DocumentWorkflowService::submit()/decide()` likewise write both. Tests assert both `data.status` and `data.metadata.status` (§4.4).
- **FACT:** The same status value is materialized in two places in the runtime representation: the `documents.status` column and the `documents.metadata.status` JSON key.

### Orthogonal approval field (context)

- `documents.client_approved` (`boolean`) exists as a **separate** column from `status` (see `app/Models/Document.php:32,98,118` and `scopeClientApproved` at lines 257-261). Client-approval visibility is therefore split across two independent primitives: a boolean flag and a status string. Relevant because the approval workflow writes `status` (draft/submitted/approved/rejected) while client-facing approval also touches `client_approved`/`visibility` — two distinct "approval" concepts sharing the table.

### `document_versions` table (secondary)

- `database/migrations/2025_09_20_141042_create_document_versions_table.php` — has **no** `status` column; only `metadata` (json). Per `SimpleDocumentController::createVersion()` → `createVersionRecord()`, the status snapshot is written to `document_versions.metadata.status` via the shared `$metadata` blob — NOT a separate status column.

### Production facts

- `PRODUCTION FACTS UNKNOWN` — production `documents.status` value distribution was **not queried**; not asserted.
- Local/test SQLite files exist in the working tree (`database/database.sqlite`, `database/smoke-staging.sqlite`, `zenamanage_test.sqlite`). Treated as **runtime/local observations**, not production evidence; not inspected for live value distribution.

---

## 4.2 Current write paths (every path that can set `documents.status`)

Live canonical surface = `App\` namespace. A divergent legacy model `Src\DocumentManagement\Models\Document` also maps to table `documents` (see §7 note).

### Path 1 — `SimpleDocumentController::store()` (API create)
- File: `app/Http/Controllers/Api/SimpleDocumentController.php:90-222`
- **Who can invoke:** caller with `document.create` via `api/zena/documents` POST (`routes/api_zena.php:478`, named `documents.store`); also reachable ungated-by-specific-permission via `documents-simple` `apiResource` (`routes/api.php:237`, inside group `auth:sanctum,tenant.isolation,rbac`).
- **Validation rule (line 98):** `'status' => ['nullable', 'string', 'max:100', Rule::notIn(DocumentWorkflowStatus::reservedValues())]`.
- **Accepted values:** any string except the 3 reserved workflow values; defaults to `'active'` when omitted (line 195: `'status' => $data['status'] ?? 'active'`).
- **Reserved values blocked:** `['submitted','approved','rejected']` (= `DocumentWorkflowStatus::reservedValues()`).
- **Mutation owner:** `SimpleDocumentController::store()` writes `documents.status` (line 195) and `metadata.status` via `buildMetadata()` (line 161).
- **Default on create:** `'active'`.

### Path 2 — `SimpleDocumentController::update()` (API update, PUT/PATCH)
- File: `app/Http/Controllers/Api/SimpleDocumentController.php:524-606`
- **Who can invoke:** caller with `document.update` (`api/zena/documents/{id}` PUT/PATCH; `documents-simple` apiResource).
- **Validation rule (line 540):** same `notIn(reservedValues)` on the **target** status.
- **Behavioral guard (lines 577-585):** if the document's CURRENT `status` is reserved, the `status` field is **silently ignored** (no change, no error); other fields in the same request still apply. If current status is not reserved, the requested (non-reserved) target is written to `documents.status` (line 579) and `metadata.status` (line 580).
- **Mutation owner:** `SimpleDocumentController::update()`.

### Path 3 — `SimpleDocumentController::createVersion()` (API new version)
- File: `app/Http/Controllers/Api/SimpleDocumentController.php:395-505`
- **Who can invoke:** caller with `document.update` (`api/zena/documents/{id}/versions` POST, named `documents.versions.store`).
- **Validation rule (line 404):** same `notIn(reservedValues)` on target.
- **Target-status logic (lines 462-464):**
  `$targetStatus = DocumentWorkflowStatus::isReserved($document->status) ? $document->status : $request->input('status', $document->status);`
  — if current is reserved, status is preserved (unchanged); otherwise uses the request value or keeps the current value.
- **Writes:** `documents.status => $targetStatus` (line 493) and `metadata.status => $targetStatus` into the new `document_versions` row's metadata via the shared `$metadata` (lines 466 & 474-478).
- **Mutation owner:** `SimpleDocumentController::createVersion()` (the version row gets a metadata snapshot; the document row gets the canonical status).

### Path 4 — `DocumentWorkflowService::submit()` (API + Web workflow entry)
- File: `app/Services/DocumentWorkflowService.php:21-52`
- **Who can invoke:** `SimpleDocumentController::submit()` (API, `rbac:document.update`, `api/zena/documents/{id}/submit`, `routes/api_zena.php:484`) and `Web\DocumentWorkflowController::submit()` (Web, `routes/web.php:419`, `rbac:document.update`, then `authorize('update')`).
- **Pre-condition (line 35):** `document.status === 'draft'` (`DocumentWorkflowStatus::DRAFT->value`); any other status throws `INVALID_SUBMIT_TRANSITION`.
- **Writes:** `status => 'submitted'` to `documents.status` (line 45) and `metadata.status/submitted_by/submitted_at` (lines 40-42).
- **Mutation owner:** `DocumentWorkflowService` — the only writer of `submitted`.
- **AuthZ note:** `submit` is gated by `document.update`, **not** `document.approve`.

### Path 5 — `DocumentWorkflowService::decide()` (API + Web workflow decision)
- File: `app/Services/DocumentWorkflowService.php:59-97`
- **Who can invoke:** `SimpleDocumentController::decision()` (API, `rbac:document.approve`, `api/zena/documents/{id}/decision`, `routes/api_zena.php:485`) and `Web\DocumentWorkflowController::approve()/reject()` (Web, `authorize('approve')` → `DocumentPolicy::approve()`).
- **Pre-condition (line 78):** `document.status === 'submitted'` (`DocumentWorkflowStatus::SUBMITTED->value`); otherwise `INVALID_DECISION_TRANSITION`.
- **Writes:** `status => $decision->value` (`approved`/`rejected`) to `documents.status` (line 90) and `metadata.status/decision/decision_by/decision_at/decision_note` (lines 83-87).
- **Mutation owner:** `DocumentWorkflowService` — the only writer of `approved`/`rejected` as a decision outcome.
- **AuthZ:** `document.approve` permission via `DocumentPolicy::approve()` (`app/Policies/DocumentPolicy.php:113-121`) — tenant-scoped + `hasPermission('document.approve')` (tenant/role-level, **not** per-document).

### Path 6 — `Web\DocumentController::store()` (Web UI upload)
- File: `app/Http/Controllers/Web/DocumentController.php:82-120`
- Delegates to `SimpleDocumentController::store()` after forcing `'status' => DocumentWorkflowStatus::DRAFT->value` (line 99).
- **ASYMMETRIC FACT:** Web-created documents start at `draft`; API-created documents default to `active` (Path 1). Three different "initial" statuses across surfaces (API default `active`, Web default `draft`, factory random — §4.4).

### Path 7 — Legacy `App\Services\DocumentService` (divergent model) — NOT on canonical path
- File: `app/Services/DocumentService.php` — uses `Src\DocumentManagement\Models\Document` (legacy namespace, table `documents`), **not** `App\Models\Document`.
- `createDocument()` (lines 66-100) does **not** set `status` (relies on DB default `'active'`) and does **not** write `metadata.status`.
- `updateDocument()` / `revertToVersion()` do **not** set `status`.
- `approveForClient()` writes only `visibility`/`client_approved` — **not** `documents.status`.
- This legacy service is **not** routed from the two live surfaces (`routes/api.php`/`api_zena.php` use `App\Http\Controllers\Api\SimpleDocumentController` and `App\Http\Controllers\Web\{DocumentWorkflowController,DocumentController}`). It is a divergent implementation over the same `documents` table.

### Path 8 — `DocumentSeeder` (seed/fixture only)
- `database/seeders/DocumentSeeder.php` — uses `Src\DocumentManagement\Models\Document::factory()`; does **not** set `status` (DB default `'active'` applies). Seed/fixture only, not a production write path.

---

## 4.3 Current read/use paths (`documents.status` reads / comparisons)

Classification of observed `documents.status` reads or comparisons (repo evidence):

**approval workflow**
- `DocumentWorkflowService::submit()` reads `$document->status` to gate `draft → submitted` (`app/Services/DocumentWorkflowService.php:35`).
- `DocumentWorkflowService::decide()` reads `$document->status` to gate `submitted → approved|rejected` (line 78).
- `App\Events\DocumentUploaded.php:52` reads `$this->document->status` (audit payload).
- `Web\DocumentWorkflowController` delegates to the service above.

**document listing / filter**
- `SimpleDocumentController::index()` filters `->when($request->filled('status'), ... ->where('status', ...))` (`app/Http/Controllers/Api/SimpleDocumentController.php:65`) and serializes the model.
- `Web\DocumentController::index()` filters by `status` (`app/Http/Controllers/Web/DocumentController.php:44-46`); `approvals()` filters by `status` (`lines 75-76`).

**dashboard / UI rendering**
- `resources/views/documents/index.blade.php:58` renders `<x-ui.status-badge :status="$document->status ?? 'pending'">`.
- `resources/views/documents/index.blade.php:62` branches on `$document->status === 'draft'`.
- `resources/views/documents/approvals.blade.php:52` branches on `in_array($document->status, ['approved','rejected'])`.
- `resources/views/documents/approvals.blade.php:69` branches on `$document->status === 'submitted'`.
- **ASYMMETRY FACT:** the UI filter dropdowns (`index.blade.php:31-33`, `approvals.blade.php:31-33`) offer the option value `pending` (label "Chờ duyệt"), but **no write path ever stores `pending`** — the workflow "waiting" state is `submitted`. A user filtering by "pending" returns nothing from the `documents.status` column. `DESIGN QUESTION — NOT DECIDED`: is `pending` a stale/legacy UI label that should map to `submitted`?

**dashboard (Today Workspace)**
- `App\Http\Controllers\Api\V1\App\DashboardController.php` builds Today Workspace panels. Its only `'action' => 'review'` entries (lines 89, 347) map to project proposals (`/app/projects?filter=pending_review`) and a design review — **not** to `documents.status`. No Today-Workspace surface reads `documents.status` for an "Action Required" item. Per `docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §7, Document approval is **explicitly excluded** from Today "Action Required" pending a per-document approver (GAP-033). So there is **no live Today-Workspace read path** on `documents.status` today; resolving GAP-032 is a precondition, not a current dependency.

**reporting**
- `App\Services\DocumentService::getDocumentStats()` / `getDocumentsByProject()` filter on `visibility` and `client_approved`, **not** `status`. No status-based report found on the live `App\` service.

**API clients / contracts**
- API exposes `Document` as raw model JSON via `ZenaContractResponseTrait::zenaSuccessResponse()`: top-level `data.status` (column) and `data.metadata.status` (JSON key) — populated identically at create/update and by the workflow service.
- **FACT:** the API contract is an **unconstrained string**; no OpenAPI enum enumerates document status values. `API_DOCUMENTATION.md` and `docs/zena/contract/` do not enumerate document status values.

**tests / seeders / fixtures** — see §4.2 (Path 8) and §4.4.

**other**
- `App\Exceptions\DocumentWorkflowException` carries reason codes (`INVALID_SUBMIT_TRANSITION`, `INVALID_DECISION_TRANSITION`, `DOCUMENT_NOT_FOUND`) and strings the current `$document->status` for internal logging only.

---

## 4.4 Existing real values (`documents.status`)

Values of `documents.status` actually produced/read by repo artifacts (no production query):

| Source | Values produced/read |
|---|---|
| `database/factories/DocumentFactory.php:65` | `draft`, `review`, `approved`, `published` |
| `SimpleDocumentController::store()` default (line 195) + DB default (migration `:30`) | `active` (the omitted case) |
| `App\Enums\DocumentWorkflowStatus` + `DocumentDecision` | `draft`, `submitted`, `approved`, `rejected` |
| `tests/Feature/Api/DocumentManagementTest.php` | `review`, `draft`, `submitted`, `approved`, `rejected` |
| `tests/Feature/Services/DocumentWorkflowServiceTest.php` | `draft`, `submitted`, `approved`, `rejected` |
| `tests/Unit/Enums/DocumentWorkflowStatusTest.php` | asserts `reserved() = [submitted, approved, rejected]`; asserts `isReserved('draft')`/`('active')`/`('review')` === false |

**Consolidated set present in repo evidence:**
- Generic/legacy values: `active` (default), `review`, `published` (factory only).
- Workflow values: `draft`, `submitted`, `approved`, `rejected`.

**Per-source breakdown:**
- `draft` — workflow entry; Web store default; factory; tests. Also accepted as a generic (non-reserved) value by `store()`/`update()`.
- `review` — accepted as a generic (non-reserved) value by `store()`/`update()`; asserted in tests; factory.
- `active` — DB default + API `store()` default; **not** emitted by the factory.
- `published` — **factory only** (no live controller/service/test path sets a document to `published`); note `published` is also used as a status by *other* models (`KnowledgeArticle`, `DeliverableTemplate`, `support_documentations`), but those are different tables — only the document factory mentions it.
- `submitted`/`approved`/`rejected` — reserved workflow values; written only by `DocumentWorkflowService`; `approved` is also randomly emitted by the factory (which would collide semantically with a terminal workflow decision).

`PRODUCTION FACTS UNKNOWN` — production `documents.status` value distribution was **not queried**; the live production set of values is unknown.

`DESIGN QUESTION — NOT DECIDED`: the factory emits `published` and `approved` for documents, but `published` is otherwise unused by any live path and `approved` overlaps the workflow terminal state. Whether `published`/`review`/`active` are intended business statuses or legacy artifacts is a business decision (GAP-032 Gate 2), not an engineering recommendation here.

---

## 4.5 GAP-031 boundary (resolved vs. intentionally open)

Reference design spec: `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md` (rev 3, §16 + §3 state machine). Register entry: `OPERATIONAL_GAP_REGISTER.md` (GAP-031 = RESOLVED, verified).

**GAP-031 RESOLVED** exactly these (per spec §16 and the live code):
1. **Divergence closed:** dead Web approval surface vs. canonical API workflow — resolved (`DocumentWorkflowController` now routes through `DocumentWorkflowService`).
2. **Decision authorization:** `document.approve` permission + `DocumentPolicy::approve()` gate all decision actions (API `decision` route `rbac:document.approve`, line `app/Policies/DocumentPolicy.php:113-121`).
3. **Transition integrity (reserved-status guard):** the 3 reserved workflow statuses (`submitted`/`approved`/`rejected`) are blocked from every generic write path (`store()`/`update()`/`createVersion()`) via `Rule::notIn(DocumentWorkflowStatus::reservedValues())`; and once a document is in a reserved state, generic `update()`/`createVersion()` **preserve** it (cannot move it out to a legacy value). Confirmed live in `SimpleDocumentController.php:98,540,404,462-464,577-585` and covered by tests: `test_update_rejects_direct_set_of_reserved_status_approved`, `test_update_rejects_direct_set_of_reserved_status_submitted_and_rejected`, `test_update_on_submitted_document_silently_preserves_status_for_legacy_target`, `test_update_on_approved_document_still_updates_other_fields`, `test_create_version_rejects_direct_set_of_reserved_status`, `test_create_version_on_submitted_document_preserves_status`, `test_create_version_on_approved_document_with_legacy_status_input_preserves_status`.
4. **Decision audit:** `metadata.decision_by/decision_at/decision_note/submitted_by/submitted_at` written by `DocumentWorkflowService`; `PROTECTED_METADATA_KEYS` (`app/Http/Controllers/Api/SimpleDocumentController.php:46-54`) blocks clients forging them via the `metadata` JSON blob (covered by `test_create_version_cannot_forge_workflow_audit_metadata`).
5. **Single mutation owner:** `DocumentWorkflowService` is the only writer of the reserved values.

**GAP-031 INTENTIONALLY DID NOT resolve** (spec §2 "Ngoài phạm vi" + §16):
- The meaning of legacy/generic statuses (`active`, `review`, and any other client-set value) within the approval workflow.
- Whether legacy-status documents need a re-entry/normalization step (e.g. `active → draft`) before `submit()` — note `submit()` requires `draft`, but `store()`/`Web store()` can produce `active`/`review`/`published`, so such documents can never enter approval through no present path.
- Backward-compatibility policy for legacy values and what to do with legacy data.

**GAP-032 STARTS EXACTLY at this boundary.** The column is now *safe to write* workflow values through one service, but there is still **no single business definition of what `Document.status` means**, and legacy values coexist with workflow values in the same column with no shared lifecycle. The field-level guard exists; the semantic/lifecycle contract does not.

---

## 4.6 GAP-033 dependency (business-level only)

- **GAP-033** = "designate a specific approver/action-owner per Document" — `document.approve` is tenant/role-wide (`DocumentPolicy::approve()` = `hasPermission('document.approve')`), **not** per-document. See register line for GAP-033 and `docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §6.4/§7.
- **GAP-032 is a prerequisite to GAP-033:** GAP-033's approver design cannot assume a coherent `Document.status` lifecycle until GAP-032 decides it. No approver mechanism is designed or introduced here.
- Today Workspace "Action Required" for documents is blocked by **both** GAP-032 (unclear status lifecycle) and GAP-033 (no per-document approver) — not introduced by either. Closing TODAY "Action Required" is out of scope for this work item.

---

## DESIGN QUESTIONS for Gate 2 (NOT decided / NOT recommended here)

1. Does `Document.status` represent a single lifecycle or multiple dimensions (generic + workflow)?
2. Are legacy values (`active`/`review`/`published`) still valid business statuses, or should they normalize into the workflow?
3. Must a legacy-status document re-enter via a step (e.g. `active → draft`) before `submit()`?
4. What backward compatibility must be preserved for API clients currently setting legacy statuses?
5. How must legacy data be handled (preserve, migrate, reject)? — `PRODUCTION FACTS UNKNOWN` until the production DB is separately queried (not done in Gate 1 per exclusions §8).

## Risks observed (no solutions proposed)

- **RISK — one column, two concepts:** a document can be `active`/`review` (generic) with no workflow meaning, yet `submit()` only accepts `draft`, so such documents can never enter approval without a transition no path currently performs.
- **RISK — multiple "initial" statuses:** API `store()` default `active` vs Web `store()` default `draft` vs factory random element — no single canonical initial status.
- **RISK — silent status preservation:** `update()`/`createVersion()` silently ignore `status` when the current value is reserved (HTTP 200, status unchanged) — a client cannot move a document out of `submitted`/`approved`/`rejected` even if future business rules allow reopening.
- **RISK — `pending` UI filter:** document view filter dropdowns offer `pending`, which no write path ever stores; the real "waiting" value is `submitted`.
- **RISK — status duplication:** `documents.status` and `documents.metadata.status` hold the same value; any path writing one without the other would drift.
- **RISK — divergent legacy model:** `Src\DocumentManagement\Models\Document` (used by `App\Services\DocumentService` and `DocumentSeeder`) omits `status`/`metadata.status` from its fillable, vs `App\Models\Document`. Unclear whether this legacy service is live or dead at runtime — `DESIGN QUESTION — NOT DECIDED`.
- **RISK — tenant/role-level approve:** `document.approve` is not per-document, so "who can decide" is not resolvable per document — today's GAP-033 gap, which also blocks Today Workspace inclusion.

## Note on legacy model / divergence (context only)

`database/seeders/DocumentSeeder.php:5` and `app/Services/DocumentService.php:14` import `Src\DocumentManagement\Models\Document`, while the live API/Web surface uses `App\Models\Document`. Both declare `protected $table = 'documents'`. The `Src\` model is a stripped-down variant (no `status` in fillable, no `metadata`/`version`/`is_current_version`/`parent_document_id`/`tenant_id`). Whether `App\Services\DocumentService` is reachable at runtime (i.e., live traffic vs. dead code) was **not** confirmed by a route/trace scan here; it is an outstanding `DESIGN QUESTION` for GAP-032 Gate 2, not an engineering assertion.
