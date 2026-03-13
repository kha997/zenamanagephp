# ZenaManage Canonical Roadmap (Execution + Backlog Reconciliation)

Last updated: 2026-03-12
Scope: execution sequencing and reconciliation only (does not replace business backlog detail).

## Context: Current Verified Baseline
- SSOT policy rules are defined in `docs/agent-ssot-rules.md`.
- Business/module expansion backlog exists in `docs/roadmap/backlog.yaml` with epic/story IDs (`EPIC-*`, `S*.*`) and acceptance criteria.
- Existing 7-phase roadmap set in repository root exists, but overlaps heavily and contains stale status assumptions.
- Near-term browser/testing stabilization intent exists in change proposals:
  - `docs/change-proposals/button-form-submission-audit-rewrite-plan.md`
  - `docs/change-proposals/button-form-submission-phase1-freeze-note.md`
- WorkTemplate v2 has an MVP snapshot documented in `docs/work-templates-ssot.md`.

## SSOT Invariants
Execution must satisfy these non-negotiables:
- Evidence-first reporting or mark `UNKNOWN`.
- Route/middleware claims must be proven via `php artisan route:list`.
- Migration is schema SSOT.
- Do not relax tenant isolation or RBAC.
- Do not change domain logic only to force tests to pass.
- Keep PR scope minimal and auditable.

Reference sources:
- `docs/agent-ssot-rules.md`
- `docs/roadmap/backlog.yaml` governance section

## Existing Planning Sources (Reconciled View)
- Canonical now:
  - `docs/roadmap/canonical-roadmap.md` (this file) = execution sequencing SSOT.
  - `docs/roadmap/backlog.yaml` = strategic/product backlog SSOT.
- Historical/non-canonical but still useful as context:
  - `ROADMAP_7_PHASES_DETAILED.md`
  - `ROADMAP_MANAGEMENT_SYSTEM.md`
  - `ROADMAP_EXECUTION_CHECKLIST.md`
  - `ROADMAP_ACTION_PLAN.md`
  - `ROADMAP_REVIEW_REPORT.md`
  - `roadmap-progress/*` generated snapshots
- Targeted near-term change planning:
  - `docs/change-proposals/button-form-submission-audit-rewrite-plan.md`
  - `docs/change-proposals/button-form-submission-phase1-freeze-note.md`

## Priority Order
1. Stabilization-first execution (tests/browser/workflow safety + CI/guardrails).
2. Complete platform backbone dependencies (`EPIC-0`, then `EPIC-1..EPIC-3`).
3. Expand business modules (`EPIC-4..EPIC-6`) only after readiness gates pass.

## Near-Term Execution Phases (Round-Based)
## Phase R1: Stabilize Test/Browser/Workflow Surfaces
Goal:
- Replace frozen mixed legacy browser suite with route-anchored suites and deterministic assertions.
- Remove blind spots that produce false confidence.

Primary references:
- `docs/change-proposals/button-form-submission-audit-rewrite-plan.md`
- `docs/change-proposals/button-form-submission-phase1-freeze-note.md`
- `docs/testing/button-test-plan.md`

Backlog mapping:
- `EPIC-0/S0.2` (CI reliability)
- `EPIC-0/S0.3` (event/audit/notification verifiable path)

Done criteria:
- Replacement suites are in place for targeted `/app/*` surfaces.
- CI test job is deterministic enough to gate PR checks.
- Evidence included in PR (commands + outputs + CI links).

## Phase R2: Guardrails + Module Skeleton Compliance
Goal:
- Enforce module structure, route mounting law, and middleware stack consistency.

Backlog mapping:
- `EPIC-0/S0.1`
- `EPIC-0/S0.2`

Done criteria:
- `composer ssot:lint` has no new violations beyond baseline.
- Route inventory and middleware evidence are attached for changed modules.

## Phase R3: Backbone Product Flows (Template -> Document -> Change)
Goal:
- Build/verify end-to-end backbone before expansion modules.

Backlog mapping:
- Template engine: `EPIC-1` (`S1.1`..`S1.4`)
- Document center: `EPIC-2` (`S2.1`..`S2.4`)
- Change order: `EPIC-3` (`S3.1`..`S3.4`)

Done criteria:
- One evidence-backed flow works end-to-end:
  - template apply -> work artifacts -> document workflow -> change approval/audit.
- Tenant isolation and RBAC checks are proven for all touched endpoints.

## Phase R4: Expansion Modules and Service-Ready Boundaries
Goal:
- Add procurement/material, quality/QMS-lite, and analytics in controlled increments.

Backlog mapping:
- `EPIC-4`, `EPIC-5`, `EPIC-6`

Done criteria:
- Each merged story references backlog ID and evidence.
- No cross-tenant leakage in search/filter/reporting paths.
- Event/outbox records remain replayable and auditable.

## How This Roadmap Relates to `backlog.yaml`
- `docs/roadmap/backlog.yaml` keeps detailed strategic backlog and acceptance criteria.
- This roadmap defines execution order and readiness gates for near-term rounds.
- No duplicate story creation: execution tasks must reference existing `S*.*` IDs whenever scope matches.
- If work is outside current IDs, add as explicit new story with unique ID in the relevant epic (do not create wording-only duplicates).

## Reconciliation Notes
- Reconciled conflict class 1: Multiple 7-phase files compete as execution guidance.
  - Resolution: treat as historical context; this file is canonical execution sequence.
- Reconciled conflict class 2: Legacy docs mark some security/policy work as missing while files currently exist.
  - Resolution: do not trust old completion percentages as SSOT; use evidence-based verification per change.
- Reconciled conflict class 3: Backlog file previously contained shell script wrappers.
  - Resolution: normalized `docs/roadmap/backlog.yaml` into clean YAML without changing epic/story meaning.

## UNKNOWN (Must Be Proven Before Claiming Done)
- Exact story-level completion state for each `S*.*` item in `backlog.yaml` is `UNKNOWN` until verified by code + tests + route/schema evidence.
- Full gap matrix between browser stabilization plan and existing Dusk suites is `UNKNOWN` until current suites are re-run and audited.
- Readiness of all `EPIC-1` acceptance criteria versus MVP implementation is `UNKNOWN` until verified against migrations, routes, requests, and tests.

## Do-Not-Do
- Do not overwrite `backlog.yaml` role as strategic backlog.
- Do not copy/paste full backlog content into new roadmap docs.
- Do not create additional competing roadmap files without explicit reason.
- Do not mark stories done without reproducible evidence.
- Do not modify backup files (`*.bak.*`).

## Update Protocol (Changelog Style)
- Every planning update must add a short note here:
  - Date
  - What changed
  - Which backlog IDs are affected
  - Evidence links (PR/CI/report)

### Change Log
- 2026-03-12:
  - Established canonical execution roadmap in this file.
  - Reconciled role split: execution sequencing (this file) vs strategic backlog (`docs/roadmap/backlog.yaml`).
  - Marked old 7-phase roadmap set as historical/non-canonical guidance.
