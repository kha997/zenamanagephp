# ZENA Ops Roadmap — Design Spec

Date: 2026-07-09
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

**API (new `Api/DesignItemController`):** `index`, `store`, `show`, `update`, `updateStatus` (validates allowed transitions; `revision_requested`/`sent_to_client` require `client_feedback_notes` or `due_to_client_at` context as appropriate — exact validation rules are an implementation-plan decision, not fixed here).

**Web (new `Web/DesignItemPageController` + views under `resources/views/design-items/`, mounted under `/operator/design-items/*` following the same convention as `CrmPageController`):** kanban board grouping `DesignItem` by `review_status`, same pattern as `CrmPageController::BOARD_GROUPS`. Nav entry under a new or existing "Dự án"/"Kinh doanh" section — decide placement when implementing, based on how it reads next to existing nav groups.

**Permissions:** new `design-item.view`, `design-item.manage` (mirror the `crm.*` pattern from the CRM slice: seeder entries in `ZenaPermissionsSeeder` + `ZenaRbacSeeder`, policy class `DesignItemPolicy`).

**Acceptance criteria:**
- Create a `DesignItem` linked to an existing `WorkInstanceStep` and one with `work_instance_step_id = null` (ad-hoc) — both work identically through the kanban.
- Move an item through all `review_status` values via the API and via the kanban UI.
- Attach a file, upload a second version, see both versions listed.
- RBAC: user without `design-item.manage` cannot change status or upload; user without `design-item.view` gets 403 on index.
- Feature test (`tests/Feature/Api/DesignItemApiTest.php`) + operator UI test (`tests/Feature/Zena/OperatorDesignItemUiTest.php`), mirroring `CrmApiTest`/`OperatorCrmUiTest` conventions (tenant isolation test, permission-denied test, full-flow test).

**Open questions for the implementing agent:** exact nav placement; whether `item_type` should be a fixed enum validated server-side or free text with suggested values (recommend fixed enum, matching `Opportunity::VALID_SERVICE_CATEGORIES` precedent).

---

## Phase 2 — Tích hợp ZenaManage ↔ zena-boq-core

**Goal:** let a ZenaManage `Opportunity` reference a `zena-boq-core` project/quote and display its current totals/status without ZenaManage owning any pricing logic.

**Cross-repo work (in `zena-boq-core`, tracked as a separate task there, not in this repo):**
- Add a minimal authenticated read API:
  - `GET /api/external/projects/:code` → `{ id, code, name, client, status, rateBookVersion }`
  - `GET /api/external/quotes/latest?projectCode=:code` (or `/api/external/quotes/:id`) → `{ id, projectId, revision, status, calibration, subtotal, vatAmount, total, issuedAt }`
  - Auth: `Authorization: Bearer <shared-secret>`, secret stored as env var, matching the one ZenaManage holds.
  - Optional (defer if it slows Phase 2 down): webhook POST to a ZenaManage URL on `quote.issued` / `quote.accepted`, signed with the same shared secret.

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

**Out of scope:** any UI for creating/editing quotes (that stays in `zena-boq-core`); user-level SSO between the two systems.

---

## Phase 3 — Hiển thị báo giá trong CRM

**Goal:** surface the synced quote data from Phase 2 directly on the Opportunity page.

**Work:** add a "Báo giá" card to `resources/views/crm/opportunity-show.blade.php`: subtotal/VAT/total, status badge (`ISSUED`/`ACCEPTED`/...), a visually distinct `UNCALIBRATED`/`CALIBRATED` badge (must not be able to be mistaken for each other — this is a hard requirement carried over from `zena-boq-core`'s own governance rules), and an external link (`target="_blank"`) to open the real quote on `zena-boq.vercel.app`. No embedding/iframe — link out only, since the source of truth's UI lives there.

**Acceptance criteria:** an Opportunity with a synced quote shows the card; one without a linked `zena-boq-core` project shows a "Chưa liên kết báo giá" empty state with a way to trigger the linking flow from Phase 2.

**Dependency:** requires Phase 2's cached snapshot fields to exist and be populated.

---

## Phase 4 — Tự động hoá hợp đồng

**Goal:** when an Opportunity is WON and its linked quote is `ACCEPTED`, let staff generate a draft `Contract` pre-filled from the quote total, with a generated PDF, in one action.

**Work:**
- New action on the Opportunity page: "Tạo hợp đồng" (visible only when `pipeline_stage = won` and `external_quote_snapshot.status = ACCEPTED`).
- Creates a `Contract` record: `total_value = external_quote_snapshot.total`, `client_name` from the linked `Account`, `status = draft`.
- Generates a PDF from a contract template — reuse the existing `DeliverableTemplate`/`DeliverableTemplateVersion` + `DeliverablePdfExportService` pattern (template with merge fields) rather than building new PDF infrastructure.

**Acceptance criteria:** one action from a WON Opportunity with an ACCEPTED quote produces a `Contract` row and a PDF whose total matches the quote total exactly (no manual re-entry of the number).

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

**This phase needs its own brainstorming session before implementation** — the client auth mechanism (magic-link tied to `Account.email` vs. staff-provisioned login) is a security-sensitive decision this spec deliberately does not lock in. Do not start coding Phase 6 without that follow-up design conversation.

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

**Technical approach:** new `App\Services\AiAssistService` wrapping the Anthropic Messages API (see the `claude-api` skill reference for current model IDs/params), gated behind a new `ai.suggest` permission.

**Dependency:** all prior phases — AI needs stable CRM/quote/design data to have anything meaningful to work with.

---

## 4. Spec-lite scope note

Each phase above is deliberately specced at "enough to hand to another agent" depth (goal, data model sketch, API/route shape, dependencies, acceptance criteria) — not full TDD-level implementation detail. Per the standard process, **each phase should get its own short brainstorming pass (if anything here turns out ambiguous once someone is actually implementing it) and its own `writing-plans` implementation plan** before code starts on it. This spec is the shared reference so that plan stays consistent with the others.
