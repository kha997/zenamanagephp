# 2026-03-19 System Review and Roadmap Repair

Repo: `zenamanage-golden`
Scope: evidence-first repo review, canonical-vs-stale map, and roadmap repair
Constraint: no business/runtime code changes in this round

## 1) Executive System Verdict

ZenaManage is currently a Laravel-based multi-tenant project-delivery platform with real operational modules for projects, tasks, documents, contracts, change requests, RFIs, submittals, inspections, notifications, and a newer work-template/work-instance backbone. The repo is already pointed at AEC-style enterprise operations, not a generic dashboard product, but maturity is uneven: `/api/zena/*` plus the work-template/export stack are the clearest modern business surface, while `/api/v1/*`, `src/*`, and several web/debug/demo layers still carry compatibility and historical drift. The biggest issue is ownership ambiguity, not lack of code: multiple route families, mixed `app/*` and `src/*` implementations, and stale root docs keep signaling competing product narratives. The correct direction is to preserve the multi-tenant delivery backbone, keep inspection/quality as a supported vertical, demote historical dashboard/demo narratives, and drive all next rounds from one repaired roadmap anchored on runtime truth.

## 2) Product Purpose SSOT

Canonical purpose statement moved to [`docs/product-purpose-ssot.md`](../product-purpose-ssot.md).

Short form:

- ZenaManage is a multi-tenant enterprise operations platform for architecture, construction, interiors, and inspection-adjacent delivery teams.
- Core capabilities are tenant isolation, RBAC, projects, tasks, documents, contracts, workflow templates, work instances, approvals, notifications, change requests, RFIs, submittals, inspections, dashboards, and evidence-bearing exports.
- Near-term product focus is operational delivery control, not a generic dashboard showcase, not a pure SPA rewrite, and not a full horizontal ERP outside project-delivery scope.

## 3) Current Business Capability Map

| Capability / module | Purpose | Primary evidence | Current status | Business criticality | Decision | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Tenant + organization boundary | Tenant-scoped security and data ownership | `app/Http/Middleware/TenantIsolationMiddleware.php`; `app/Models/Tenant.php`; route stacks on `/api/zena/*`, `/api/v1/*`, `/app/*` | canonical | high | keep | Core invariant, not optional |
| RBAC / permission gating | Permission and role enforcement across APIs and web | `app/Http/Middleware/RoleBasedAccessControlMiddleware.php`; `routes/api_zena.php`; `routes/api.php` | canonical | high | keep | Implementation mixed, invariant still clear |
| Auth surfaces | Session login, `/api/v1/auth/*`, `/api/zena/auth/*`, debug auth helpers | `routes/web.php`; `routes/api.php`; `routes/api_zena.php`; route-list outputs | partial | high | repair | Multiple active families; `/api/zena/auth/*` is cleaner for modern business flow |
| Project management | Tenant-scoped project CRUD, details, team linkage | `app/Http/Controllers/Api/ProjectController.php`; `app/Models/Project.php`; `/api/zena/projects`; `/app/projects` | canonical | high | keep | Controller still depends on legacy adapters |
| Task management | Delivery execution, dependencies, assignees, watchers | `app/Http/Controllers/Api/TaskController.php`; `app/Models/Task.php`; `/api/zena/tasks`; `/app/tasks` | canonical | high | keep | Broadly active, mixed contract styles |
| Document center | Upload, metadata, versions, project-linked evidence | `app/Http/Controllers/Api/SimpleDocumentController.php`; `Src/DocumentManagement/*`; `/api/zena/documents` | canonical | high | keep | Runtime is real but domain ownership is split between `app` and `src` |
| Work templates | Reusable workflow templates with draft/publish lifecycle | `app/Http/Controllers/Api/WorkTemplateController.php`; `app/Models/WorkTemplate.php`; `docs/work-templates-ssot.md`; `tests/Feature/Api/WorkTemplateMvpApiTest.php` | canonical | high | keep | Strongest modern subsystem |
| Work instances + approvals | Applied workflow execution on projects/components | `app/Http/Controllers/Api/WorkInstanceController.php`; `app/Models/WorkInstance.php`; `database/migrations/2026_02_27_120000_create_work_templates_mvp_tables.php` | canonical | high | keep | Backbone for vertical workflows |
| Deliverable templates + export | Deliverable versioning and HTML/PDF/ZIP evidence export | `app/Http/Controllers/Api/DeliverableTemplateController.php`; `app/Http/Controllers/Api/WorkInstanceController.php`; export tests | canonical | high | keep | Strong runtime asset for downstream delivery |
| Change requests | Govern scope/cost/schedule/design/quality changes | `app/Http/Controllers/Api/ChangeRequestController.php`; `database/migrations/2025_09_17_162350_create_change_requests_table.php`; `/api/zena/change-requests` | partial | high | repair | Real module, but duplicated with `src/ChangeRequest` and `/api/v1/change-requests` |
| RFIs | Question/answer/escalation workflow on projects | `app/Http/Controllers/Api/RfiController.php`; `app/Models/Rfi.php`; `database/migrations/2025_09_20_133629_create_rfis_table.php`; `/api/zena/rfis` | canonical | high | keep | Good vertical fit |
| Submittals | Review/approval package flow for construction/interiors | `app/Http/Controllers/Api/SubmittalController.php`; `app/Models/Submittal.php`; `/api/zena/submittals`; `tests/Feature/Api/SubmittalApiTest.php` | partial | high | repair | Runtime exists, but table provenance and legacy alias history are messy |
| QC plans + inspections | Inspection scheduling, execution, findings | `app/Http/Controllers/Api/InspectionController.php`; `app/Models/QcInspection.php`; QC migrations; `/api/zena/inspections` | partial | medium-high | keep | Inspection readiness is real, not full QMS yet |
| NCR / quality follow-up | Quality issue lifecycle from inspections | `app/Models/Ncr.php`; `tests/Feature/InspectionNcrWorkflowTest.php` | partial | medium | repair | Evidence exists, but route ownership and module packaging are less clear |
| Contracts + payments | Project contract record and payment tracking | `app/Http/Controllers/Api/ContractController.php`; `app/Http/Controllers/Api/ContractPaymentController.php`; `/api/v1/projects/{project}/contracts*`; policies/tests | canonical | medium-high | keep | Lives in `/api/v1`, not yet converged into modern namespace |
| Notification inbox | User notifications, unread counts, mark-read flows | `app/Http/Controllers/Api/NotificationController.php`; `app/Models/Notification.php`; `/api/zena/notifications` | canonical | medium | keep | Real runtime |
| Notification rules / event pipeline | Channel/rule-based event notifications | `app/Models/NotificationRule.php`; `Src/Notification/*`; backlog `S0.3` | partial | medium | repair | Good direction, still mixed ownership |
| Role-based dashboards | PM, designer, site engineer operational overviews | `app/Http/Controllers/Api/PmDashboardController.php`; `routes/api_zena.php` role groups | partial | medium | keep | Useful only as projections of core modules, not as primary product identity |
| `/app/*` tenant HTML shell | Authenticated tenant navigation for projects/tasks/documents/templates/settings/team | route-list `/app`; `app/Http/Controllers/Web/AppController.php`; `resources/views/layouts/app-layout.blade.php` | canonical | high | keep | Canonical HTML shell despite quality issues |
| Admin web surface | System admin pages and controls | route-list `/admin`; `routes/web.php`; admin views | partial | medium | repair | Production-owned but messy and multi-versioned |
| Universal-frame surface | Former broad page shell and smart tools experiment | `resources/views/layouts/universal-frame.blade.php`; 2026-03-19 audits | stale | low | freeze | No canonical runtime owner |
| Accessibility / performance / final-integration web-mounted APIs | Admin-like APIs still mounted under `web` stack | route-list `/api/v1/accessibility`, `/api/v1/performance`, `/api/v1/final-integration`; `routes/web.php` | drift | low-medium | freeze | Current runtime exists but not core business product |
| Standalone `frontend/` React app | Separate SPA prototype / alternative frontend | `frontend/index.html`; `frontend/src/main.tsx`; no Laravel mount evidence | partial | low | freeze | Buildable, but not canonical runtime shell |
| `/_debug/*` pages and auth helpers | Dev/test tooling | route-list `/_debug`; `docs/audits/2026-03-19-debug-route-inventory.md` | debug | low | keep fenced | Must stay non-canonical |
| Root historical reports / completion docs | Prior summaries and planning artifacts | root `*_REPORT.md`, `ROADMAP_*`, `PROJECT_OVERVIEW.md`, `SYSTEM_DOCUMENTATION.md` | historical-only | low | archive by policy | Too stale to steer product direction |

## 4) Canonical vs Non-Canonical Map

### Canonical runtime surfaces

- Tenant HTML shell: `GET /app/*` routes from `php artisan route:list --path='app'`; views and shell in `resources/views/layouts/app-layout.blade.php`
- Modern business API surface: `GET/POST/... /api/zena/*` routes from `php artisan route:list --path='api/zena'`; definitions in `routes/api_zena.php`
- Platform compatibility API surface still mounted: `GET/POST/... /api/v1/*` routes from `php artisan route:list --path='api/v1'`; definitions in `routes/api.php`
- Invariants / governance docs: `docs/agent-ssot-rules.md`, `docs/engineering/testing-matrix.md`, `docs/roadmap/backlog.yaml`

### Compatibility / legacy surfaces

- `src/*` module route families mounted from `routes/api.php`:
  - `src/ChangeRequest/routes/api.php`
  - `src/RBAC/routes/api.php`
  - `src/DocumentManagement/routes/api.php`
  - `src/Compensation/routes/api.php`
  - `src/CoreProject/routes/api.php`
- Legacy model aliases:
  - `app/Models/ZenaSubmittal.php`
  - `app/Models/ZenaRfi.php`
  - `app/Models/ZenaChangeRequest.php`
- `routes/legacy/api_v1.php` is historical reference only per `routes/README.md`

### Debug / demo surfaces

- Active debug routes under `/_debug/*` from route-list and `docs/audits/2026-03-19-debug-route-inventory.md`
- `resources/views/test*.blade.php`
- `resources/views/debug/simple-dashboard.blade.php`
- Session/demo auth helpers in debug route group

### Historical-only docs / artifacts

- Root overview/completion docs with stale claims, for example:
  - `PROJECT_OVERVIEW.md`
  - `SYSTEM_DOCUMENTATION.md`
  - `ROADMAP_7_PHASES_DETAILED.md`
  - `ROADMAP_MANAGEMENT_SYSTEM.md`
  - `ROADMAP_ACTION_PLAN.md`
  - `ROADMAP_EXECUTION_CHECKLIST.md`
  - `ROADMAP_REVIEW_REPORT.md`
- Historical page-tree docs already marked non-canonical:
  - `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`
  - `ZENAMANAGE_PAGE_TREE_DIAGRAM_OLD.md`
  - `ZENAMANAGE_PAGE_TREE_DIAGRAM_RESTRUCTURED.md`

### Orphan / stale surfaces

- `resources/views/layouts/universal-frame.blade.php` and dependent support components, per `docs/audits/2026-03-19-universal-frame-ownership-audit.md`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/tenants/index.blade.php`
- Smart-tools Blade stack:
  - `resources/views/components/smart-search.blade.php`
  - `resources/views/components/smart-filters.blade.php`
  - `resources/views/components/analysis-drawer.blade.php`
  - `resources/views/components/export-component.blade.php`
  - `resources/views/test-smart-tools.blade.php`
- Standalone `frontend/` app as unmounted alternative surface

## 5) SSOT / Roadmap / Architecture Doc Assessment

| File | Current role | Trust level | Action | Why |
| --- | --- | --- | --- | --- |
| `docs/agent-ssot-rules.md` | repo-wide evidence law | high | keep | Matches current verification practice and route/schema rules |
| `docs/engineering/testing-matrix.md` | verify-order SSOT | high | keep | Aligned with composer scripts |
| `docs/roadmap/backlog.yaml` | governance backlog SSOT | medium-high | keep | Story inventory is useful, but broad completion state still needs runtime proof per story |
| `docs/roadmap/canonical-roadmap.md` | execution roadmap SSOT | medium -> high after this round | revise | Needed product-level repair and stronger boundaries |
| `docs/product-purpose-ssot.md` | temporary product-purpose SSOT | high | keep | Added to stop dashboard/demo drift |
| `routes/README.md` | route ownership rules | medium | revise later | Correct about `routes/api.php` composition, incomplete about active `api/zena` product role |
| `docs/architecture/routing-architecture.md` | routing law | high | keep | Good composition guardrail even if repo does not fully conform semantically |
| `docs/work-templates-ssot.md` | module-specific SSOT for WT/WI MVP | high | keep | Matches current work-template backbone |
| `docs/repo-architecture-review.md` | broad repo review | medium | keep as context | Strong evidence base, but still mixes recommendation and prior assumptions |
| `docs/audits/2026-03-19-debug-route-inventory.md` | debug runtime inventory | high | keep | Current-date route evidence |
| `docs/audits/2026-03-19-blade-ownership-reachability-audit.md` | Blade stale/canonical map | high | keep | Good stale-surface evidence |
| `docs/audits/2026-03-19-universal-frame-ownership-audit.md` | universal-frame ownership decision | high | keep | Clear non-canonical decision |
| `PROJECT_OVERVIEW.md` | old project summary | low | demote | Claims Vue 3, microservices, completed status; conflicts with current repo runtime |
| `SYSTEM_DOCUMENTATION.md` | old system overview | low | demote | Outdated route/middleware claims and incomplete auth/debug inventory |
| Root `ROADMAP_*` files | historical planning set | low | demote | Competing roadmap narratives with stale completion assumptions |
| `README.md` | public repo overview | low-medium | revise later | Still claims universal-frame and `/api/universal-frame/*` as active primary product narrative |

## 6) What Is Currently Right

- Tenant isolation and RBAC are correctly treated as hard invariants in docs and runtime middleware.
- `/api/zena/*` is the clearest modern business namespace for workflow, RFI, submittal, inspection, notification, and role dashboard operations.
- The WorkTemplate -> WorkInstance -> Deliverable subsystem is the strongest product backbone and already has schema, routes, tests, seeders, and roadmap evidence.
- The repo already supports the intended AEC/interiors/inspection direction through real modules rather than only aspirational docs.
- `/app/*` remains the canonical authenticated HTML shell; it should be preserved until an explicit replacement is mounted.
- Recent audits from 2026-03-19 correctly demote debug/demo/public artifacts instead of treating them as production-owned.
- Contracts/payments, document handling, and role dashboards are product-relevant and should be integrated into the backbone rather than discarded.
- Inspection/quality is not fake. QC plan, inspection, NCR, inspection tests, and inspection baseline template evidence show real readiness, even if the vertical is incomplete.

## 7) What Is Outdated / Wrong / No Longer Fit

- `PROJECT_OVERVIEW.md` is stale. It claims the project is completed in January 2025, uses Vue 3, and implements microservices; that does not match current Laravel/Blade/React split runtime.
- `SYSTEM_DOCUMENTATION.md` is stale. It describes a narrower route map and older debug/admin ownership than current route-list evidence.
- Root `ROADMAP_*` files are no longer fit as execution guides because `docs/roadmap/canonical-roadmap.md` already superseded them and current runtime does not match their completion framing.
- `README.md` still centers universal-frame and `/api/universal-frame/*` examples, which conflicts with the current `/api/v1/universal-frame/*` runtime and the non-canonical universal-frame audits.
- `resources/views/layouts/universal-frame.blade.php` is stale as a production shell because no canonical route owner renders it.
- The smart-tools Blade stack is stale because it points to wrong `/api/universal-frame/*` paths and orphan endpoints while having no mounted page owner.
- `/api/v1/*` ownership is split and noisy: it contains modern contracts, legacy `src/*` modules, and web-mounted API-style families under the `web` stack.
- `frontend/` is not a current runtime owner. It is a standalone alternative frontend without Laravel mount evidence.
- Submittal persistence history is still messy because migrations clearly create `zena_submittals`, while runtime aliases point to `submittals`; full physical table provenance remains unresolved.

## 8) Strategic Decisions

1. Canonical tenant UI shell: keep `/app/*` Blade shell as canonical runtime HTML surface for now.
2. Canonical modern business API direction: treat `/api/zena/*` as the primary forward business contract surface.
3. `/api/v1/*` role: keep as mounted compatibility and platform surface, not the narrative center of the product.
4. `src/*` modules: treat as compatibility/domain-ownership debt to be converged, not as a greenfield expansion pattern.
5. Universal-frame decision: freeze as non-canonical debug/demo shell; do not revive without an explicit ownership round.
6. Role dashboards: keep only as projections over canonical operational modules; do not let dashboards define product scope.
7. Inspection/quality positioning: keep as a supported product pillar and roadmap track, but as QMS-lite attached to the template/document/change backbone, not as a separate suite yet.
8. Debug/demo policy: keep `/_debug/*` gated and documented, but exclude it from roadmap success criteria except where it affects safety or contributor confusion.
9. Canonical product-purpose doc: use `docs/product-purpose-ssot.md`.
10. Canonical roadmap doc: use `docs/roadmap/canonical-roadmap.md` after this round’s repair.

## 9) Repaired Roadmap

Canonical roadmap moved to [`docs/roadmap/canonical-roadmap.md`](../roadmap/canonical-roadmap.md).

Summary:

- North Star: multi-tenant AEC/interiors operations platform built around delivery workflows, controlled records, and evidence-bearing execution.
- Product pillars:
  - platform trust: tenant isolation, RBAC, contracts, notifications, test guardrails
  - delivery backbone: project/task/document/work-template/work-instance
  - controlled change: RFI, submittal, inspection, change request, approvals
  - evidence and reporting: export, audit, dashboard projections
- Priority order:
  - Phase 1 platform ownership and contract convergence
  - Phase 2 delivery backbone completion
  - Phase 3 controlled field/change workflows
  - Phase 4 role dashboards, reporting, and bounded expansion
  - Phase 5 rationalization and retirement of stale surfaces

## 10) Proposed Doc Changes

- Revised `docs/roadmap/canonical-roadmap.md`
  - reason: make one usable execution roadmap aligned to runtime truth and current product direction
- Added `docs/product-purpose-ssot.md`
  - reason: stop drift toward dashboard/demo narratives and establish product boundary
- Added `docs/audits/2026-03-19-system-review-roadmap-repair.md`
  - reason: preserve this round’s system review, capability map, canonical-vs-stale map, and decisions

Docs that should be revised later but were not changed in this round:

- `README.md`
- `routes/README.md`
- `docs/api/API_DOCUMENTATION.md`
- selected root historical docs should be batch-demoted or moved under an archive policy

## 11) Exact Files Touched

- `docs/product-purpose-ssot.md`
- `docs/audits/2026-03-19-system-review-roadmap-repair.md`
- `docs/roadmap/canonical-roadmap.md`

## 12) Verification

Commands and evidence used in this round included:

- `git status --short`
- `rg --files routes app/Http/Controllers app/Models resources/views resources/js frontend docs Src`
- `php artisan route:list --except-vendor -v --path='api/zena'`
- `php artisan route:list --except-vendor -v --path='api/v1'`
- `php artisan route:list --except-vendor -v --path='_debug'`
- `php artisan route:list --except-vendor -v --path='app'`
- direct reads of:
  - `app/Providers/RouteServiceProvider.php`
  - `routes/web.php`
  - `routes/api.php`
  - `routes/api_zena.php`
  - `docs/agent-ssot-rules.md`
  - `docs/engineering/testing-matrix.md`
  - `docs/work-templates-ssot.md`
  - multiple 2026-03-19 audit docs
  - selected controllers, models, migrations, tests, and seeders

Tests run in this round:

- none

Not verified in this round:

- end-to-end runtime behavior in browser
- full schema-to-runtime proof for every legacy alias table
- story-by-story completion in `docs/roadmap/backlog.yaml` beyond explicitly evidenced entries

## 13) Unknown

- Exact physical table provenance for `submittals` versus `zena_submittals`
- Whether the standalone `frontend/` app has any deployment/runtime owner outside this repo’s Laravel mounts
- Exact bounded-context ownership split intended between `app/*` and `src/*`
- Which admin view variants are still operationally needed versus just stale alternatives
- Full production consumer surface, if any, for `/api/v1/universal-frame/*`

## 14) Recommended Next Engineering Round

Run one focused runtime-ownership convergence round for active business APIs:

1. map duplicate module ownership across `/api/zena/*`, `/api/v1/*`, `app/*`, and `src/*` for projects, documents, change requests, notifications, and contracts
2. choose canonical controller/model ownership per module without breaking mounted routes
3. add explicit compatibility markers/tests for kept legacy aliases
4. end with one module ownership SSOT document and removal/freeze list
