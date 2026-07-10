# ZENA Ops Roadmap — Design Spec

Date: 2026-07-09 (revised after two adversarial review passes — see §5 and §6)
Status: approved by user (Phan), ready for implementation planning
Author: Claude (brainstorming session), decision owner: Phan (khafibo@gmail.com)

## 1. Purpose

ZenaManage's original 10-goal vision (see project memory `project_zena_vision.md`, captured 2026-07-09) positions the app as the full operating brain of Z.E.N.A architecture company: lead → tư vấn → khảo sát → báo giá → hợp đồng → thiết kế/thi công → nghiệm thu → chăm sóc sau. A gap assessment done the same day found the operational backbone (project/procurement/QC/document workflows) strong (~75-80%), but the commercial-funnel + intelligence layer weak: quotation/pricing (~20%), client portal (~3%), AI (0%), business-specific BI (~30%), plus a newly-scoped need for a design-work management layer.

This spec decomposes the remaining work into 7 phases, each independently specced and buildable by a different agent/session, in dependency order.

**Known conflict to resolve as part of this work:** `docs/product-purpose-ssot.md` and `docs/roadmap/canonical-roadmap.md` (last updated 2026-03-19, self-marked "temporary... pending explicit replacement") explicitly scope CRM and "generalized BI" as **out of scope**. That statement is now superseded by the user's 2026-07-09 vision and the CRM slice already merged. Phase 0 below updates those SSOT docs so future agents reading them aren't misled.

## 2. Cross-system architecture decision

ZenaManage (this repo, Laravel/PHP/Blade, multi-tenant, RBAC) and `zena-boq-core` (separate repo, Next.js 16 + Prisma 7 + PostgreSQL/Neon, deployed at `zena-boq.vercel.app`) are **two independent systems, integrated via API/webhook — not merged, not sharing a database.**

- `zena-boq-core` owns: work-item library (priced catalog: resources → work items → assemblies → versioned rate books), BOQ line items, immutable Quote revisions (PDF/Excel export), backtest/calibration workflow. All rates are `UNCALIBRATED` until the project's own Gate 0 governance process passes — ZenaManage must display this flag verbatim, never suppress or "clean" it.
- ZenaManage owns: CRM (Lead/Account/Opportunity), project/task/document/contract operations, RBAC, tenant isolation, dashboards.
- Integration is read-only from ZenaManage's side: ZenaManage pulls/receives quote totals and status: it never writes pricing data into `zena-boq-core`.
- Auth between systems: static bearer API keys (service-to-service secrets) — one per direction (see Phase 2 for naming), stored as env vars. No user-level SSO in this phase.
- Goal #3 from the original 10-goal vision ("thư viện công tác chuẩn hóa") is considered satisfied by `zena-boq-core`'s existing `/library` and is **out of scope for ZenaManage** — any gaps in package coverage (pháp lý, phá dỡ, ép cọc, PCCC, BIM) are a `zena-boq-core` backlog item, tracked there, not here.

## 3. Phase map

```
Phase 0: SSOT doc alignment                  (housekeeping, no code)
Phase 1: Quản lý công việc thiết kế          (independent)
Phase 2: Tích hợp ZenaManage <-> zena-boq-core (cross-repo; needs a matching task opened in zena-boq-core)
Phase 3: Hiển thị báo giá trong CRM          -> depends on Phase 2
Phase 4: Tự động hoá hợp đồng                 -> depends on Phase 3
Phase 5: BI Dashboard điều hành               -> depends on Phase 1, 2, 3, 4
Phase 6: Cổng khách hàng                      -> depends on Phase 1, 3, 4, 5
Phase 7: AI có kiểm soát                      -> sequenced last by choice, not by hard technical dependency (see note below)
```

Phases 0 and 1 have no dependencies and can start immediately / in parallel. Phase 2 is the pivot point for the commercial chain (3→4→5). Phase 6 is terminal on its dependencies (real data flowing, not stubs) — do not start it early.

**Phase 7 is a deliberate sequencing choice, not a technical blocker.** At least two of its three v1 use cases (suggesting `service_category` from `Lead.project_description`; flagging missing `Document`s against a `WorkTemplate` checklist) only need data that already existed *before* this roadmap (the CRM slice, `Document`/`WorkTemplate`) — they do not technically require Phases 2-6 to exist. "AI last" is the user's explicit risk-management choice (build on stable data first), not a dependency graph fact. Do not treat this as a hard blocker if there's ever a reason to fast-track one narrow AI use case ahead of the rest — re-confirm with the user first, since the ordering was their call, but don't assume the codebase itself forbids it.

**Rough sizing** (order-of-magnitude only, for sequencing/staffing — not a commitment): Phase 0 — XS (hours). Phase 1 — M (new model + API + kanban UI + 2 test files, similar scope to the CRM slice already shipped). Phase 2 — M-L on the ZenaManage side, **plus an untracked, externally-owned dependency** on `zena-boq-core` work of unknown size (see §5, finding I7). Phase 3 — XS. Phase 4 — S. Phase 5 — S (pure additive KPI cards on existing infra). Phase 6 — M, but cannot start sizing seriously until its own brainstorm resolves auth + visibility scope. Phase 7 — S per use case, ship one at a time rather than all three together.

**Branching/PR strategy:** each phase gets its own branch off `main` (or off the prior phase's branch if started before that phase merged) and its own PR, merged sequentially in dependency order. Do not build Phase 3+ code against an unmerged Phase 2 branch without explicitly rebasing — the phases are sequenced by data dependency, not just by convenience, so drift between branches is a real risk here (Phase 2 changes the `Opportunity` schema that 3/4/5 read from).

---

## Phase 0 — SSOT doc alignment

**Goal:** stop future agents from reading stale scope docs that contradict the current direction.

**Work:**
- Update `docs/product-purpose-ssot.md`: remove "CRM" and "generalized BI/analytics" from the explicit-boundary exclusion list; add a note referencing this roadmap spec.
- Update `docs/roadmap/canonical-roadmap.md`: add CRM/quotation-integration/portal/AI as a new pillar or explicit addendum section, pointing at this spec file.
- No application code changes.

**Acceptance criteria:** both SSOT docs no longer contradict the existence of the CRM slice already merged, and both link to this spec.

**Out of scope:** rewriting the entire SSOT docs; this is a targeted correction, not a rewrite.

---

## Phase 1 — Quản lý công việc thiết kế (Design Work Management)

**Goal:** track design deliverables per project through a client-facing review/revision cycle, with a kanban view, without being locked to the existing fixed WorkTemplate/WorkInstance step structure.

**Data model — new model `DesignItem`:**

| Field | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `tenant_id` | ulid | |
| `project_id` | ulid | FK `projects` |
| `phase_id` | ulid, nullable | FK `project_phases` |
| `work_instance_step_id` | ulid, nullable | FK `work_instance_steps` — set when this item corresponds to a standard templated design step (for traceability/reporting); left `null` for ad-hoc items not in any template |
| `name` | string | |
| `item_type` | string enum | `concept`, `schematic`, `technical`, `structural`, `mep`, `interior`, `other` |
| `review_status` | string enum | `draft`, `internal_review`, `sent_to_client`, `revision_requested`, `approved`, `final` |
| `assigned_to` | ulid, nullable | FK `users` |
| `due_to_client_at` | date, nullable | |
| `client_feedback_notes` | text, nullable | |
| `approval_evidence` | string, nullable | how the `approved` transition was confirmed — e.g. `phone`, `email`, `zalo`, `client_portal` (once Phase 6 exists); required when transitioning to `approved`, see below |
| `created_by` | ulid | FK `users` |
| timestamps | | |

**File attachments:** reuse `Document`/`DocumentVersion`. Add `Document::ENTITY_TYPE_DESIGN_ITEM` to the existing polymorphic enum (`linked_entity_type`/`linked_entity_id`). Do not use `WorkInstanceStepAttachment` (no versioning support).

**Authority rule when `work_instance_step_id` is set (mandatory, do not skip):** `WorkInstanceStep`'s own status/`approveStep` flow keeps meaning exactly what it means today — internal checklist/QC-style step completion, unrelated to client review. `DesignItem.review_status` is a **separate, independent state machine** and is the *only* thing that drives the kanban and the client-facing cycle. Implementations must **never** auto-write `DesignItem.review_status` changes back onto the linked `WorkInstanceStep`'s status, and must never read `WorkInstanceStep`'s status to infer or gate `DesignItem.review_status`. The FK is for traceability/reporting cross-reference only ("this design item corresponds to template step X"), not a sync channel. If a future need for syncing them emerges, that's a new decision, not an assumed default.

**State machine (explicit, supersedes any implied linear reading):** `review_status` transitions allowed are:
- `draft` → `internal_review`
- `internal_review` → `draft` (sent back for rework) | `sent_to_client`
- `sent_to_client` → `revision_requested` | `approved`
- `revision_requested` → `internal_review` (loop back — this is the common case, not an edge case)
- `approved` → `final` | `revision_requested` (**late change requests after approval are expected and must be supported** — do not model `approved` as a dead end)
- `final` is terminal; no further transitions (a genuinely new revision after `final` is a new `DesignItem`, not a status change, to preserve history)

All other transitions are invalid and must be rejected by `updateStatus` with a 422. `revision_requested` requires non-empty `client_feedback_notes`. `sent_to_client` requires `due_to_client_at` to already be set (set it earlier in `draft`/`internal_review`, not as part of the transition) **and requires at least one attached `Document` to already exist for this `DesignItem`** — nothing should be markable as "sent to client" with zero files attached; reject with 422 otherwise. `approved` requires `approval_evidence` to be set (non-empty) — this is a staff attestation of how client agreement was confirmed (phone/email/Zalo today; the client portal in Phase 6 becomes another valid value once it exists), not optional metadata: it's exactly the kind of record `product-purpose-ssot.md` already calls for ("evidence-bearing... flows") and the kind most likely to be referenced in a client dispute later.

**Audit trail (mandatory):** every `review_status` change must write an `EventRecord` (`aggregate_type = 'design_item'`), following the exact pattern already established for `Lead`/`Opportunity` in the CRM slice (`crm.lead.captured`, `crm.opportunity.stage_changed`, etc. — use `design_item.status_changed` with `from`/`to` payload). This is not optional: client revision history is exactly the kind of record that gets referenced in disputes, and the CRM slice already proved this pattern works and is tested.

**API (new `Api/DesignItemController`):** `index`, `store`, `show`, `update`, `updateStatus` (enforces the transition graph above; returns 422 on invalid transitions with a message naming the invalid `from`/`to` pair).

**Web (new `Web/DesignItemPageController` + views under `resources/views/design-items/`, mounted under `/operator/design-items/*` following the same convention as `CrmPageController`):** kanban board grouping `DesignItem` by `review_status`, same pattern as `CrmPageController::BOARD_GROUPS`. Nav entry under a new or existing "Dự án"/"Kinh doanh" section — decide placement when implementing, based on how it reads next to existing nav groups.

**Permissions:** new `design-item.view`, `design-item.manage` (mirror the `crm.*` pattern from the CRM slice: seeder entries in `ZenaPermissionsSeeder` + `ZenaRbacSeeder`, policy class `DesignItemPolicy`).

**Acceptance criteria:**
- Create a `DesignItem` linked to an existing `WorkInstanceStep` and one with `work_instance_step_id = null` (ad-hoc) — both work identically through the kanban; changing one's `review_status` never touches the linked `WorkInstanceStep`'s own status (assert this explicitly in a test — it's the exact bug the authority rule above exists to prevent).
- Walk a `DesignItem` through the full loop at least once: `draft → internal_review → sent_to_client → revision_requested → internal_review → sent_to_client → approved → revision_requested (late change) → internal_review → sent_to_client → approved → final` — proving the loop-back paths work, not just the forward chain.
- Attempt an invalid transition (e.g. `draft → approved` directly) and assert 422.
- Attempt `sent_to_client` with zero attached `Document`s and assert 422; attempt `approved` without `approval_evidence` and assert 422.
- Each status change produces an `EventRecord` with correct `from`/`to` payload — assert the full history is queryable in order.
- Attach a file, upload a second version, see both versions listed.
- RBAC: user without `design-item.manage` cannot change status or upload; user without `design-item.view` gets 403 on index.
- Feature test (`tests/Feature/Api/DesignItemApiTest.php`) + operator UI test (`tests/Feature/Zena/OperatorDesignItemUiTest.php`), mirroring `CrmApiTest`/`OperatorCrmUiTest` conventions (tenant isolation test, permission-denied test, full-flow test).

**Open questions for the implementing agent:** exact nav placement; whether `item_type` should be a fixed enum validated server-side or free text with suggested values (recommend fixed enum, matching `Opportunity::VALID_SERVICE_CATEGORIES` precedent).

---

## Phase 2 — Tích hợp ZenaManage ↔ zena-boq-core

**Goal:** let a ZenaManage `Opportunity` reference a `zena-boq-core` project/quote and display its current totals/status without ZenaManage owning any pricing logic.

**Multi-tenancy gate (mandatory, critical):** `zena-boq-core` has no tenant concept — it is Phan's single-company tool. ZenaManage is explicitly multi-tenant (see `docs/product-purpose-ssot.md`). As designed, this integration would let **any** tenant in ZenaManage type in an arbitrary `zena-boq-core` project code and pull back Z.E.N.A's commercial quote data — a cross-tenant data leak the moment a second tenant exists. This integration must be **hard-restricted to one specific tenant** (the Z.E.N.A tenant): the sync button and the `external_boq_project_code` field must both check the current tenant against the gate (see resolution mechanism below) and refuse (403/no-op) for every other tenant. (The webhook receiver is deferred — see below — but the same gate check must apply to it too, whenever that follow-up task builds it.) This is not deferrable to a later hardening pass — it must ship in Phase 2's first version, because there is currently nothing else preventing the leak.

**No Z.E.N.A tenant exists yet, and how the gate resolves it (decided 2026-07-10):** checked the dev database directly — there is no `Tenant` row representing Z.E.N.A today, only unrelated seed/faker-generated tenants. Phase 2 must create one. Resolution mechanism, decided during brainstorming (option **(b)** of three considered): **look up by name at runtime, not by a hardcoded/copied ID.**

- **Seeder** (not a migration — this is business data, matching the existing `ZenaPermissionsSeeder`/`ZenaRbacSeeder` convention in this codebase, not a schema change): `Tenant::firstOrCreate(['name' => 'Z.E.N.A'], [...])`. `'Z.E.N.A'` is the fixed, canonical lookup key — pick a value distinct enough that it can't collide with faker-generated tenant names (already true; existing dev tenants are things like "Zieme-Jerde", "Aufderhar, Kuvalis and Wisoky" — plain "Z.E.N.A" won't collide). This makes the seeder naturally idempotent (`firstOrCreate` on `name`) and identical across every environment without any manual "copy the generated ID into `.env`" step.
- **Config:** new `config/zena_boq.php` returning `['integration_tenant_name' => env('ZENA_BOQ_INTEGRATION_TENANT_NAME', 'Z.E.N.A'), 'read_api_secret' => env('ZENA_BOQ_READ_API_SECRET'), 'base_url' => env('ZENA_BOQ_BASE_URL')]` — the config holds the **name** to look up, not an ID to compare directly.
- **The gate itself** resolves the ID at request time: `Tenant::where('name', config('zena_boq.integration_tenant_name'))->value('id')`, then compares that resolved ID to the current tenant. This is what "fail-closed" (below) must guard: if the configured name is empty OR resolves to no row, treat it as "no tenant is authorized," not as a match against `null`.

Scope this narrowly — this is a tenant record to anchor the integration gate, not a user/staff onboarding task (assigning real Z.E.N.A employees as `User` records to this tenant is a separate, pre-existing concern, out of scope here).

**Webhook deferred, firm decision (made 2026-07-10, supersedes the "optional, defer if it slows Phase 2 down" wording below):** Phase 2 v1 ships with the manual "Đồng bộ báo giá" sync button as the *only* integration mechanism. The webhook (`zena-boq-core` → ZenaManage push notification) is deferred to its own follow-up task, opened once `zena-boq-core`'s side has both the read API *and* webhook-sending capability (neither exists yet as of this decision — confirmed via a fresh check of that repo, no change since the original spec). Do not build the inbound webhook receiver route in Phase 2's first version.

**Fail-closed requirement (mandatory — this is what makes the gate above actually safe):** with name-based resolution, there are now *two* independent ways this can fail open if written naively, not just one — both must be handled: (1) `config('zena_boq.integration_tenant_name')` itself is unset/empty (e.g. missing env var in a fresh deployment), and (2) the configured name doesn't match any `Tenant` row (e.g. seeder hasn't run yet, or the name was typo'd in `.env`). Either case must resolve to **"no tenant is authorized"** — never treat a `null` resolved ID as equal to a `null`/empty current-tenant check. Write the guard as an explicit two-step resolution: `$configuredName = config('zena_boq.integration_tenant_name'); $resolvedId = $configuredName ? Tenant::where('name', $configuredName)->value('id') : null; if (!$resolvedId || $tenant->id !== $resolvedId) { deny(); }`. Add tests for both failure modes (empty config name; config name set but no matching tenant row) — these are the single most important tests in this phase, since they're verifying the fix for the roadmap's original critical finding actually holds under the resolution mechanism chosen here.

**One secret for now:** since the webhook is deferred (see above), only the outbound direction exists in Phase 2 v1 — `ZENA_BOQ_READ_API_SECRET`, used by ZenaManage to call `zena-boq-core`'s read API. Reserve the name `ZENA_BOQ_WEBHOOK_SECRET` for the deferred follow-up task (don't use it for anything now, so there's no ambiguity later about which secret guards which direction).

**Cross-repo work (in `zena-boq-core`, tracked as a separate task there, not in this repo):**
- Add a minimal authenticated read API:
  - `GET /api/external/projects/:code` → `{ id, code, name, client, status, rateBookVersion }`
  - `GET /api/external/quotes/latest?projectCode=:code` (or `/api/external/quotes/:id`) → `{ id, projectId, revision, status, calibration, subtotal, vatAmount, total, issuedAt }`
  - Auth: `Authorization: Bearer <ZENA_BOQ_READ_API_SECRET>`.
  - (Webhook intentionally not listed here — deferred, see above.)

**Coordination note — do not let ZenaManage-side work block on this:** the `zena-boq-core` read API above does not exist yet as of this spec, and there is no tracked mechanism in this repo for knowing when it lands (it's a separate repo, separate backlog). ZenaManage-side implementation must be built and fully tested against a **mocked HTTP client** (fake responses matching the shapes above) from day one — do not wait for the real endpoint to exist to make progress. Full end-to-end verification (a real sync against a live `zena-boq-core` project) is a separate, later checkpoint once both sides are ready, not a blocker for merging the ZenaManage-side PR.

**ZenaManage-side work:**
- New `App\Database\Seeders\ZenaBoqTenantSeeder` (or add to an existing tenant-related seeder — implementer's call): `Tenant::firstOrCreate(['name' => 'Z.E.N.A'], [...])`. Business data, not schema — a seeder, not a migration. This is a prerequisite for the tenant-gate to have anything real to resolve against.
- New `config/zena_boq.php`: `integration_tenant_name` (default `'Z.E.N.A'`, overridable via `ZENA_BOQ_INTEGRATION_TENANT_NAME`), `read_api_secret` (`ZENA_BOQ_READ_API_SECRET`), `base_url` (`ZENA_BOQ_BASE_URL`).
- Migration: add nullable columns to `opportunities`: `external_boq_project_code`, `external_quote_id`, `external_quote_snapshot` (json: `subtotal`, `vat_amount`, `total`, `status`, `calibration`, `issued_at`), `external_quote_synced_at`.
- New service `App\Services\ZenaBoqIntegrationService`: wraps the outbound HTTP call to `zena-boq-core`'s read API (base URL + bearer secret from `config/zena_boq.php`), with a clear timeout and error-envelope-consistent failure handling (don't let a `zena-boq-core` outage break the Opportunity page — degrade to "last synced at X" state).
- Manual "Đồng bộ báo giá" button on the Opportunity page (`Api/OpportunityController@syncExternalQuote` or similar), calling `ZenaBoqIntegrationService` synchronously — this is the *only* integration mechanism in Phase 2 v1 (no webhook receiver route in this phase).

**Acceptance criteria:**
- After the seeder runs, a `Tenant` row named `'Z.E.N.A'` exists (created if missing, reused if already present — re-running the seeder must not create a duplicate).
- An Opportunity belonging to the real, seeded Z.E.N.A tenant can be linked to an `external_boq_project_code` and successfully synced.
- Clicking "Đồng bộ báo giá" populates `external_quote_snapshot` with real data from a test `zena-boq-core` project (or a mocked HTTP response in tests).
- `zena-boq-core` being unreachable degrades gracefully (existing cached data stays visible with a "last synced" timestamp; no 500s bubble to the user).
- **Tenant-gate test (required):** an Opportunity belonging to a *different*, non-Z.E.N.A tenant cannot trigger a sync, even with a syntactically valid `external_boq_project_code` — assert this explicitly, it is the fix for the critical finding above.
- **Fail-closed test, both failure modes (required):** (1) with `zena_boq.integration_tenant_name` config empty/null, no tenant — including the real seeded Z.E.N.A tenant — can sync; (2) with the config set to a name that matches no `Tenant` row (e.g. seeder hasn't run, or a typo), the same denial holds. Both prove the gate fails closed rather than accidentally resolving to a match.

**Out of scope:** any UI for creating/editing quotes (that stays in `zena-boq-core`); user-level SSO between the two systems.

**Non-Z.E.N.A tenants — deferred fallback, trigger-gated (decision made 2026-07-09):** the multi-tenancy gate above means every tenant other than Z.E.N.A gets no "Báo giá" card and keeps using `Opportunity.estimated_fee` as a single manual number — the same as today, not worse. This is a deliberate choice, not an oversight: as of this spec Z.E.N.A is the only real tenant, so building a second quotation path now would be speculative work for a hypothetical customer (YAGNI). The decision is **not** "ignore it forever" — it's "documented and ready to build the moment it's actually needed," so a future agent doesn't have to re-derive this from scratch:

- **Trigger:** the moment a second real tenant is onboarded and needs structured quotation (not before).
- **What to build then:** a lightweight, tenant-scoped internal quotation module inside ZenaManage — a simple `Quote`/`QuoteLine` model (line items + unit price + quantity + subtotal), following the same tenant-isolation/RBAC conventions as every other ZenaManage module. Deliberately **do not** carry over `zena-boq-core`'s calibration/backtest/rate-book-versioning governance — that complexity exists specifically for Z.E.N.A's own historical-pricing-accuracy problem and would be pure overhead for a new tenant starting fresh.
- **Where it plugs in:** the same "Báo giá" card slot on the Opportunity page (Phase 3) should be able to render from *either* an external `zena-boq-core`-style snapshot (Z.E.N.A) *or* this internal `Quote` (other tenants) — when Phase 3 is implemented, keep the card's data shape (subtotal/vat/total/status) source-agnostic for exactly this reason, even though only the external source is wired up now.
- This item is intentionally not a numbered phase (0-7) — it has no scheduled start date. Re-open it as its own brainstorming session when the trigger condition above actually occurs.

---

## Phase 3 — Hiển thị báo giá trong CRM

**Goal:** surface the synced quote data from Phase 2 directly on the Opportunity page.

**Work:** add a "Báo giá" card to `resources/views/crm/opportunity-show.blade.php`: subtotal/VAT/total, status badge (`ISSUED`/`ACCEPTED`/...), a visually distinct `UNCALIBRATED`/`CALIBRATED` badge (must not be able to be mistaken for each other — this is a hard requirement carried over from `zena-boq-core`'s own governance rules), a "đồng bộ lần cuối: X" relative-time label sourced from `external_quote_synced_at` (flag visually — e.g. muted/warning color — when older than a few days, so staff don't act on stale numbers without realizing it), and an external link (`target="_blank"`) to open the real quote on `zena-boq.vercel.app`. No embedding/iframe — link out only, since the source of truth's UI lives there.

**Acceptance criteria:** an Opportunity with a synced quote shows the card; one without a linked `zena-boq-core` project shows a "Chưa liên kết báo giá" empty state. The action to trigger the Phase 2 linking flow from that empty state requires `crm.manage` (same as every other CRM mutation) — a `crm.view`-only user sees the empty state with no action button, not a disabled/broken one.

**Forward-compat note:** build the card's view data as a small, source-agnostic shape (`subtotal`, `vat_amount`, `total`, `status`, `calibration|null`, `synced_at|null`, `external_url|null`) assembled by the controller from `external_quote_snapshot`, rather than reading `external_quote_snapshot` fields directly in the Blade template. This is what lets the deferred internal-quotation fallback (see Phase 2's "Non-Z.E.N.A tenants" note) reuse the same card later without a rewrite — even though only the `zena-boq-core` source exists today.

**Dependency:** requires Phase 2's cached snapshot fields to exist and be populated.

---

## Phase 4 — Tự động hoá hợp đồng

**Goal:** when an Opportunity is WON and its linked quote is `ACCEPTED`, let staff generate a draft `Contract` pre-filled from the quote total, with a generated PDF, in one action.

**Work:**
- New action on the Opportunity page: "Tạo hợp đồng" (visible only when `pipeline_stage = won` and `external_quote_snapshot.status = ACCEPTED`).
- Creates a `Contract` record: `total_value = external_quote_snapshot.total`, `client_name` from the linked `Account`, `status = draft`. **Pin the exact source revision**: store `external_quote_snapshot.id` (the `zena-boq-core` quote id) and its `revision` number on the `Contract` record at creation time (new columns, e.g. `source_quote_id`, `source_quote_revision`) — this is what "no manual re-entry of the number" actually depends on being traceable to.
- **Quote-drift guard:** once a `Contract` has been generated, if a later "Đồng bộ báo giá" (Phase 2) pulls a *different* `external_quote_id`/`revision` or a changed `total` than what's pinned on an existing draft `Contract`, show a visible warning on both the Opportunity and the Contract page ("Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp") rather than silently letting the two drift apart unnoticed. **`Contract.total_value` and the pinned `source_quote_id`/`source_quote_revision` must never be auto-updated by a re-sync, ever** — a `Contract` is treated as a point-in-time commercial document once created (it may already be reflected in an issued PDF), so silently rewriting its total on drift detection would be worse than the drift itself. The warning is informational only; resolving it (regenerating a new Contract, manually amending, or explicitly dismissing) is always a deliberate human action, never automatic.
- **Duplicate-contract guard:** if a `Contract` already exists for this Opportunity, "Tạo hợp đồng" must not silently create a second one — either disable the button and link to the existing Contract, or require an explicit confirm naming the existing one.
- Generates a PDF from a contract template — reuse the existing `DeliverableTemplate`/`DeliverableTemplateVersion` + `DeliverablePdfExportService` pattern (template with merge fields) rather than building new PDF infrastructure.

**Acceptance criteria:** one action from a WON Opportunity with an ACCEPTED quote produces a `Contract` row (with `source_quote_id`/`source_quote_revision` pinned) and a PDF whose total matches the quote total exactly; a second click does not create a duplicate Contract; a subsequent quote re-sync that changes the total surfaces the drift warning instead of silently updating or silently doing nothing.

**Dependency:** Phase 3 (needs the synced quote data and status visible/queryable).

---

## Phase 5 — BI Dashboard điều hành

**Goal:** add ZENA-specific business KPIs to the existing dashboard/widget infrastructure — no new dashboard framework.

**Work:** extend `KpiService`/`DashboardController` with new KPI cards:
- Doanh số theo tháng (sum of `Opportunity.estimated_fee` or synced quote totals, where `pipeline_stage = won`, grouped by month).
- Pipeline value theo stage (sum of `estimated_fee` grouped by `pipeline_stage`, mirroring the CRM board groups).
- Công nợ (outstanding `ContractPayment` amounts, overdue flagged).
- Hiệu quả sale (win-rate per `sales_owner_id`: won / (won + lost + no_bid)).
- Hiệu quả gói dịch vụ (win-rate and average fee per `service_category`).

All five must follow `KpiService`'s existing `Cache::remember($cacheKey, 60, ...)` convention — these are new cards on existing infrastructure, not a reason to add uncached heavy aggregate queries alongside it.

**Acceptance criteria:** dashboard renders all five cards with real tenant data (not placeholder/demo values), respecting existing tenant-isolation and RBAC patterns on the dashboard endpoints.

**Dependency:** needs Phase 1 (design throughput as an optional KPI candidate), Phase 2-4 (quote/contract data) to have real numbers to show — building this against empty CRM data would produce a dashboard nobody trusts.

---

## Phase 6 — Cổng khách hàng

**Goal:** let a client see their project's progress, delivered documents, quote/contract summary, and outstanding balance, without staff RBAC/roles.

**This phase needs its own brainstorming session before implementation** — two security-sensitive decisions this spec deliberately does not lock in:
1. The client auth mechanism (magic-link tied to `Account.email` vs. staff-provisioned login).
2. **Visibility scope per `Account`**: one `Account` can have multiple `Opportunity`/`Project` records, and potentially multiple client-side stakeholders (chủ đầu tư vs. người giám sát công trình) who may need different views of the same Account's data. The follow-up brainstorm must explicitly define what a logged-in client identity can see — all Projects under their Account, or scoped further — before any portal query code is written; this is not a detail to leave implicit.
3. **Data retention/deletion for client accounts**: once clients can log in and see their own data, questions of how long it's kept and whether a client can request deletion/export become real (Vietnam's Personal Data Protection Decree applies to any system holding customer personal data, regardless of company size). This does not need to be solved now, but the follow-up brainstorm must at least decide whether it's in scope for Phase 6 v1 or explicitly deferred — not silently ignored.

Do not start coding Phase 6 without that follow-up design conversation covering all three points.

**What is already decided:**
- Read-only surface, separate auth scope from staff RBAC (no `rbac:*` middleware reuse — needs its own guard).
- Content: `DesignItem` status/progress (Phase 1), `Document` where `status = final`/approved, quote/contract summary (Phase 2-4), outstanding `ContractPayment` balance.

**Dependency:** Phase 1, 3, 4, 5 (it's a read projection over all of them).

---

## Phase 7 — AI có kiểm soát

**Goal:** narrow, low-risk AI-assisted drafting — never AI-generated pricing or legal content, every output requires explicit human acceptance before it's persisted.

**Scoped use cases (v1):**
1. Suggest `Opportunity.service_category` and a scope summary draft from `Lead.project_description` — shown as a suggestion banner on the lead-conversion form, not auto-applied.
2. Draft a `DesignItem` description from project type + `item_type` — same accept-before-save pattern.
3. Compare a project's uploaded `Document`s against its `WorkTemplate` checklist and flag likely-missing items — a read-only report, not an auto-created task.

**Hard rule, non-negotiable:** AI never originates pricing, unit rates, or legal/contractual language — those values only ever come from `zena-boq-core`'s calibrated data or existing approved templates. Every AI output is labeled as a suggestion in the UI and requires an explicit accept action to become real data.

**Data-minimization rule, equally non-negotiable:** "AI có kiểm soát" controls *both* directions, not just the write-back. Before any use case ships, name exactly which fields are sent in the prompt (e.g., use case 1 sends only `Lead.project_description`, never sends `contact_hint`/phone/name or any other tenant's data) and confirm that's the minimum needed for the suggestion to be useful. Do not send full model dumps ("just serialize the Lead") to the API by default. This applies per-tenant: a tenant's data must never appear in another tenant's AI request context.

**Technical approach:** new `App\Services\AiAssistService` wrapping the Anthropic Messages API (see the `claude-api` skill reference for current model IDs/params), gated behind a new `ai.suggest` permission. Ship the three use cases one at a time (not as one PR) — each gets its own field-minimization review before it ships.

**Dependency:** all prior phases — AI needs stable CRM/quote/design data to have anything meaningful to work with.

---

## 4. Spec-lite scope note

Each phase above is deliberately specced at "enough to hand to another agent" depth (goal, data model sketch, API/route shape, dependencies, acceptance criteria) — not full TDD-level implementation detail. Per the standard process, **each phase should get its own short brainstorming pass (if anything here turns out ambiguous once someone is actually implementing it) and its own `writing-plans` implementation plan** before code starts on it. This spec is the shared reference so that plan stays consistent with the others.

## 5. Adversarial review — findings applied

A strict self-review pass was run against the original v1 of this spec before implementation planning began. All findings below were fixed inline in the sections above; listed here for traceability so later readers know why certain rules exist.

**Critical (fixed):**
- C1 — Phase 2 had no tenant restriction; any ZenaManage tenant could pull Z.E.N.A's commercial data via an arbitrary project code. → tenant-gate requirement added, with a required test.
- C2 — Phase 1's optional link to `WorkInstanceStep` created two overlapping, undefined status authorities for the same "step". → explicit authority rule added (DesignItem.review_status is sole authority for the client cycle; no auto-sync either direction).
- C3 — Phase 4 generated a Contract from a quote total with no pinning to a specific quote revision, and no handling for the quote changing afterward. → revision pinning + drift-warning requirement added.

**Important (fixed):**
- I4 — Phase 1's `review_status` chain read as linear; real design work needs loop-backs (especially `approved → revision_requested` for late change requests). → explicit transition graph added.
- I5 — Phase 1 had no audit-trail requirement despite the CRM slice already establishing `EventRecord` for exactly this kind of history. → made mandatory, matching the CRM precedent.
- I6 — Phase 7 only controlled the write-back direction ("human approves before saving"), not what tenant/customer data gets sent to a third-party API. → data-minimization rule added, per-field, per-tenant.
- I7 — Phase 2's cross-repo dependency had no coordination mechanism, risking ZenaManage-side work sitting blocked on an untracked external task. → explicit "build against a mock, don't wait" note added.

**Minor (fixed):**
- M8 — no effort sizing anywhere. → rough S/M/L sizing added to §3.
- M9 — no branching/PR strategy stated. → added to §3.
- M10 — Phase 4 had no guard against double-clicking "Tạo hợp đồng" creating duplicates. → duplicate-contract guard added.
- M11 — Phase 6's deferred brainstorm named only the auth mechanism, not account-level visibility scope (multi-stakeholder, multi-project-per-Account). → sharpened to name both.
- M12 — Phase 3 had no UI signal for stale synced data. → staleness label added to the quote card.

## 6. Second adversarial review pass — findings applied

A second strict review pass was run against the revised (§5) version of this spec, specifically looking for issues introduced *by* the first round of fixes, not just issues missed the first time. Two of the four "important" findings below (A, B) are exactly that: the first pass's own fixes for C1 and C3 were incomplete on their own.

**Important (fixed):**
- A — Phase 4's drift-guard (from C3) said to show a warning on quote drift but never said whether `Contract.total_value` auto-updates. → made explicit: never auto-updates, ever; resolution is always a manual human action.
- B — Phase 2's tenant-gate (from C1) didn't specify fail-closed behavior; an unset/empty config could accidentally compare empty-to-empty and defeat the entire fix. → fail-closed requirement added, with its own required test — this is now called out as the single most important test in the phase, since it's verifying the roadmap's original critical fix actually holds.
- C — Phase 2 used one shared secret for two different call directions (outbound read, inbound webhook), so a leak on either side would compromise both. → split into `ZENA_BOQ_READ_API_SECRET` and `ZENA_BOQ_WEBHOOK_SECRET`.
- D — Phase 2's new inbound webhook route is necessarily public (no session auth) and had no rate-limiting, unlike other public-facing sensitive endpoints already in this codebase. → `throttle` middleware requirement added, matching existing convention.
- E — Phase 7 claimed to depend on "all prior phases," but at least two of its three use cases only need data that predates this entire roadmap. → corrected: "AI last" is documented as the user's deliberate risk-sequencing choice, not a technical blocker, so a future reader doesn't mistake it for a hard dependency.

**Minor (fixed):**
- F — Phase 1's `approved` transition had no record of how/by whom client agreement was actually confirmed, despite the product's own stated bias toward evidence-bearing records. → added a required `approval_evidence` field.
- G — Phase 1's `sent_to_client` transition didn't require any file to actually be attached. → added a required-attachment validation.
- H — Phase 5 didn't say to reuse `KpiService`'s existing cache convention. → added explicitly.
- I — Phase 6's deferred brainstorm didn't mention data retention/deletion for client accounts. → added as a third point that brainstorm must at least explicitly decide on (in-scope or deferred), not silently skip.
- J — Phase 3 didn't say which permission gates triggering the BOQ-linking action from the empty state. → clarified as `crm.manage`, consistent with every other CRM mutation.

## 7. Phase 2 brainstorm review (2026-07-10) — findings applied

Before writing Phase 2's implementation plan, its "create a Z.E.N.A tenant" addition (itself added during this brainstorm session, not part of the original two review passes above) was reviewed and found under-specified in two places:

- K — The tenant-resolution mechanism was unstated: three options existed (hardcoded ID, name-lookup, manual copy-to-env) with materially different idempotency/correctness properties, and the spec text didn't pick one. → resolved with the user: name-based runtime lookup (`Tenant::where('name', config(...))->value('id')`), never a hardcoded/copied ID. This also surfaced a second, previously-invisible fail-closed failure mode (configured name resolves to no row) that a hardcoded-ID approach wouldn't have had — both failure modes are now required test cases.
- L — "Migration/seeder" conflated two mechanisms this codebase treats as distinct (schema vs. business data, per the existing `ZenaPermissionsSeeder`/`ZenaRbacSeeder` precedent). → corrected to seeder-only.
- M (minor) — Config file/keys and the tenant's lookup name weren't pinned down. → `config/zena_boq.php` with named keys, `'Z.E.N.A'` as the fixed seeder lookup key, specified explicitly.
