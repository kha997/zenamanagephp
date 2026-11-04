# GAP-110 — Project OPPM Phase 2 (management overlay) and Phase 3 (snapshots and decisions): discovery evidence

Date: 2026-11-05. Base: `main` at `bdf1b5fb27e3487ce25bd0fe61d9c63ee8b986eb` (after GAP-109).

Sources:
- issue #248 and its three Owner comments (umbrella One-Page Management, Contract & Finance boundary, Service-Line
  taxonomy);
- SSOT OWN-2026-009 (`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`);
- GAP-104 Phase 1 evidence and Gate 2 (V-1, W-A, T-1, R-1);
- the code on `main`.

Method: static reading; no production data was used.

## 1. What the Owner asked (issue #248)

**Phase 2 — management overlay.** Only after the data design is approved:
- outcome (kết quả đầu ra);
- weight (trọng số);
- next action;
- management note;
- display order;
- update rights and audit.

**Phase 3 — snapshot and decisions.** Only if the survey shows the need and no other module already covers it:
- period report close;
- immutable or append-only snapshot;
- this period vs previous period;
- decisions that need Owner / PM;
- PDF export / project portfolio report.

**Product acceptance criteria that bind these phases:**
- manual data has RBAC, validation and audit;
- a snapshot never changes silently when live data changes;
- no parallel project / task data;
- no new progress, delay or attention formula (SSOT §5.2: consume `ProjectHealthReadService`).

**Owner comments that bind scope:**
- company-wide portfolios (Control Tower, Contract / Finance Control) are separate Work IDs;
- OPPM stays one-project.

## 2. What Phase 1 delivered (GAP-104, merged `9e758cb8`)

- Page `/app/projects/{project}/oppm` (`rbac:project.view` plus the tenant check), with a print layout (`@media print`) and
  no export.
- `App\Services\ProjectOppm\ProjectOppmReadService::forProject()`:
  - header;
  - Service Lines;
  - KPIs from `ProjectHealthReadService`;
  - workstreams = **root components** (W-A), at most 12, ordered by start date, each with its own progress / cost and
    blocked / overdue counts;
  - timeline;
  - blocked / overdue tasks and blocked design items (at most 10 rows each);
  - a finance summary without Treasury alerts (T-1).
- Projects without root components show "Chưa có nhóm công việc" with task counts.
- Components are created only through the API (no web screen).

## 3. What exists that Phase 2 / 3 could reuse

| Need | Existing | Fit |
|---|---|---|
| Workstream identity for an overlay | `components.id` (root components, W-A) | stable id. Projects without components have no row to attach an overlay to |
| Decisions | `ChangeRequest` (+ approvals, comments) | formal scope / cost / time change with approval flow, not a general "decision needed" list |
| Decisions | `Rfi`, `Submittal` | technical questions / submittals, project-scoped with due dates, not management decisions |
| Decisions | `Approval`, `DocumentApprovalEvent` | document / work approvals, not management decisions |
| Snapshot storage | none for OPPM; `baselines` are schedule baselines (dates only) | no reusable period-report table |
| PDF | `DeliverablePdfExportService` (HTML → PDF through Node / headless Chromium, with an "unavailable" fallback) | could render the OPPM print view; depends on Node being present at runtime |
| Audit | `audit_logs` (`AuditLog`) | used by every recent GAP |
| Permissions | `project.view`, `project.update` (SSOT §11: reuse existing names) | overlay edit could use `project.update`; snapshot close might need a stricter holder |

No table stores outcome, weight, next action, management notes or management decisions today.

## 4. Open points for Gate 2

1. **Split:**
   - one GAP / PR for both phases; or
   - Phase 2 now (GAP-110), Phase 3 later as its own Work ID (GAP-111).
2. **Overlay anchor:**
   - per root component only; or
   - also a project-level block (overall next actions / management note) so projects without components still get value.
3. **Overlay fields:**
   - outcome, weight, next action (text + owner + due date), note, display order;
   - whether weight changes the progress figure. SSOT §5.2 forbids a new progress formula unless approved; the safe
     default is that weight is shown, not used.
4. **Who edits:** `project.update` holders, or the project manager only; audit with old / new values.
5. **Decisions:**
   - a new "management decision" list (title, decision maker, due date, status, outcome); or
   - reuse ChangeRequest / RFI and only show them.
6. **Snapshots:**
   - who closes a period, and which period (week / month / manual);
   - the snapshot holds the full rendered OPPM data (JSON, append-only) so later data changes never alter it;
   - comparison with the previous snapshot (progress, schedule, attention, finance deltas).
7. **PDF:** reuse `DeliverablePdfExportService` with the print view, or keep browser print only.
8. **Portfolio report:** out of scope (Owner comment: Control Tower is separate) unless the Owner wants a minimal list.

## 5. Constraints

- No new progress / delay / attention formulas (SSOT §5.2).
- No copy of project or task data except inside an explicitly approved immutable snapshot.
- Tenant / project isolation: components, milestones and baselines have no `tenant_id` and must be read through the
  tenant-checked project.
- Query budget on the OPPM page must not grow with overlay or snapshot count (Phase 1 query-invariance test).
- MySQL parity.

## 6. Out of scope

- Control Tower / portfolio pages;
- Contract / Finance Control;
- component or milestone authoring UI (separate Work IDs);
- AI forecasting;
- deployment.
