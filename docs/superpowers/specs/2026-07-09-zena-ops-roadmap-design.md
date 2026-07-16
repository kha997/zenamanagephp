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
Phase 7: AI có kiểm soát (Use Case 1)         -> sequenced last by choice, not by hard technical dependency (see note below)
Phase 8: AI Use Case 2 (mô tả DesignItem)     -> depends on Phase 1, 7 (uses AiAssistService + ai.suggest permission); Phase 3 optional enrichment
Phase 9: AI Use Case 3 (checklist tài liệu)   -> depends on Phase 1 (WorkTemplate/WorkInstance); no AI/LLM call — pure PHP comparison, see note below
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

**Confirmed real facts (checked directly against `zena-boq-core`'s Prisma schema and Next.js routes, 2026-07-10):**
- `QuoteStatus` enum is `DRAFT | ISSUED | ACCEPTED | REJECTED | SUPERSEDED` (5 values, not just "ISSUED/ACCEPTED/...").
- `CalibrationStatus` enum is `UNCALIBRATED | CALIBRATED`.
- A real page route exists at `web/src/app/quotes/[id]/page.tsx` — the external link can deep-link directly to `{zena_boq.base_url}/quotes/{external_quote_id}`, not just to the project.
- `Quote.revision: Int` exists on the Prisma model but is **not yet captured** by Phase 2's `ZenaBoqIntegrationService::fetchLatestQuote()` return shape or `external_quote_snapshot`.

**Work:**
- Extend `ZenaBoqIntegrationService::fetchLatestQuote()`'s returned array with `'revision' => (int) ($quote['revision'] ?? 0)`, and have `syncExternalQuote()` store it into `external_quote_snapshot['revision']`. No new column, no migration — it lives inside the existing `external_quote_snapshot` JSON blob alongside `subtotal`/`vat_amount`/`total`/`status`/`calibration`/`issued_at`. Phase 3 stores this but does not render it; Phase 4 is the consumer (see below).
- Add a "Báo giá" card to `resources/views/crm/opportunity-show.blade.php`: subtotal/VAT/total (existing money-format convention), a quote-status badge, a visually distinct `UNCALIBRATED`/`CALIBRATED` badge (must not be able to be mistaken for each other — hard requirement carried over from `zena-boq-core`'s own governance rules), a "đồng bộ lần cuối: X" relative-time label sourced from `external_quote_synced_at` (via `->diffForHumans()`, matching this codebase's only two existing precedents in `CalendarIntegration`/`ProjectActivity` — app locale is `en`, so no new Vietnamese-locale infrastructure is introduced here), flagged visually (muted/warning color) when **older than 14 days**, and an external link (`target="_blank"`) to `{base_url}/quotes/{external_quote_id}`. No embedding/iframe — link out only, since the source of truth's UI lives there.
- The quote-status badge reuses the existing `<x-ui.status-badge>` component, extended with the three missing `QuoteStatus` match arms (`issued`, `accepted`, `superseded` — `draft`/`rejected` already exist there for other features). The calibration badge is a **new, dedicated** `<x-ui.calibration-badge status="...">` component — deliberately not folded into the generic status-badge, because the "must not be mistaken for each other" requirement is a data-quality/governance signal, not an ordinary workflow status, and warrants its own bespoke, safety-oriented visual (solid rose/warning-glyph for `UNCALIBRATED`, solid emerald/check-glyph for `CALIBRATED`).
- `Web\CrmPageController::showOpportunity()` gains a private `buildBoqCardViewModel(Opportunity $opportunity): ?array` that returns `null` when not linked (→ empty state) or the source-agnostic shape below when linked. The Blade template consumes only this view-model, never `external_quote_snapshot` fields directly.

**Acceptance criteria:** an Opportunity with a synced quote shows the card; one without a linked `zena-boq-core` project shows a "Chưa liên kết báo giá" empty state. The action to trigger the Phase 2 linking flow from that empty state requires `crm.manage` (same as every other CRM mutation) — a `crm.view`-only user sees the empty state with no action button, not a disabled/broken one.

**Forward-compat note:** the view-model shape is `{subtotal, vat_amount, total, status, calibration, synced_at, is_stale, external_url}` (`revision` deliberately excluded from the view-model — Phase 3 stores it in `external_quote_snapshot` for Phase 4 to read directly from the model, not through this display-oriented shape), assembled by the controller from `external_quote_snapshot`, rather than reading `external_quote_snapshot` fields directly in the Blade template. This is what lets the deferred internal-quotation fallback (see Phase 2's "Non-Z.E.N.A tenants" note) reuse the same card later without a rewrite — even though only the `zena-boq-core` source exists today.

**Dependency:** requires Phase 2's cached snapshot fields to exist and be populated.

---

## Phase 4 — Tự động hoá hợp đồng

**Goal:** when an Opportunity is WON and its linked quote is `ACCEPTED`, let staff generate a draft `Contract` pre-filled from the quote total, with a generated PDF, in one action.

**Confirmed real facts (checked directly against this codebase, 2026-07-10):**
- `Contract` belongs to a `Project` (`project_id`), not an `Opportunity` — an Opportunity only gets a `Project` via its existing, separate `convert()` action (requires `pipeline_stage = won`; guarded against double-conversion). A WON Opportunity can sit unconverted indefinitely.
- The "`DeliverableTemplate`/`DeliverableTemplateVersion` + `DeliverablePdfExportService` pattern" is really two separable things: `DeliverablePdfExportService::render(html, options, meta)` is a generic, domain-agnostic HTML→PDF converter (shells out to Node/Playwright) — genuinely reusable as-is. `DeliverableTemplate`/`DeliverableTemplateVersion` (the editable-template-with-merge-fields part) is tied specifically to the WorkInstance/deliverable domain via a separate `WorkInstanceExportBundleService::renderHtmlForInstance()` — not a drop-in generic contract-templating system.
- The existing deliverable-export flow (`WorkInstanceController`) generates a PDF **on demand and streams it as a direct download response** (`Content-Disposition: attachment`) — it is never persisted to disk or a `Document` model. This phase follows the same pattern for the Contract PDF.
- `Contract`'s model default `currency` is `'USD'` — a legacy scaffold default, inconsistent with the rest of this Vietnamese-market app (VND/`₫` everywhere else). This phase explicitly overrides it to `'VND'` for auto-created contracts.

**Work:**
- New action on the Opportunity page: "Tạo hợp đồng" (visible only when `pipeline_stage = won` and `external_quote_snapshot.status = ACCEPTED` — this scopes the feature to BOQ-integrated tenants, matching Phase 2/3's Z.E.N.A-only gating; non-integrated tenants keep using the existing manual Contract-creation form, untouched by this phase). **Permission layering** (the button's own visibility is gated by `crm.manage`, matching the BOQ card's pattern on this same page, but the endpoint enforces all permissions the operations it actually performs already require elsewhere in this codebase): `OpportunityPolicy::convert()` requires the distinct `crm.convert` permission (not `crm.manage`) — if the Opportunity isn't yet converted, the endpoint must call `$this->authorize('convert', $opportunity)` before auto-converting, exactly like the existing `convert()` action does, so a `crm.manage`-only user without `crm.convert` cannot trigger project conversion through this back door. Likewise `ContractPolicy::create()` requires `contract.create` — the endpoint must call `$this->authorize('create', Contract::class)` before creating the `Contract`, so a user without that permission cannot create contracts through this action either.
- **Auto-converts to a `Project` first if not already converted.** If the Opportunity has no `converted_project_id` yet, "Tạo hợp đồng" calls the existing `convert()` logic before creating the `Contract` — one action, as the acceptance criteria requires, not two manual steps.
- Creates a `Contract` record: `project_id` (from convert, existing or new), `total_value = external_quote_snapshot.total`, `client_name` = the linked `Account`'s `display_name`, `currency = 'VND'`, `code` auto-generated as `CTR-` + 8 random uppercase chars (mirroring `Project::generateCode()`'s exact retry-then-ULID-fallback collision-avoidance pattern), `status = draft` (model default).
- **Three new nullable columns on `contracts`**: `source_opportunity_id`, `source_quote_id`, `source_quote_revision` — pinned at creation time from the Opportunity's `id` and its `external_quote_snapshot.id`/`.revision`. These did not appear in the original draft of this section; without `source_opportunity_id` specifically, the duplicate-guard and drift-guard below have no way to find "the contract this Opportunity's action created," since `Contract` otherwise only links to a `Project` (which can have multiple contracts for other reasons — the model already has a `version`/`scopeLatestVersion` concept for contract amendments unrelated to this feature).
- **Quote-drift guard:** computed live, never stored — compares the `Contract`'s pinned `source_quote_id`/`source_quote_revision` against the Opportunity's *current* `external_quote_id`/`external_quote_snapshot.revision` at render time. If a later "Đồng bộ báo giá" (Phase 2) pulls a different quote/revision than what's pinned, show a visible warning on both a new "Hợp đồng" card on the Opportunity page and the existing Contract show page ("Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp") rather than silently letting the two drift apart unnoticed. **`Contract.total_value` and the pinned `source_quote_id`/`source_quote_revision` must never be auto-updated by a re-sync, ever** — a `Contract` is treated as a point-in-time commercial document once created (it may already be reflected in an issued PDF), so silently rewriting its total on drift detection would be worse than the drift itself. The warning is informational only; resolving it (regenerating a new Contract, manually amending, or explicitly dismissing) is always a deliberate human action, never automatic.
- **Duplicate-contract guard:** before creating, check `Contract::where('source_opportunity_id', $opportunity->id)->exists()` — if found, "Tạo hợp đồng" must not silently create a second one; link to the existing Contract instead.
- **PDF generation:** a new, fixed Blade view (`resources/views/contracts/pdf.blade.php` — not an editable/versioned template, per this phase's brainstorm decision) rendered to an HTML string via `view(...)->render()`, then passed to the existing `DeliverablePdfExportService::render()` for HTML→PDF conversion, streamed back as a direct download exactly like `WorkInstanceController`'s existing pattern — no new storage infrastructure, no `Document` model entry.

**Acceptance criteria:** one action from a WON Opportunity with an ACCEPTED quote produces a `Contract` row (with `source_opportunity_id`/`source_quote_id`/`source_quote_revision` pinned) and a downloadable PDF whose total matches the quote total exactly; a second click does not create a duplicate Contract (links to the existing one instead); a subsequent quote re-sync that changes the total surfaces the drift warning (on both the Opportunity and Contract pages) instead of silently updating or silently doing nothing.

**Dependency:** Phase 3 (needs the synced quote data and status visible/queryable).

---

## Phase 5 — BI Dashboard điều hành

**Goal:** add ZENA-specific business KPIs, on real tenant data, with real caching.

**Confirmed real facts (checked directly against this codebase, 2026-07-11):**
- `App\Services\KpiService` (the class this section originally said to "extend") is **entirely mock data** — every method returns a hardcoded literal (e.g. `return 1247; // Mock data`), not a database query. Its only consumer, `KpiController`, is routed at `/api/v1/universal-frame/kpis` behind `rbac:admin`, and nothing in any current Blade view or JS file calls it — it is dead code from before this app's operator-first consolidation, not the live dashboard.
- The real, live, tenant-scoped dashboard every user actually sees is `Web\AppController::dashboard()` → `resources/views/app/dashboard.blade.php` ("Bảng điều hành dự án") — plain direct Eloquent queries per request, no caching at all today.
- `ContractPayment` already has a `STATUS_OVERDUE` enum value, but no job/command in this codebase ever transitions a payment into it — "overdue" must be computed live (`due_date < now()`), not read from that stored status.
- `Opportunity` has no dedicated "won at"/"closed at" timestamp — only `expected_close_date` (a forecast field) and `updated_at`. Monthly revenue grouping uses `updated_at`'s month as the closest available signal; adding a new timestamp column purely for this KPI would be over-engineering for what's needed.

**Work:** this phase does **not** touch `KpiService`/`KpiController` (left alone, genuinely dead, out of scope) and does **not** add cards to the existing operational dashboard. Instead:
- New service `App\Services\BusinessKpiService` — tenant-scoped, each of the five KPIs independently wrapped in `Cache::remember("business_kpi_{$key}_{$tenantId}", 60, ...)` (this is where the spec's original caching intent actually lands, on new code, since the old service's convention was never real to begin with).
- New dedicated page, not mixed into the operational dashboard: `Web\CrmReportController::index()` at `/operator/crm/reports` (route name `operator.crm.reports`), gated by the existing `crm.view` permission (no new permission — CRM viewers are exactly who should see CRM data rolled up), with a new nav entry under the existing "Kinh doanh" section in `layouts/operator.blade.php`. Nested under the CRM URL namespace rather than the existing unrelated `/operator/reports` prefix — that prefix already belongs to a completely different feature, a raw CSV dataset-export tool (`Web\ReportPageController`), and reusing it would have conflated two unrelated concepts.
- Five KPIs:
  - **Doanh số theo tháng**: WON opportunities grouped by month of `updated_at`; revenue per opportunity = `external_quote_snapshot.total` when present (from Phase 2/3, only for BOQ-integrated tenants), else `estimated_fee` — works correctly for both integrated and non-integrated tenants.
  - **Pipeline value theo stage**: `sum(estimated_fee)` grouped by `pipeline_stage`.
  - **Công nợ**: `ContractPayment` where `status != paid`, summed; a separate overdue sub-total computed live as `due_date < now()`.
  - **Hiệu quả sale**: grouped by `sales_owner_id`, `won / (won + lost + no_bid)`.
  - **Hiệu quả gói dịch vụ**: grouped by `service_category`, win-rate + average fee (same quote-total-else-estimated_fee rule as the first KPI).

**Acceptance criteria:** the new page renders all five KPIs with real tenant data (not placeholder/demo values), respecting tenant isolation and the `crm.view` RBAC gate; a second page load within 60 seconds does not re-run the aggregate queries (cache hit).

**Dependency:** needs Phase 1 (design throughput as an optional KPI candidate — not built in this pass, the five KPIs above are the full scope), Phase 2-4 (quote/contract data) to have real numbers to show — building this against empty CRM data would produce a dashboard nobody trusts.

---

## Phase 6 — Cổng khách hàng

**Goal:** let a client see their project's progress, delivered documents, quote/contract summary, and outstanding balance, without staff RBAC/roles.

**The three required security-sensitive decisions, resolved with the user on 2026-07-11:**
1. **Client auth mechanism**: magic-link tied to `Account.email` — no password, no staff-provisioned account.
2. **Visibility scope per `Account`**: v1 scopes to the whole `Account` — a logged-in client identity sees every `Project`/`Opportunity` under their `Account`, not scoped further per individual stakeholder (chủ đầu tư vs. người giám sát công trình share the same view in v1). A contact-level model with per-person project scoping was considered and explicitly deferred, not built now.
3. **Data retention/deletion**: explicitly deferred out of v1 scope, not silently dropped — must be revisited before real client data accumulates at any meaningful volume (Vietnam's Personal Data Protection Decree applies regardless of company size). This is a known, documented gap, not an oversight.

**Confirmed real facts (checked directly against this codebase, 2026-07-11):**
- No client-facing auth guard exists today — only `web` (staff, session) and `api` (staff, Sanctum), both backed by the `User` model. A `client` guard is new infrastructure, not a reuse of anything.
- `Tenant` already has both `slug` and `domain` columns (`slug` already used for uniqueness elsewhere), but neither is currently used by any middleware to resolve a tenant from an inbound request — there is no existing subdomain/custom-domain tenant-resolution convention to align with. The portal therefore identifies its tenant via a URL path segment (`/portal/{tenant_slug}/...`), reusing the existing `slug` field, since a client only ever knows their own email, never a tenant ID.
- `Document`'s real status values are `approved`/`rejected` (set in `Web\DocumentController.php`) — there is no `final` status. The original spec's "`status = final`/approved" was an unverified guess; the portal reads `status = 'approved'` only.
- Real `Mail`-based infrastructure already exists (`app/Mail/PasswordResetEmail.php` is the closest existing precedent for a "here's a link, click it" email) — the magic-link email follows that same pattern, not new mail infrastructure.
- **`DesignItem` (Phase 1) does not exist on the base branch yet** — PR #153 has been open, unmerged, since long before this phase's brainstorm. This phase's spec can still be written now (the design doesn't depend on Phase 1's code existing to be designed), but PR #153 must merge before Phase 6's implementation can actually query `DesignItem` for the portal's progress content — flag this again at implementation-sequencing time, the same way every prior phase's base-branch merge has been confirmed before starting.

**Architecture:**
- New `client` auth guard (session driver), provider backed by the `Account` model itself acting as the identity — `Account` implements Laravel's `Authenticatable` contract via the standard `Illuminate\Auth\Authenticatable` trait (no password column needed; magic-link auth never calls `getAuthPassword()` for credential checking).
- New table `portal_login_tokens`: `account_id`, a hashed single-use `token`, `expires_at` (15-30 minutes), `used_at`.
- Login flow: `GET /portal/{tenant_slug}/login` (email entry form) → `POST` validates the email against `Account::where('tenant_id', $tenant->id)->where('email', $email)->first()` (tenant resolved from the URL slug first) → **always shows the same generic "check your inbox" message regardless of whether a match was found**, to prevent account-existence enumeration → on a real match, emails a single-use link → `GET /portal/{tenant_slug}/login/{token}` validates the token (unexpired, unused, and belonging to an `Account` in the URL's own tenant), marks it used, authenticates via the `client` guard, redirects into the portal.
- **The magic-link email must be queued, not sent inline (`PortalLoginLinkEmail implements ShouldQueue`)** — sending it synchronously creates a measurable response-time difference between a matched and unmatched email (SMTP/render latency only on a match), which is a timing side-channel that defeats the byte-identical-response anti-enumeration guarantee above; the two protections are not the same thing, and skipping either one reopens enumeration. **This requires a real async `QUEUE_CONNECTION` in production** (not `sync`, which still executes inline and provides no protection) — a deployment prerequisite for this phase, not just a code-level one. Found and fixed during this phase's final whole-branch review, after an earlier task mislabeled dropping `ShouldQueue` as a "test-only" change.
- New middleware (does not reuse any `rbac:*` staff middleware) gates all authenticated portal routes: checks the `client` guard AND that the authenticated `Account`'s `tenant_id` still matches the URL's resolved tenant — defense in depth beyond the guard alone.
- Portal content, scoped to every `Project`/`Opportunity` under the authenticated `Account` (per the v1 visibility decision above): `DesignItem` status/progress (Phase 1 — blocked on PR #153 merging first), `Document` where `status = 'approved'`, quote/contract summary (Phase 2-4 data), outstanding `ContractPayment` balance.

**Dependency:** Phase 1 (code must be merged, not just designed, before this phase's implementation can query `DesignItem`), Phase 3, 4, 5 (it's a read projection over all of them).

---

## Phase 7 — AI có kiểm soát

**Goal:** narrow, low-risk AI-assisted drafting — never AI-generated pricing or legal content, every output requires explicit human acceptance before it's persisted.

**Scoped use cases (v1):**
1. Suggest `Opportunity.service_category` and a scope summary draft from `Lead.project_description` — shown as a suggestion, not auto-applied. **This is the only use case being built in this pass — see below.**
2. Draft a `DesignItem` description from project type + `item_type` — same accept-before-save pattern. Deferred to its own future brainstorm+plan cycle.
3. Compare a project's uploaded `Document`s against its `WorkTemplate` checklist and flag likely-missing items — a read-only report, not an auto-created task. Deferred; **also blocked on new schema that doesn't exist yet** — confirmed by reading `WorkTemplate`/`WorkTemplateStep`/`WorkTemplateField` directly, 2026-07-12: there is no "expected/required document types per template step" concept anywhere in this codebase today, only a generic untyped `config_json` field on `WorkTemplateStep`. This use case needs its own schema-design brainstorm before it can even be scoped, not just an AI-wiring pass.

**Hard rule, non-negotiable:** AI never originates pricing, unit rates, or legal/contractual language — those values only ever come from `zena-boq-core`'s calibrated data or existing approved templates. Every AI output is labeled as a suggestion in the UI and requires an explicit accept action to become real data.

**Data-minimization rule, equally non-negotiable:** "AI có kiểm soát" controls *both* directions, not just the write-back. Before any use case ships, name exactly which fields are sent in the prompt (e.g., use case 1 sends only `Lead.project_description`, never sends `contact_hint`/phone/name or any other tenant's data) and confirm that's the minimum needed for the suggestion to be useful. Do not send full model dumps ("just serialize the Lead") to the API by default. This applies per-tenant: a tenant's data must never appear in another tenant's AI request context.

**Technical approach:** new `App\Services\AiAssistService` wrapping the Anthropic Messages API directly via Laravel's HTTP client, gated behind a new `ai.suggest` permission. Ship the three use cases one at a time (not as one PR) — each gets its own field-minimization review before it ships.

**Confirmed real facts (checked directly against this codebase, 2026-07-12):**
- No existing Anthropic/Claude API integration anywhere in this codebase (matches the original 10-goal assessment's "AI 0%" finding) — this is genuinely new infrastructure, not extending anything.
- No `config/services.php` file exists in this codebase — following the precedent set by Phase 2 (a dedicated `config/zena_boq.php` rather than piggybacking on a generic third-party-services file), this phase creates its own dedicated `config/ai.php`.
- **The lead-conversion form does not currently have `service_category` or an editable scope-summary field at all**, even though the backend (`Api\LeadController::convert()`) already validates and accepts `service_category` (`nullable`, must be in `Opportunity::VALID_SERVICE_CATEGORIES`). Today, `service_scope_summary` is silently hardcoded to a verbatim copy of `Lead.project_description` server-side (`LeadController.php:246`) — there is no user-editable field for it in the UI. This means Use Case 1 is not merely "add an AI suggestion on top of an existing form" — it also adds these two fields to the conversion form for the first time.

**Use Case 1 — decisions applied (resolved with the user, 2026-07-12):**
- **Model**: Claude Haiku 4.5 (`claude-haiku-4-5-20251001`) — fast/cheap, sufficient for a simple classify-and-summarize task with no complex reasoning required.
- **Trigger**: a manual "Gợi ý AI" button on the conversion form, not an automatic fetch on form-open — avoids an API call every time staff merely opens the form without intending to use the suggestion.
- **Data sent**: `Lead.project_description` only. Nothing else from `Lead` (not `contact_hint`, not `source`, not any staff-identifying field) and nothing from any other model.
- **Data returned and validated, never trusted blindly**: `service_category` (checked against `Opportunity::VALID_SERVICE_CATEGORIES`; any value outside that enum is discarded, not silently coerced or passed through) and a short `scope_summary` draft (plain text, no formatting assumptions).
- **UI**: both new fields (`service_category` dropdown, `service_scope_summary` textarea) start empty/editable regardless of AI availability; the suggestion button pre-fills them but the user can freely edit before submitting; nothing is persisted until the existing explicit form-submit action.
- **Graceful degradation**: if the Anthropic API call fails, times out, or the key is unconfigured, the button surfaces an error and the form remains fully usable exactly as it is today (empty fields, manual entry) — the conversion flow itself must never be blocked by an AI failure.

**Dependency:** all prior phases — AI needs stable CRM/quote/design data to have anything meaningful to work with.

---

## Phase 8 — AI Use Case 2: gợi ý mô tả DesignItem

**Goal:** the second of Phase 7's three deferred AI use cases, built to its own brainstorm+plan cycle per the "ship one at a time" rule. A manual "Gợi ý AI" button on the DesignItem creation form drafts a `description` from the item's type and (when resolvable) the originating project's service category — same accept-before-save pattern as Use Case 1, never auto-applied.

**Confirmed real facts (checked directly against this codebase, 2026-07-12):**
- `design_items` has no `description` column at all today (`database/migrations/2026_07_10_090000_create_design_items_table.php`) — this use case adds one, it does not merely wire AI onto an existing field.
- `Project` has no "project type"/category concept anywhere (`app/Models/Project.php`'s `$fillable` has no such field). The nearest existing concept is `Opportunity.service_category` (the CRM enum from Phase 3+), reachable only by reverse lookup (`Opportunity::where('converted_project_id', $projectId)->value('service_category')`) — and only when the Project was created via the CRM conversion flow. Projects created directly (not through CRM) have no such value.
- Only the DesignItem **create** form (`resources/views/design-items/create.blade.php`, route `operator.design-items.store`) is wired to the web UI. `Api\DesignItemController::update()` exists but has no corresponding web route or edit form today — so this use case's UI surface is create-only; there is nothing to extend on an edit screen because no edit screen exists.
- Because the DesignItem does not yet exist when the suggestion button is clicked (unlike Use Case 1, where the Lead row already existed before conversion), the suggestion endpoint cannot look anything up by a saved record's ID. It must accept the form's current in-progress selections (`project_id`, `item_type`) as request parameters, tenant-validate `project_id` the same way `DesignItemController::rules()` already does, then resolve the service category server-side from that validated project.

**Use Case 2 — decisions applied (resolved with the user, 2026-07-12):**
- **Project-type source**: resolved from `Opportunity.service_category` via the reverse `converted_project_id` lookup when available; `null`/absent when the Project has no CRM origin — the suggestion still works with `item_type` alone in that case, just with less context. No new schema added to `Project`.
- **Data sent to Anthropic**: `item_type` (enum) and, when resolvable, `service_category` (enum) — both closed-vocabulary values, never free text, never the project's name, client name, or any other field.
- **Data returned and validated**: a single `description` string (plain text). Unlike Use Case 1 there is no enum to check the output against — the validation here is structural (non-empty, reasonable length) rather than membership-in-an-enum, since a free-text description has no fixed vocabulary to compare against.
- **UI**: a new `description` textarea on the create form only, empty/editable regardless of AI availability, pre-filled by the button but freely editable before submit; nothing persisted until the existing explicit "Tạo" submit action.
- **Graceful degradation**: identical contract to Use Case 1 — any API failure returns `null` from the service, the button surfaces a plain failure message, and the create form remains fully usable with a manually-typed description exactly as it works today (empty field).
- **Permission**: reuses the existing `ai.suggest` permission from Phase 7 — no new permission needed, since this is the same class of action ("invoke a paid AI suggestion call") already gated by that permission.

**Technical approach:** extend `App\Services\AiAssistService` with a second public method, `suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array`, following the exact same internal pattern as `suggestLeadConversion()` (forced tool-use, fail-closed to `null`, no exceptions escape the service). New migration adds `description` (`text`, nullable) to `design_items`. New endpoint on `Web\DesignItemPageController` (mirroring `CrmPageController::suggestLeadConversion()`), gated by `rbac:design-item.manage` + `rbac:ai.suggest` (dual-permission layering, same rationale as Phase 7: separates "can create design items" from "can invoke paid AI calls"). New JS module `resources/js/ai-design-item-suggest.js`, same vanilla-IIFE + `data-*` attribute convention as `ai-lead-suggest.js`, reading the form's currently-selected `project_id`/`item_type` values client-side at click time (not server-rendered into a data attribute, since neither is fixed until the user picks them).

**Dependency:** Phase 1 (`DesignItem` itself), Phase 7 (`AiAssistService`, `ai.suggest` permission, established data-minimization/graceful-degradation pattern). Phase 3 (`Opportunity.service_category`) for the optional project-type enrichment, though the feature degrades gracefully without it.

---

## Phase 9 — AI Use Case 3: checklist tài liệu theo template

**Goal:** the third and last of Phase 7's originally-deferred AI use cases. A read-only report on a Project's detail page flags which document types, required by the project's applied WorkTemplate steps, have not yet been uploaded — never an auto-created task, never a write of any kind.

**Confirmed real facts (checked directly against this codebase, 2026-07-12):**
- No "required document types per template step" concept exists anywhere — confirmed again (first found during Phase 7's own scoping pass) by reading `WorkTemplateStep`/`WorkTemplateField` directly: `WorkTemplateStep` has only a generic untyped `config_json`, and `WorkTemplateField` is a form-field concept (`type`, `is_required`, `enum_options_json`) unrelated to document requirements.
- `Document.document_type` is validated inconsistently across the codebase: `Api\DocumentController` enforces a closed enum (`drawing`, `specification`, `contract`, `report`, `photo`, `other`) at `store()`/`update()`, but `Api\SimpleDocumentController` and `Web\DocumentController` treat it as an unconstrained `string|max:100`. No shared `Document::VALID_DOCUMENT_TYPES` constant exists — the enum values are only ever written as inline validation strings.
- A separate, already-existing table `work_instance_step_attachments` (model `App\Models\WorkInstanceStepAttachment`) DOES link a file directly to a specific `WorkInstanceStep` via `work_instance_step_id` — but has no `document_type`/category field, and no web UI anywhere uses it (only `Api\WorkInstanceController`'s `uploadStepAttachment`/`listStepAttachments`/`deleteStepAttachment`, unused by any Blade view). This is a distinct mechanism from `Document` and was NOT chosen as this use case's data source (see decisions below) — noted here so a future reader doesn't rediscover it as if new.
- A `WorkInstance` can apply to a `Project` OR to an individual `Component` within it (`scope_type` = `project`/`component`, set via `Api\WorkTemplateController::applyToProject()`/`applyToComponent()`). A single Project can therefore have multiple `WorkInstance` rows.
- No web UI exists for authoring `WorkTemplate`/`WorkTemplateStep` definitions at all — templates are created/edited only via `Api\WorkTemplateController::store()`/`update()` (JSON `steps[]` payload) or the `WorkTemplateBaselineSeeder`. Building a web template editor is out of scope for this use case.

**Use Case 3 — decisions applied (resolved with the user, 2026-07-12):**
- **No AI/LLM call at all.** This is a deterministic set-comparison (required document types minus present document types), not a task requiring language-model reasoning. Calling Anthropic here would add cost, latency, and a failure mode for something `array_diff()` already does correctly and instantly, and it would mean sending project document metadata to a third party for no benefit — a strictly worse outcome under this phase's own data-minimization rule. This use case ships as pure PHP business logic; it is grouped under the "AI có kiểm soát" roadmap phase because of its product framing (the same class of "controlled, human-reviewed drafting" feature), not because it invokes an LLM.
- **Requirement declaration level**: per `WorkTemplateStep`, not per whole `WorkTemplate` — matches the "checklist theo từng bước" framing in the original spec text. New nullable JSON column `required_document_types` on `work_template_steps`, an array of values drawn from a newly-extracted `Document::VALID_DOCUMENT_TYPES` constant (`drawing`, `specification`, `contract`, `report`, `photo`, `other` — sourced from `Api\DocumentController`'s existing validation, made a reusable constant rather than fixing the inconsistency across the other two Document upload paths, which stays out of scope).
- **Matching granularity**: project-level, not step-level. A required document type counts as "present" if the Project has ANY `Document` row with a matching `document_type`, regardless of which step (or none) it was conceptually uploaded for. This deliberately avoids requiring `Document` rows to be linked to a specific `WorkInstanceStep` (a linkage that doesn't exist today — see `work_instance_step_attachments` note above) and matches the spec's own "flag likely-missing items" framing (advisory, not exact).
- **WorkInstance scope**: only `WorkInstance` rows with `scope_type = 'project'` are considered — WorkInstances applied to individual Components are out of scope for v1's report (a project could otherwise have an unbounded, harder-to-summarize set of per-component checklists).
- **Data leaves the system**: none. This is entirely server-side computation against already-tenant-scoped data; nothing is sent anywhere.

**Technical approach:** new migration adds `required_document_types` (JSON, nullable) to `work_template_steps`; `Document::VALID_DOCUMENT_TYPES` constant added and reused by both the new step-requirement validation (`Api\WorkTemplateController::store()`/`update()`, extending the existing `steps.*` payload shape) and the existing `Api\DocumentController` validation rules (replacing its inline enum string, not duplicating it). New `App\Services\DocumentChecklistService::buildReport(Project $project): array` — pure PHP, no HTTP calls — resolves the project's `scope_type = 'project'` WorkInstances, walks their steps (via the live `work_template_step_id` foreign key back to `WorkTemplateStep`, no snapshotting needed since this is read-only informational data, not a value a user edits), and diffs each step's `required_document_types` against the project's uploaded `Document.document_type` values. Rendered as a new "Checklist tài liệu" card on the existing `projects.show` Blade view (`Web\ProjectController::show()`), gated by the existing `work.view` permission (no new permission).

**Correction (verified while writing the implementation plan, 2026-07-12):** the `projects.show` page referenced above is the ONLY Project detail page in this codebase — it lives under the `app.` route-name prefix (`app.projects.show`, `Web\ProjectController::show()`), not `operator.`; there is no separate "operator"-namespaced Project page anywhere. This is simply how this codebase is structured (Project management predates the "operator" ZENA layer built across Phases 1-8), not a design flaw to fix here. `Web\ProjectController` has a stray, never-invoked `use Src\RBAC\Middleware\RBACMiddleware;` import (the line that would apply it is commented out) — dead code, not a second live RBAC system; the route still uses the same `rbac:` middleware alias family as every other phase (e.g. `rbac:project.write` on the update route). The GET show route itself has no `rbac:*` middleware at all today (any authenticated tenant user can view any project in their tenant) — adding one now to gate the whole page would be a scope-creeping behavior change. Instead, the new checklist card's visibility is decided in the Blade template itself via `Auth::user()->hasPermission('work.view')`, matching the precedent already used elsewhere in this codebase for conditionally showing a page section without gating the whole route.

**Dependency:** Phase 1 (`WorkTemplate`/`WorkInstance`/`DesignItem` infrastructure already exists independent of this roadmap's own phases — this is the one AI use case that does NOT depend on Phase 7's `AiAssistService`, since it makes no AI call).

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

## 8. Phase 3 brainstorm (2026-07-10) — decisions applied

Before writing Phase 3's implementation plan, its two under-specified points were resolved directly with the user, and the enum/route assumptions in the original Phase 3 section were verified against the real `zena-boq-core` repo rather than left as guesses:

- N — The spec said "flag visually... when older than a few days" without a number. → resolved with the user: **14 days**.
- O — Phase 4 needs `Quote.revision` pinned onto a `Contract` at creation time, but Phase 2's synced snapshot shape never captured it, and nothing in the original Phase 3 text said whether Phase 3 should close that gap. → resolved with the user: yes, Phase 3 extends `fetchLatestQuote()`/`external_quote_snapshot` to include `revision` now (stored, not rendered), so Phase 4 doesn't have to re-touch the Phase 2 service layer.
- P (verification, not a user decision) — The original Phase 3 text guessed at the `QuoteStatus` values ("`ISSUED`/`ACCEPTED`/...") and didn't confirm a real link target existed. Checked directly against `zena-boq-core`'s `web/prisma/schema.prisma` and `web/src/app/quotes/[id]/page.tsx`: the real enum is `DRAFT | ISSUED | ACCEPTED | REJECTED | SUPERSEDED`, and the `/quotes/[id]` page route is real, so the external link can deep-link to the specific quote rather than only the project.

## 9. Phase 4 brainstorm (2026-07-10) — decisions applied

Before writing Phase 4's implementation plan, exploring this codebase's actual `Contract` model and PDF-export pipeline (rather than assuming the original spec text's design held up) surfaced two real architectural gaps, both resolved directly with the user:

- Q — `Contract.project_id` is required, but a WON Opportunity is not guaranteed to have a `converted_project_id` yet (`convert()` is a separate, existing manual action). The original spec's "one action" acceptance criterion didn't say what "Tạo hợp đồng" should do if the Opportunity hasn't been converted yet. → resolved with the user: auto-convert first if not already converted, then create the `Contract` — a single action end-to-end, as originally intended, rather than forcing a separate manual convert step.
- R — The spec's "reuse the existing `DeliverableTemplate`/`DeliverableTemplateVersion` + `DeliverablePdfExportService` pattern" conflated a generic, reusable piece (`DeliverablePdfExportService`, pure HTML→PDF conversion) with a domain-specific piece (`DeliverableTemplate`/`Version`, an editable-template-with-merge-fields system tied to the WorkInstance/deliverable domain via its own `WorkInstanceExportBundleService`) that isn't actually a drop-in generic contract-templating system. Building an equivalent editable/versioned template system for contracts was a real, much heavier option on the table. → resolved with the user: use a fixed Blade view for the contract PDF content (not editable via UI), reusing only the genuinely generic `DeliverablePdfExportService` for the HTML→PDF conversion step — matching this codebase's YAGNI conventions, since nothing in the goal requires tenants to customize contract wording.
- S (gap-filling, not re-litigated with the user — a natural consequence of the codebase facts above, not a competing design) — the original spec said to pin `source_quote_id`/`source_quote_revision` on the `Contract` but never specified how the duplicate-guard or drift-guard would find "the contract this Opportunity's action created," since `Contract` only otherwise links to a `Project` (which can have multiple contracts for unrelated reasons — the model already supports amendments via `version`/`scopeLatestVersion`). → added a third new column, `source_opportunity_id`, so both guards have a direct, unambiguous lookup path.
- T (gap-filling, found while reading the actual authorization code, not re-litigated with the user) — the brainstorm's "gate the whole action by `crm.manage`" answer was a reasonable first cut, but reading `OpportunityPolicy::convert()` and `ContractPolicy::create()` directly showed both `crm.convert` and `contract.create` are pre-existing, distinct permission checks this codebase already enforces for the two operations (project conversion, contract creation) that this one action performs internally. Gating only by `crm.manage` would let a user without `crm.convert` or `contract.create` trigger both through this new endpoint — a real permission-escalation gap, not a hypothetical one. → the endpoint must call `$this->authorize('convert', $opportunity)` before auto-converting and `$this->authorize('create', Contract::class)` before creating the `Contract`, in addition to the `crm.manage` gate on the button/action itself — see the updated Phase 4 "Permission layering" note above.

## 10. Phase 5 brainstorm (2026-07-11) — decisions applied

Before writing Phase 5's implementation plan, exploring the actual `KpiService`/dashboard code (rather than trusting the original spec text's assumption that it was live, working infrastructure) surfaced that the thing this section said to "extend" is dead mock code, and two placement/data-source questions were resolved directly with the user:

- U (verification, not a user decision — but the single most consequential finding in this section) — `App\Services\KpiService` is entirely hardcoded mock data with no real consumer in the live UI (`KpiController`'s only route sits behind `rbac:admin` at a legacy `/api/v1/universal-frame/` prefix nothing currently calls). The actual live dashboard (`Web\AppController::dashboard()`) is a completely separate code path with no caching at all. Continuing to say "extend KpiService" would have sent an implementer down a dead-end path building real logic into unreachable code. → resolved: this phase ignores `KpiService`/`KpiController` entirely (left untouched, not deleted — out of scope) and builds a new `BusinessKpiService` + new dedicated page instead.
- V — the spec didn't say whether the five new KPIs belong on the existing operational dashboard or a separate page. → resolved with the user: a separate new "Báo cáo kinh doanh" page (`/operator/crm/reports`), not mixed into the task/project-focused operational dashboard — keeps the two concerns visually and architecturally distinct, matching the phase's own "BI Dashboard điều hành" framing as a distinct area. (Nested under the CRM namespace, not the pre-existing unrelated `/operator/reports` CSV-export prefix, per the final whole-branch review's confirmation this avoids conflating two different features.)
- W — the spec's "sum of `Opportunity.estimated_fee` or synced quote totals" for monthly revenue left an unresolved "or." → resolved with the user: prefer the synced quote's real total when available (`external_quote_snapshot.total`, from Phase 2/3 — only populated for BOQ-integrated tenants), falling back to `estimated_fee` otherwise — this is the only choice that produces correct numbers for both integrated and non-integrated tenants, since quote totals alone would leave the KPI empty for every tenant without the zena-boq-core integration.

## 11. Phase 6 brainstorm (2026-07-11) — decisions applied

This phase's spec explicitly required its own dedicated brainstorming session covering three named security-sensitive points before any code — see the Phase 6 section above for the resolved decisions themselves. This entry records the process and the additional facts uncovered while resolving them:

- X — client auth mechanism: magic-link vs. staff-provisioned login. → resolved with the user: magic-link tied to `Account.email`.
- Y — visibility scope per `Account`: whole-Account vs. per-contact scoping. → resolved with the user: whole-Account for v1 (every Project/Opportunity under the Account, no differentiation between chủ đầu tư/giám sát công trình yet). A per-contact model was described as the heavier alternative and explicitly not chosen — if a future need for differentiated stakeholder visibility emerges, that is a new brainstorm, not an assumed extension of this design.
- Z — data retention/deletion for client accounts. → resolved with the user: explicitly deferred out of v1, documented as a known gap (per the spec's own instruction not to silently ignore this point) — must be revisited before real client data accumulates.
- AA (verification, not a user decision) — the original spec's "no rbac:* middleware reuse — needs its own guard" instruction was confirmed accurate by checking `config/auth.php` directly: only `web`/`api` guards exist today, both backed by `User`, neither reusable for a client identity. This phase is building genuinely new auth infrastructure, not adapting existing middleware.
- BB (verification, not a user decision) — resolving how the portal identifies which tenant's `Account` table to search (a question the original spec didn't address at all, since it only discussed post-login visibility, not the pre-login tenant-lookup problem) found that `Tenant.slug` already exists and is already used for uniqueness elsewhere, but no existing middleware resolves tenant from a request today — so a `/portal/{tenant_slug}/...` URL structure was chosen as the natural, already-supported mechanism, rather than introducing subdomain-based routing that nothing else in this codebase currently does.
- CC (verification, not a user decision) — the original spec guessed `Document` status `final`/`approved`; checked directly against `Web\DocumentController.php` and found the real values are `approved`/`rejected`, no `final`. Corrected in the Phase 6 section above.
- DD (verification, not a user decision) — `DesignItem` (Phase 1) does not exist on the base branch; PR #153 remains unmerged. This doesn't block writing Phase 6's design or plan, but must be resolved (merged) before Phase 6's implementation can query `DesignItem` — flagged in the Phase 6 section's Dependency line as a merge-not-just-design requirement, distinct from Phase 3/4/5's dependency (which are already merged).

## 12. Phase 7 brainstorm (2026-07-12) — decisions applied

Before writing Phase 7's implementation plan, checking the actual `WorkTemplate`/lead-conversion code (rather than assuming all three scoped use cases were equally ready to build) found that only one of the three is fully grounded in existing data — a real scoping decision resolved directly with the user, plus a technical model choice:

- EE — the spec's three use cases were written as if equally ready to scope; investigating each found Use Case 3 ("compare Documents against a WorkTemplate checklist") has no underlying schema at all — no "expected/required document types per step" concept exists anywhere in `WorkTemplate`/`WorkTemplateStep`/`WorkTemplateField`, only a generic untyped `config_json` field. Use Case 2 (`DesignItem` description drafting) is plausible but not yet investigated in depth. → resolved with the user: this pass builds only Use Case 1 (already fully grounded — no new schema needed beyond two new UI fields), explicitly deferring Use Cases 2 and 3 to their own future brainstorm+plan cycles, matching the original spec's own "ship one at a time" instruction taken to its logical conclusion (not even brainstorming the un-ready ones prematurely).
- FF (verification, not a user decision) — the original spec described Use Case 1 as adding "a suggestion banner on the lead-conversion form," which reads as if the form already has the fields the suggestion would fill. Checked directly against `resources/views/crm/leads.blade.php` and `Api\LeadController::convert()`: the conversion form has no `service_category` or editable scope-summary input at all today — `service_scope_summary` is currently hardcoded server-side to a verbatim copy of `Lead.project_description`. → this means Use Case 1's actual scope includes adding these two fields to the form for the first time, not merely layering a suggestion onto fields that already exist.
- GG — model choice for the classify-and-summarize task. → resolved with the user: Claude Haiku 4.5 (`claude-haiku-4-5-20251001`), the fast/cheap tier — this task requires no complex reasoning, and Phase 7's own "narrow, low-risk" framing argues against defaulting to a heavier model without a demonstrated quality need.
- HH — automatic AI-suggestion fetch on form-open vs. a manual trigger. → resolved with the user: a manual "Gợi ý AI" button, not automatic — avoids an API call (and its cost) every time staff merely opens the conversion form without intending to use the suggestion.

## 13. Phase 8 brainstorm (2026-07-12) — decisions applied

Before writing Phase 8's implementation plan, checked the actual `DesignItem`/`Project` schema and the DesignItem web routes (rather than assuming Use Case 2's premise — "project type + item_type" — mapped cleanly onto existing fields, the same mistake Use Case 1's premise made in finding FF above):

- II (verification, not a user decision) — `design_items` has no `description` column today; confirmed by reading the table migration directly. Use Case 2's scope therefore includes adding this column, not just wiring AI onto an existing field — the same shape of gap as FF.
- JJ (verification, not a user decision) — `Project` has no "project type"/category field anywhere; the only close-enough existing concept is `Opportunity.service_category`, reachable only via a reverse `converted_project_id` lookup and only for Projects that originated from a CRM conversion.
- KK — how to source "project type" given it doesn't exist on `Project`. → resolved with the user (recommended option chosen): derive it from `Opportunity.service_category` via the reverse lookup when available, degrade to `item_type`-only context when not (e.g., a Project created outside the CRM flow) — explicitly rejected adding a new `project_type` column to `Project` (would expand scope beyond this use case and touch the existing Project create/edit form) and rejected dropping project-type context entirely (loses useful signal for CRM-originated projects, the common case).
- LL (verification, not a user decision) — only the DesignItem **create** form is wired to the web UI (`operator.design-items.store`); `Api\DesignItemController::update()` has no web route or edit form. This use case's UI surface is therefore create-only by necessity, not by choice — there is nothing to extend on an edit screen because none exists. A consequence follows: the suggestion endpoint must accept the form's in-progress, not-yet-persisted `project_id`/`item_type` selections as request parameters (tenant-validated the same way `DesignItemController::rules()` already validates `project_id`), unlike Use Case 1's endpoint, which read everything from an already-persisted `Lead` row addressed by path ID.
- Permission and model choice were not re-litigated — reuses `ai.suggest` (Phase 7) and Claude Haiku 4.5, both already justified in §12 and unchanged by this use case's different data shape.

## 14. Phase 9 brainstorm (2026-07-12) — decisions applied

Before writing Phase 9's implementation plan, checked `WorkTemplateStep`/`WorkTemplateField`/`Document`/`WorkInstance`/`WorkInstanceStepAttachment` directly (this use case had been flagged as schema-blocked since Phase 7's own scoping pass, EE above — this session resolved that blocker):

- MM (verification, not a user decision) — reconfirmed EE's original finding directly against the current schema: no "required document types per step" concept exists anywhere in `WorkTemplateStep`/`WorkTemplateField`. Additionally found `Document.document_type` is validated inconsistently across three different upload paths (`Api\DocumentController` enforces a 6-value enum; `Api\SimpleDocumentController` and `Web\DocumentController` accept unconstrained free text) — a second, previously-undocumented gap this use case's design has to work around, not fix.
- NN (verification, not a user decision) — discovered `work_instance_step_attachments` (model `WorkInstanceStepAttachment`), an already-existing table that DOES link a file directly to a specific `WorkInstanceStep`, entirely separate from `Document` and unused by any web UI. Surfaced to the user as relevant context before the matching-granularity decision (OO below) — the user's chosen design does not use this table, but it's documented here so it isn't mistaken for new/undiscovered infrastructure in a future session.
- OO — where to declare "required document types," and how precisely to match uploaded Documents against them. → resolved with the user across three sequential decisions: (1) declare requirements per `WorkTemplateStep`, not per whole `WorkTemplate` — closer to the spec's "checklist theo bước" framing; (2) match at the Project level (any Document with a matching `document_type` anywhere in the project counts, regardless of step) rather than requiring per-step Document linkage — avoids depending on `WorkInstanceStepAttachment` or extending `Document`'s polymorphic `linked_entity_type` to include steps, both of which would have expanded scope significantly; (3) match only against the existing strict 6-value `document_type` enum, not the free-text values accepted by the other two upload paths — documents with non-enum `document_type` values simply don't count as fulfilling any requirement, rather than attempting fuzzy/free-text matching.
- PP — whether this use case should call the Anthropic API at all. → resolved with the user: no. The comparison is a deterministic set-difference with no natural-language reasoning involved; an LLM call would add cost, latency, and a data-minimization surface for something `array_diff()` already does correctly. This is the one Phase 7/8/9 AI use case that ships as pure PHP with zero `AiAssistService` involvement — its "AI có kiểm soát" grouping in the roadmap is about product framing (controlled, human-reviewed, non-auto-applied output), not about literally invoking a model.
