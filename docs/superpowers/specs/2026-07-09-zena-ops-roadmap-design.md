# ZENA Ops Roadmap — Design Spec

Date: 2026-07-09 (revised after adversarial review pass — see §5)
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
- Auth between systems: a static bearer API key (service-to-service secret), stored as env var on both sides. No user-level SSO in this phase.
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
Phase 7: AI có kiểm soát                      -> depends on all of the above
```

Phases 0 and 1 have no dependencies and can start immediately / in parallel. Phase 2 is the pivot point for the commercial chain (3→4→5). Phase 6 and 7 are terminal — do not start them before their dependencies are demonstrably working (real data flowing, not stubs).

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

All other transitions are invalid and must be rejected by `updateStatus` with a 422. `revision_requested` requires non-empty `client_feedback_notes`. `sent_to_client` requires `due_to_client_at` to already be set (set it earlier in `draft`/`internal_review`, not as part of the transition).

**Audit trail (mandatory):** every `review_status` change must write an `EventRecord` (`aggregate_type = 'design_item'`), following the exact pattern already established for `Lead`/`Opportunity` in the CRM slice (`crm.lead.captured`, `crm.opportunity.stage_changed`, etc. — use `design_item.status_changed` with `from`/`to` payload). This is not optional: client revision history is exactly the kind of record that gets referenced in disputes, and the CRM slice already proved this pattern works and is tested.

**API (new `Api/DesignItemController`):** `index`, `store`, `show`, `update`, `updateStatus` (enforces the transition graph above; returns 422 on invalid transitions with a message naming the invalid `from`/`to` pair).

**Web (new `Web/DesignItemPageController` + views under `resources/views/design-items/`, mounted under `/operator/design-items/*` following the same convention as `CrmPageController`):** kanban board grouping `DesignItem` by `review_status`, same pattern as `CrmPageController::BOARD_GROUPS`. Nav entry under a new or existing "Dự án"/"Kinh doanh" section — decide placement when implementing, based on how it reads next to existing nav groups.

**Permissions:** new `design-item.view`, `design-item.manage` (mirror the `crm.*` pattern from the CRM slice: seeder entries in `ZenaPermissionsSeeder` + `ZenaRbacSeeder`, policy class `DesignItemPolicy`).

**Acceptance criteria:**
- Create a `DesignItem` linked to an existing `WorkInstanceStep` and one with `work_instance_step_id = null` (ad-hoc) — both work identically through the kanban; changing one's `review_status` never touches the linked `WorkInstanceStep`'s own status (assert this explicitly in a test — it's the exact bug the authority rule above exists to prevent).
- Walk a `DesignItem` through the full loop at least once: `draft → internal_review → sent_to_client → revision_requested → internal_review → sent_to_client → approved → revision_requested (late change) → internal_review → sent_to_client → approved → final` — proving the loop-back paths work, not just the forward chain.
- Attempt an invalid transition (e.g. `draft → approved` directly) and assert 422.
- Each status change produces an `EventRecord` with correct `from`/`to` payload — assert the full history is queryable in order.
- Attach a file, upload a second version, see both versions listed.
- RBAC: user without `design-item.manage` cannot change status or upload; user without `design-item.view` gets 403 on index.
- Feature test (`tests/Feature/Api/DesignItemApiTest.php`) + operator UI test (`tests/Feature/Zena/OperatorDesignItemUiTest.php`), mirroring `CrmApiTest`/`OperatorCrmUiTest` conventions (tenant isolation test, permission-denied test, full-flow test).

**Open questions for the implementing agent:** exact nav placement; whether `item_type` should be a fixed enum validated server-side or free text with suggested values (recommend fixed enum, matching `Opportunity::VALID_SERVICE_CATEGORIES` precedent).

---

## Phase 2 — Tích hợp ZenaManage ↔ zena-boq-core

**Goal:** let a ZenaManage `Opportunity` reference a `zena-boq-core` project/quote and display its current totals/status without ZenaManage owning any pricing logic.

**Multi-tenancy gate (mandatory, critical):** `zena-boq-core` has no tenant concept — it is Phan's single-company tool. ZenaManage is explicitly multi-tenant (see `docs/product-purpose-ssot.md`). As designed, this integration would let **any** tenant in ZenaManage type in an arbitrary `zena-boq-core` project code and pull back Z.E.N.A's commercial quote data — a cross-tenant data leak the moment a second tenant exists. This integration must be **hard-restricted to one specific, config-defined tenant ID** (the Z.E.N.A tenant): the sync button, the webhook receiver, and the `external_boq_project_code` field must all check `tenant_id === config('zena_boq.integration_tenant_id')` (or equivalent) and refuse (403/no-op) for every other tenant. This is not deferrable to a later hardening pass — it must ship in Phase 2's first version, because there is currently nothing else preventing the leak.

**Cross-repo work (in `zena-boq-core`, tracked as a separate task there, not in this repo):**
- Add a minimal authenticated read API:
  - `GET /api/external/projects/:code` → `{ id, code, name, client, status, rateBookVersion }`
  - `GET /api/external/quotes/latest?projectCode=:code` (or `/api/external/quotes/:id`) → `{ id, projectId, revision, status, calibration, subtotal, vatAmount, total, issuedAt }`
  - Auth: `Authorization: Bearer <shared-secret>`, secret stored as env var, matching the one ZenaManage holds.
  - Optional (defer if it slows Phase 2 down): webhook POST to a ZenaManage URL on `quote.issued` / `quote.accepted`, signed with the same shared secret.

**Coordination note — do not let ZenaManage-side work block on this:** the `zena-boq-core` read API above does not exist yet as of this spec, and there is no tracked mechanism in this repo for knowing when it lands (it's a separate repo, separate backlog). ZenaManage-side implementation must be built and fully tested against a **mocked HTTP client** (fake responses matching the shapes above) from day one — do not wait for the real endpoint to exist to make progress. Full end-to-end verification (a real sync against a live `zena-boq-core` project) is a separate, later checkpoint once both sides are ready, not a blocker for merging the ZenaManage-side PR.

**ZenaManage-side work:**
- Migration: add nullable columns to `opportunities`: `external_boq_project_code`, `external_quote_id`, `external_quote_snapshot` (json: `subtotal`, `vat_amount`, `total`, `status`, `calibration`, `issued_at`), `external_quote_synced_at`.
- New service `App\Services\ZenaBoqIntegrationService`: wraps the outbound HTTP call to `zena-boq-core`'s read API (base URL + bearer secret from config/env), with a clear timeout and error-envelope-consistent failure handling (don't let a `zena-boq-core` outage break the Opportunity page — degrade to "last synced at X" state).
- New inbound webhook route: `POST /api/zena/integrations/zena-boq/webhook`, HMAC or shared-secret validated, updates the matching Opportunity's cached snapshot fields by `external_boq_project_code`.
- Manual "Đồng bộ báo giá" button on the Opportunity page (`Api/OpportunityController@syncExternalQuote` or similar), calling `ZenaBoqIntegrationService` synchronously — this is the fallback that must work even if the webhook is never wired up on the `zena-boq-core` side, so Phase 2 does not hard-depend on cross-repo webhook work landing first.

**Acceptance criteria:**
- An Opportunity can be linked to an `external_boq_project_code`.
- Clicking "Đồng bộ báo giá" populates `external_quote_snapshot` with real data from a test `zena-boq-core` project (or a mocked HTTP response in tests).
- The webhook endpoint updates the same fields when called directly (test with a signed payload), independent of the manual sync path.
- `zena-boq-core` being unreachable degrades gracefully (existing cached data stays visible with a "last synced" timestamp; no 500s bubble to the user).
- **Tenant-gate test (required):** an Opportunity belonging to a non-Z.E.N.A tenant cannot trigger a sync and cannot be updated by the webhook, even with a syntactically valid `external_boq_project_code` and a correctly signed payload — assert this explicitly, it is the fix for the critical finding above.
- Webhook idempotency note (informational, not extra work): `external_quote_snapshot` is a read-model cache keyed by the latest data, not a ledger — a duplicated/retried webhook call simply overwrites with the same values, which is already safe. No additional de-duplication machinery is needed; don't build any.

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

**Acceptance criteria:** an Opportunity with a synced quote shows the card; one without a linked `zena-boq-core` project shows a "Chưa liên kết báo giá" empty state with a way to trigger the linking flow from Phase 2.

**Forward-compat note:** build the card's view data as a small, source-agnostic shape (`subtotal`, `vat_amount`, `total`, `status`, `calibration|null`, `synced_at|null`, `external_url|null`) assembled by the controller from `external_quote_snapshot`, rather than reading `external_quote_snapshot` fields directly in the Blade template. This is what lets the deferred internal-quotation fallback (see Phase 2's "Non-Z.E.N.A tenants" note) reuse the same card later without a rewrite — even though only the `zena-boq-core` source exists today.

**Dependency:** requires Phase 2's cached snapshot fields to exist and be populated.

---

## Phase 4 — Tự động hoá hợp đồng

**Goal:** when an Opportunity is WON and its linked quote is `ACCEPTED`, let staff generate a draft `Contract` pre-filled from the quote total, with a generated PDF, in one action.

**Work:**
- New action on the Opportunity page: "Tạo hợp đồng" (visible only when `pipeline_stage = won` and `external_quote_snapshot.status = ACCEPTED`).
- Creates a `Contract` record: `total_value = external_quote_snapshot.total`, `client_name` from the linked `Account`, `status = draft`. **Pin the exact source revision**: store `external_quote_snapshot.id` (the `zena-boq-core` quote id) and its `revision` number on the `Contract` record at creation time (new columns, e.g. `source_quote_id`, `source_quote_revision`) — this is what "no manual re-entry of the number" actually depends on being traceable to.
- **Quote-drift guard:** once a `Contract` has been generated, if a later "Đồng bộ báo giá" (Phase 2) pulls a *different* `external_quote_id`/`revision` or a changed `total` than what's pinned on an existing draft `Contract`, show a visible warning on both the Opportunity and the Contract page ("Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp") rather than silently letting the two drift apart unnoticed.
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

**Acceptance criteria:** dashboard renders all five cards with real tenant data (not placeholder/demo values), respecting existing tenant-isolation and RBAC patterns on the dashboard endpoints.

**Dependency:** needs Phase 1 (design throughput as an optional KPI candidate), Phase 2-4 (quote/contract data) to have real numbers to show — building this against empty CRM data would produce a dashboard nobody trusts.

---

## Phase 6 — Cổng khách hàng

**Goal:** let a client see their project's progress, delivered documents, quote/contract summary, and outstanding balance, without staff RBAC/roles.

**This phase needs its own brainstorming session before implementation** — two security-sensitive decisions this spec deliberately does not lock in:
1. The client auth mechanism (magic-link tied to `Account.email` vs. staff-provisioned login).
2. **Visibility scope per `Account`**: one `Account` can have multiple `Opportunity`/`Project` records, and potentially multiple client-side stakeholders (chủ đầu tư vs. người giám sát công trình) who may need different views of the same Account's data. The follow-up brainstorm must explicitly define what a logged-in client identity can see — all Projects under their Account, or scoped further — before any portal query code is written; this is not a detail to leave implicit.

Do not start coding Phase 6 without that follow-up design conversation covering both points.

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
