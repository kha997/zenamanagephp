# ZenaManage Canonical Roadmap

Last updated: 2026-03-19
Role: execution sequencing SSOT
Companion docs:

- `docs/product-purpose-ssot.md` = product-purpose SSOT
- `docs/roadmap/backlog.yaml` = backlog and governance SSOT
- `docs/agent-ssot-rules.md` = evidence/verification law
- `docs/engineering/testing-matrix.md` = verify-order SSOT

## North Star

Build ZenaManage into a multi-tenant enterprise operations platform for architecture, construction, interiors, and inspection-adjacent teams, centered on controlled delivery workflows, evidence-bearing records, and tenant-safe execution.

This repo is not to be steered as a generic dashboard showcase, a universal-frame experiment, or a pure frontend rewrite.

## Current Runtime Truth

The strongest current product backbone is:

1. tenant isolation + RBAC invariants
2. `/app/*` authenticated tenant HTML shell
3. `/api/zena/*` modern business APIs
4. WorkTemplate -> WorkInstance -> Deliverable workflow
5. project/task/document/change/RFI/submittal/inspection/notification modules around that backbone

The largest current product risk is ownership drift:

- `/api/v1/*` remains heavily mounted and business-relevant, but mixes compatibility and legacy modules
- `src/*` and `app/*` both own parts of the business domain
- several root docs still advertise stale product identities
- debug/demo and historical surfaces still create false signals

## Product Pillars

### Pillar A: Platform Trust

- tenant isolation
- RBAC and auth contract clarity
- route ownership clarity
- deterministic verification and SSOT lint discipline
- contract/payment and notification basics that support real operations

### Pillar B: Delivery Backbone

- projects
- tasks
- documents
- work templates
- work instances
- approvals
- deliverables and exports

### Pillar C: Controlled Change and Field Work

- change requests
- RFIs
- submittals
- inspections
- NCR / quality follow-up

### Pillar D: Evidence, Reporting, and Role Projections

- audit trail
- notification rules
- export/report surfaces
- PM/designer/site-engineer dashboards as projections over canonical modules

## Architecture Priorities

1. Preserve tenant isolation and RBAC as hard invariants.
2. Prefer route-list, controller, model, migration, and test evidence over old reports.
3. Treat `/api/zena/*` as the forward business contract direction.
4. Keep `/api/v1/*` mounted, but reduce new ownership drift into it unless explicitly compatibility-related.
5. Preserve `/app/*` as canonical HTML runtime shell until a real replacement is mounted.
6. Freeze debug/demo/universal-frame surfaces unless a round explicitly targets their retirement.

## Domain / Module Priorities

### Priority P0

- tenant + auth + RBAC contract clarity
- project/task/document core flows
- WorkTemplate / WorkInstance / Deliverable backbone
- change requests
- contracts/payments where they affect project governance

### Priority P1

- RFIs
- submittals
- inspections
- notification rules and event -> audit -> notification path
- role dashboards only where they summarize live P0/P1 modules

### Priority P2

- procurement/material expansion
- deeper QMS-lite capabilities
- broader analytics/reporting
- alternate frontend strategy

## Cleanup / Rationalization Priorities

1. Demote historical root docs from planning authority.
2. Stop universal-frame and smart-tools Blade from re-entering roadmap scope.
3. Reduce split ownership between `app/*` and `src/*` for active modules.
4. Clarify which `/api/v1/*` families are compatibility-only versus still product-owned.
5. Retire or freeze stale admin/demo/test artifacts only after ownership proof is documented.

## Phase Order

## Phase 1: Runtime Ownership Convergence

Goal:

- Remove ambiguity about which routes/controllers/models are canonical for active business modules.

Focus:

- `/api/zena/*` vs `/api/v1/*`
- `app/*` vs `src/*`
- `/app/*` vs stale alternative UI surfaces

Backlog alignment:

- `EPIC-0/S0.1`
- `EPIC-0/S0.2`

Exit criteria:

- one module-ownership map exists for active business modules
- new work stops introducing fresh split ownership
- compatibility aliases are explicitly marked as such
- route/middleware evidence is attached for each changed module

## Phase 2: Backbone Completion

Goal:

- Finish the product backbone already visible in runtime.

Focus:

- WorkTemplate data model completion
- apply/generator parity for project/component scope
- document metadata/version/search maturity
- deliverable/export reliability

Backlog alignment:

- `EPIC-1`
- `EPIC-2`

Exit criteria:

- one tenant-safe flow works end-to-end:
  - template publish -> apply -> work instance execution -> document linkage -> deliverable export
- story claims are backed by tests/routes/schema evidence
- WT/WI/DT remains the canonical process backbone

## Phase 3: Controlled Change and Field Execution

Goal:

- Make change and field workflows first-class and tied to the backbone.

Focus:

- change requests
- RFIs
- submittals
- inspections
- NCR / quality follow-up

Backlog alignment:

- `EPIC-3`
- `EPIC-5`
- relevant parts of `EPIC-4`

Exit criteria:

- change, question, submittal, and inspection records connect cleanly to project/document/work flows
- audit and notification evidence exists for at least one end-to-end path per module family
- tenant leakage and permission gaps are explicitly tested on touched routes

## Phase 4: Role Projections, Reporting, and Bounded Expansion

Goal:

- Improve decision surfaces only after operational modules are trustworthy.

Focus:

- PM / designer / site-engineer dashboards
- reporting and export contracts
- procurement/material depth that clearly supports interior/construction delivery

Backlog alignment:

- `EPIC-4`
- `EPIC-6`

Exit criteria:

- dashboards consume canonical module data instead of mock/demo logic
- reporting/export paths are tied to operational records
- new expansion work does not create a second product backbone

## Phase 5: Retirement and Surface Reduction

Goal:

- remove or quarantine stale ownership signals once replacements are proven.

Focus:

- historical docs
- stale admin variants
- universal-frame cluster
- demo/debug/public artifacts outside explicit debug policy

Exit criteria:

- contributors can identify canonical runtime surfaces without reading historical docs
- stale surfaces are either archived, labeled, or removed
- roadmap, README, and route docs point to one consistent product direction

## Explicit Not-Now / Out of Scope

- Full standalone SPA migration as the primary delivery goal
- Microservices decomposition
- New dashboard/demo shells without explicit ownership
- Full ERP breadth outside project-delivery needs
- Full independent QMS platform separate from delivery backbone
- Broad archive/delete sweeps before route/controller ownership is proven

## Risks and Dependencies

### Risks

- compatibility breakage while rationalizing `/api/v1/*`
- regressions from `app/*` and `src/*` ownership overlap
- false progress if role dashboards or debug/demo surfaces are treated as product completion
- schema ambiguity in legacy alias tables such as submittals

### Dependencies

- route-list proof for every route/middleware claim
- migration proof for schema claims
- targeted tests for touched module families
- backlog updates only when story-level completion is actually verified

## Relation to `docs/roadmap/backlog.yaml`

- `backlog.yaml` remains the backlog/governance SSOT.
- This roadmap defines execution order, architectural priorities, and phase gates.
- Do not duplicate stories here.
- Use existing `EPIC-*` and `S*.*` IDs whenever scope matches.
- If a new work item is needed, add it to the relevant epic instead of creating a competing planning doc.

## Historical Docs Policy

Treat these as historical/non-canonical planning context only:

- `ROADMAP_7_PHASES_DETAILED.md`
- `ROADMAP_MANAGEMENT_SYSTEM.md`
- `ROADMAP_EXECUTION_CHECKLIST.md`
- `ROADMAP_ACTION_PLAN.md`
- `ROADMAP_REVIEW_REPORT.md`
- root completion/overview/report files claiming finished state

## Unknowns

- Exact story-level completion for all backlog items beyond explicitly evidenced stories
- Exact production owner of the standalone `frontend/` app
- Full physical table provenance for `submittals`
- Final target split between compatibility `/api/v1/*` and primary `/api/zena/*`

## Update Protocol

Each roadmap update must append:

- date
- change summary
- affected backlog IDs
- evidence links or commands

### Change Log

- 2026-03-19:
  - Reframed roadmap around product-purpose/runtime truth instead of historical roadmap sets.
  - Chose `/api/zena/*` as forward business API direction and `/app/*` as current canonical HTML shell.
  - Added explicit product pillars, phase order, not-now boundaries, and retirement priorities.
- 2026-03-12:
  - Established canonical execution roadmap in this file.
  - Reconciled role split: execution sequencing (this file) vs strategic backlog (`docs/roadmap/backlog.yaml`).
  - Marked old 7-phase roadmap set as historical/non-canonical guidance.
