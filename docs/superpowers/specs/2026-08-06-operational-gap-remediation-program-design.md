# Operational Gap Remediation Program — Design

**Status:** DESIGN ONLY. No implementation authorized by this document. Each work item below requires its own Gate 1 (Business Request) and Gate 2 (Business Design) owner approval before any code is written, per `docs/owner-governance/OWNER_OPERATING_MODEL.md`.

**Program approval reference:** Owner approved this remediation program and its wave order in a prior instruction (2026-08-05/06 conversation), and separately approved OWN-2026-001 (Owner Control Layer repository foundation, merged to `main` at `8cc137f9`). This document is the program-level design the owner asked for next, produced under that approval — it does not itself constitute Gate 1/Gate 2 approval for any individual gap.

**Verified against:** `main` @ `8cc137f98a7ebbfb6510e3a1a7ab46dca2f69ebc` (post OWN-2026-001 merge).

---

## 1. Wave 1 — Verification Results (current, re-checked against `main`)

Ten previously `UNVERIFIED` register entries were re-checked by direct code reading against the current `main` tree. Full evidence (file:line for both the original defect location and the fix location) is in the investigation transcript; this table is the classification summary.

| Gap | One-line | Classification | Evidence anchor |
|---|---|---|---|
| GAP-001 | Cross-tenant IDOR, Web Task/Document controllers | **RESOLVED_VERIFIED** | `app/Http/Controllers/Web/TaskController.php:145,168`, `DocumentController.php:64,140,181` — all tenant-scoped. Two unscoped debug methods exist but are unrouted dead code. |
| GAP-003 | `zena_submittals` vs `submittals` provenance | **RESOLVED_VERIFIED** | `zena_submittals` explicitly deprecated (`2025_09_16_083456_deprecate_zena_design_construction_tables.php:75-86`); canonical `submittals` table + single `Submittal` model; zero live references to the old table outside migrations. |
| GAP-004 | Viewer can create task, missing `rbac:task.create` | **RESOLVED_VERIFIED** | `routes/web.php:396` carries the middleware; regression test exists in `tests/Feature/RBACRolesPermissionsTest.php`. |
| GAP-005 | SSRF via webhook URL, incl. DNS rebinding | **RESOLVED_VERIFIED** | Two-layer guard: create-time (`WebhookPageController.php:38-42`) + delivery-time DNS-rebinding-safe re-resolution (`DeliverWebhook.php:97-114`). |
| GAP-006 | Duplicate daily-log race (TOCTOU) → 500 | **RESOLVED_VERIFIED** | DB unique constraint (`2026_07_08_100000_create_site_diaries_table.php:41`) + `QueryException` 23000 caught → 422 (`SiteDiaryController.php:198-207`). |
| GAP-007 | Webhook retry double-counts failures | **RESOLVED_VERIFIED** | `attempts() < tries` guard on both retry paths (`DeliverWebhook.php:73-75,85-87`). |
| GAP-008 | LIKE-filter injection, activity feed search | **RESOLVED_VERIFIED** | `%`/`_` escaped before LIKE clause (`ActivityFeedPageController.php:26-27`). |
| GAP-009 | API token creation, no rate limit | **RESOLVED_VERIFIED** | `throttle:6,1` on the route (`routes/web.php:952`). |
| GAP-010 | Cluster: CSV formula injection, secret-via-flash, export OOM, Gantt timezone | **PARTIALLY_RESOLVED** — see split below | Mixed: newer path fixed, legacy path still open, one sub-item inconclusive. |
| GAP-014 | NCR/CAPA dashboard, notifications, reverse-link | **PARTIALLY_RESOLVED** — split below | 1 of 3 sub-findings resolved, 2 open. |

### GAP-010 split (per Wave 1 rules — real defects found, do not clean the register by editing prod code)

- **GAP-010a (dashboard/export, current canonical path):** RESOLVED_VERIFIED — `ReportPageController.php` escapes CSV formulas (`:151-158`) and streams via `streamDownload()`+`lazy()` (`:122-145`); secret-via-flash uses a dedicated one-shot session key, not general flash.
- **GAP-010b (legacy `ExportController::generateCsv()`, still routed at `routes/api.php:1008-1009`):** OPEN_VERIFIED — no formula escaping, builds the full CSV in memory before `Storage::put()`. Same two bugs the original audit described, on a code path the original audit apparently didn't cover because a newer, fixed path was added alongside it without removing the old one.
- **GAP-010c (Gantt timezone drift):** UNKNOWN_BLOCKED — the only Gantt-shaped view found (`design-project.blade.php:492-650`) renders hardcoded mock data with no real date-parsing path; the originally-audited component could not be located. Needs the owner or original auditor to identify which URL/screen exhibited the drift, or it should be closed as "component no longer exists."

### GAP-014 split (per Wave 1 instruction — dashboard/notifications/reverse-link have different states)

- **GAP-014a (dashboard):** RESOLVED_VERIFIED — `SiteEngineerDashboardController.php:80,101-160` computes real tenant-scoped NCR/CAPA counts.
- **GAP-014b (notification semantics):** OPEN_VERIFIED — `NcrEventListener` exists and is fully implemented, but its events (`NcrCreated`/`NcrAssigned`/`NcrResolved`) are never dispatched anywhere and the listener is unregistered in `EventServiceProvider`. Dead code, not a working feature — confirms the backlog's own admission.
- **GAP-014c (NCR↔task reverse-link storage):** OPEN_VERIFIED — no `task_id`/`capa_task_id` column on `ncrs`, no relation on the `Ncr` model. Linkage is one-way, tag-based only. The "UNKNOWN" in the backlog is now a confirmed absence, not an open question.

### Wave 1 rule compliance

- No production code was modified to produce this table (read-only investigation).
- No regression test was added — none of the 8 `RESOLVED_VERIFIED` findings lack durable coverage that would justify one (GAP-004's fix already has a regression test; the others are structural/config-level fixes verifiable by inspection, and adding a test now would be scope creep against "verification only").
- GAP-003 required no priority elevation — already resolved.
- `OPERATIONAL_GAP_REGISTER.md` itself is **not** updated by this document (design-only scope, per the instruction that this deliverable is the program design, not a register cleanup). Updating the register's 8 resolved rows to `RESOLVED (verified)` and registering GAP-010a/b/c and GAP-014a/b/c as their own rows is proposed as **Work Item WAVE1-REG-UPDATE** below — a verification-only, no-code-change item.

---

## 2. Verified Dependency Graph

```
WAVE1-REG-UPDATE (register bookkeeping, no code)
        |
        v
GAP-032 (generic vs workflow status design) ──Gate2──> GAP-033 (designated approver)
        |
        v (GAP-033 unlocks Today Workspace "Cần tôi xử lý" for documents)
   [Today Workspace personalization backlog — out of this program's scope]

GAP-029 (Submittal resubmit UI) ──┐
GAP-013 (Submittal notifications) ─┤ share actor (submittal reviewer) + workflow, but
                                    │ independent acceptance criteria (per instruction)
GAP-012 (CR-apply notification) ───┤ same notification-fan-out pattern as GAP-013,
                                    │ can reuse GAP-013's decisions but is its own Work ID
GAP-017 (invitation-expired page) ─┤ no dependency on the above — pure dead-link fix
GAP-016 (dead enhanced routes) ────┘ no dependency — pure dead-route removal

GAP-030 (rfi.resolve-escalation permission split) — standalone, blocked on owner
        actor-role matrix approval (explicitly: do not guess roles)

GAP-011 + GAP-027 (debug-route hardening program) — standalone, no dependency
GAP-018 + GAP-020 (orphaned view/component cleanup) — needs GAP-021 inventory work
        done first if `/api/v1/tasks*` compat clients touch any of the same views
        (unconfirmed — verify at GAP-021 design time)
GAP-021 (task API compat unification) — standalone design, own Gate 2
GAP-028 (architecture doc correction) — should run LAST in Wave 4, since it
        documents the *result* of GAP-011/018/020/021, not a precondition for them

GAP-010b (legacy CSV export) — standalone, small, no dependency
GAP-014b (NCR notification wiring) — standalone, but shares "which events actually
        fire notifications" design space with GAP-013/GAP-012 (Wave 3) — sequence
        AFTER Wave 3 so the notification-fan-out pattern is decided once, not twice
GAP-014c (NCR reverse-link storage) — standalone migration-risk item

GAP-015, GAP-019, GAP-026 — held, not sequenced (see §5)
```

**Merge-order constraint carried forward from OWN-2026-001:** every future governed work item's PR targets `main` directly. There is no more stacking needed — GAP-031's squash-merge lesson (retargeting after a squash merge requires a rebase, not just a base change) applies to any future PR that gets stacked on another unmerged PR. Recommendation: **do not stack future PRs** unless unavoidable; if stacking is unavoidable, budget for the rebase-after-squash correction step from the start.

---

## 3. Priority Matrix

| Priority | Items | Why |
|---|---|---|
| P0 — verification bookkeeping | WAVE1-REG-UPDATE | Zero code risk, closes the "UNVERIFIED" credibility gap in the register itself; should land before any Wave 2+ work so the register is trustworthy going forward. |
| P1 — real open security/correctness bugs found by Wave 1 | GAP-010b (legacy CSV export: formula injection + OOM, still routed) | Small, isolated, same class of bug already fixed once elsewhere — low risk, should not wait for Wave 2/3. |
| P2 — business-decision-gated design work | GAP-032 → GAP-033 | Owner decision points are the bottleneck, not engineering effort; start the design conversation early since it blocks Wave 2 entirely until Gate 2 approval. |
| P3 — workflow dead-ends (small, independent, user-visible) | GAP-029, GAP-013, GAP-012, GAP-017, GAP-016 | Each is a contained, low-risk fix; ordered per owner's explicit instruction. |
| P4 — permission/architecture cleanup (higher blast radius) | GAP-030, GAP-011+027, GAP-018+020, GAP-021, GAP-028 | Touches RBAC and route surfaces — higher care needed, sequenced last among "do implement" items. |
| P5 — deferred sub-items surfaced by Wave 1 | GAP-014b, GAP-014c, GAP-010c | Real but lower urgency than P1; GAP-014b/c share design space with Wave 3's notification work, GAP-010c needs owner input just to locate the right component before it can even be scoped. |
| Held — not sequenced | GAP-015, GAP-019, GAP-026 | Business workflow decision, deliberate non-revival, external blocker respectively — see §5. |

---

## 4. Gap → Work-Item Mapping

Every implementation item gets its own Work ID, Gate 1, Gate 2, design, plan, isolated branch/worktree, focused TDD, independent review, Gate 3, PR, and rollback plan — no combined PRs except where explicitly grouped below for sharing the same actor/workflow/data-contract/rollback boundary (per instruction).

| Proposed Work ID | Gaps covered | Grouped? | Type | Migration risk |
|---|---|---|---|---|
| WAVE1-REG-UPDATE | Register rows for GAP-001,003,004,005,006,007,008,009 → RESOLVED; register GAP-010a/b/c, GAP-014a/b/c | N/A — docs only | Verification-only | None |
| GAP-010B (new number, register to assign) | GAP-010b | Solo | Bug fix | None (no schema change) |
| GAP-032 | GAP-032 design only in this wave | Solo | Design + Gate 2, NOT implementation | TBD at design time — likely yes (status field semantics) |
| GAP-033 | GAP-033 design only, after GAP-032 Gate 2 | Solo | Design + Gate 2, NOT implementation | Likely yes (new approver-assignment table/column) |
| GAP-029 | GAP-029 | Solo | Feature (UI wiring, backend/API already exists) | None expected |
| GAP-013 | GAP-013 | Solo (may reuse GAP-029's discovery context per instruction, but separate acceptance criteria/Gate 3) | Feature (notification fan-out) | None expected |
| GAP-012 | GAP-012 | Solo | Feature (notification fan-out) | None expected |
| GAP-017 | GAP-017 | Solo | Bug fix (missing view) | None |
| GAP-016 | GAP-016 | Solo | Cleanup (dead route removal) | None |
| GAP-030 | GAP-030 | Solo | Permission/route change — **requires owner actor-role matrix approval before design proceeds** | None (permission table row addition only) |
| GAP-011-027 | GAP-011 + GAP-027 | Grouped — same actor (any authenticated dev/ops user hitting `/_debug/*`), same rollback boundary (route removal/hardening) | Hardening program (inventory + env restriction + invariant test + drift detection + removal criteria) | None |
| GAP-018-020 | GAP-018 + GAP-020 | Grouped — same rollback boundary (archive/delete orphaned view files), but ONLY after zero-reference confirmation per gap | Cleanup | None |
| GAP-021 | GAP-021 | Solo | API contract unification/deprecation — **requires client/permission inventory before design** | Possibly (if compat endpoint removed, is a breaking API change) |
| GAP-028 | GAP-028 | Solo | Documentation correction — run last, after GAP-011/018/020/021 land | None |
| GAP-014B (new number) | GAP-014b | Solo, sequenced after Wave 3's notification-pattern decisions | Feature (wire existing dead listener) | None |
| GAP-014C (new number) | GAP-014c | Solo | Feature + schema | Yes — new FK/column on `ncrs` or a link table |

---

## 5. Held / Not Sequenced

- **GAP-015** (no owner-confirmed UI for WorkTemplate→WorkInstance engine): requires a product workflow decision and its own Gate 1 before any design work starts. Not sequenced into a wave.
- **GAP-019** (`universal-frame.blade.php` shell with no owning route): remains a debug/demo shell. Not revived unless the owner explicitly asks.
- **GAP-026** (`production.yml` still using an unscoped `SLACK_WEBHOOK_URL` secret): externally blocked until the real Slack destination channel for that secret is confirmed outside this repo. Not sequenced.

---

## 6. Owner Decision Points (non-technical, plain-language, required before proceeding)

1. **GAP-032/033 sequencing gate:** Owner must approve Gate 1 (business request) for "should Document status separate generic lifecycle from approval workflow, and should documents have a named responsible approver" before any GAP-032/033 design work begins. This is the single highest-leverage decision in the whole program — it blocks Document's participation in Today Workspace's personalized "Action Required" list.
2. **GAP-030 actor-role matrix:** Owner must approve a plain-language table of "which roles may resolve an RFI escalation" before the `rfi.resolve-escalation` permission is created. Engineering will not guess this list.
3. **GAP-010c component identification:** Owner (or whoever ran the original 2026-07-09 audit) needs to identify which screen showed the Gantt timezone drift, or confirm it's safe to close as "component no longer exists."
4. **GAP-015 workflow decision:** a separate, standalone owner Gate 1 on whether/how to expose the WorkTemplate→WorkInstance engine as a real screen.

## 7. Engineering-Only Decisions (owner is NOT asked)

- Exact Work ID numbering assignments (register-administrative).
- Whether GAP-011+GAP-027 and GAP-018+GAP-020 are grouped PRs or split (grouping rationale is engineering judgment about shared rollback boundary, not a business call).
- Test mechanics, branch/worktree layout, review process for each item.
- The internal design of the debug-route hardening program's drift-detection mechanism.
- Whether GAP-021's deprecation uses a redirect shim or a hard cutover — that's an engineering compatibility-strategy call, UNLESS it changes the breaking-change risk assessed at GAP-021 design time, in which case it becomes an owner decision at that point.

## 8. Stop Conditions

- Any wave stops if a Gate 3 packet for one of its items comes back `blocked_technical` — do not proceed to the next item in the same wave assuming it'll resolve itself.
- GAP-032 design must not proceed to GAP-033 design until GAP-032's own Gate 2 is approved (explicit instruction: "Do not implement before owner Gate 2 approval").
- GAP-030 design must not begin until the owner has approved the actor-role matrix (§6.2).
- GAP-021 design must not begin implementation planning until its client/permission usage inventory is complete and reviewed.
- If any Wave 1 `RESOLVED_VERIFIED` finding is later found to be wrong (e.g., a hidden second code path), stop and re-verify before trusting downstream sequencing that assumed it was closed.

## 9. Rollback Boundaries

- Every item above except GAP-032/033/014c/021(if breaking) is a pure code change with no schema migration — rollback is a standard git revert.
- GAP-032/033 and GAP-014c are expected to need migrations; their Gate 2 designs must each include an explicit migration-rollback plan before implementation starts (per this program's own Wave 2 design requirements).
- GAP-021, if it removes/changes the compat API surface, needs a rollback plan that accounts for any external client already depending on `/api/v1/tasks*` — to be assessed at design time, not assumed away here.

## 10. Adoption Metrics (how we'll know the program worked)

- Register accuracy: 0 remaining `UNVERIFIED` rows for the 10 gaps re-checked here, after WAVE1-REG-UPDATE lands.
- Each shipped work item: Gate 3 packet exists, `owner_decision.value == approved`, evidence digest bound, matching this project's OWN-2026-001 pattern now that the tooling exists to enforce it.
- Zero combined/giant PRs — every merged PR traces to exactly one (or one explicitly-grouped) Work ID.
- GAP-032/033: Document becomes eligible for Today Workspace personalized "Action Required" only after GAP-033 ships — that eligibility flip is the concrete, observable success signal for the whole Wave 2.

---

## Self-Review

- **Placeholder scan:** no TBD/TODO left unresolved as an action item — every "TBD" in §4 is explicitly flagged as "to be determined at [specific future] design time," not a gap in this document.
- **Internal consistency:** GAP-014b/c sequencing (§2, §4) agrees with §3's P5 placement. GAP-021's migration-risk flag in §4 agrees with §9's conditional rollback note.
- **Scope check:** this document does not implement anything; every code-touching recommendation is explicitly deferred to its own future Gate 1/Gate 2/Gate 3 cycle.
- **Ambiguity check:** "grouped" work items (§4) each state the specific shared boundary (actor/rollback) justifying the grouping, per the instruction that grouping requires justification, not convenience.
