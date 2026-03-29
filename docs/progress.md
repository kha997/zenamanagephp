# Progress

## 1. Project Header

- Project: Build zena webapp / zenamanage-golden
- Last updated: 2026-03-29
- Branch: main
- Goal: deploy the real webapp and do not change domain/app logic just to pass test/CI

## 2. Executive Snapshot

The repo is in a controlled evidence-locking phase around the canonical `/api/zena/*` business surface. Recent work locked backlog-backed completion for `S1.2` and `S2.4`, proved `S2.1` on the canonical document owner path by wiring `/api/zena/documents/{id}/versions` plus metadata/versioning/permission tests, shipped a minimal canonical document workflow slice for `S2.3`, implemented the narrow runtime slices for `S3.2` on the canonical change-request owner path without overclaiming broader workflow ownership, proved `S3.4` on the canonical CR timeline plus Document Center-backed attachment-query surface, proved `S2.2` on the same canonical Document Center owner path through attach/detach plus tenant-safe link-query evidence for task, component, and change request targets, proved `S4.1` on the canonical material and vendor master-data owner paths by adding minimal CRUD on `/api/zena/materials` and `/api/zena/vendors` with tenant-safe anti-enumeration plus dedicated RBAC, locked `S4.2` planning to a dedicated canonical BOQ owner family on `/api/zena/boqs` with project as the required anchor and component as an optional same-project line-item link, proved `S5.1` on the canonical inspection owner path by linking generated `WT-BL-INSPECTION` checklist instances from the existing WorkTemplate engine into `/api/zena/inspections`, proved `S5.2` on the same inspection owner path by adding nested `/api/zena/inspections/{inspection}/ncrs` create/list/show/status endpoints plus a minimal task-handoff payload into canonical `/api/zena/tasks`, and completed `S0.1`'s narrowed route-mounting inventory by explicitly composing `src/WorkTemplate/routes/api.php` from `routes/api.php`, disabling provider-based route auto-mount in `Src\WorkTemplate\Providers\WorkTemplateServiceProvider`, and preserving the `/api/v1/work-template*` compatibility surface without duplicate METHOD+URI or double-prefix drift. With the acceptance boundary narrowed to already-proved canonical slices and the active provider offender inventory cleared, `S0.1`, `S1.1`, `S2.1`, `S2.2`, `S3.2`, `S3.4`, `S4.1`, `S5.1`, and `S5.2` are evidence-complete, while `S3.2a` remains the explicit follow-up for broader approver/stakeholder semantics and any later notification expansion.

For `S3.2`, the former planning gap was backlog wording that bundled `approvers and stakeholders` into one acceptance surface. That gap is now resolved by the planning split: narrowed `S3.2` owns only the proved canonical workflow + minimal direct-recipient notification slice, and `S3.2a` owns the still-unknown broader approver/stakeholder semantics.

For `S5.2`, the narrowed execution round is now proved: NCR ownership remains only as a child of the canonical inspection owner path, CAPA execution is handed off into the existing canonical `/api/zena/tasks` surface through a task payload blueprint rather than a new owner family, and NCR↔task reverse-link semantics, escalations, notifications, dashboards, and broader QMS cleanup remain explicitly deferred until runtime evidence exists.

## 3. Operating Rules

- `docs/roadmap/backlog.yaml` is the story-status and planning SSOT.
- `docs/progress.md` is the execution-progress SSOT for round history, current state, and next actions.
- Runtime truth comes from `php artisan route:list`.
- Evidence first: if evidence is missing or weak, write `UNKNOWN`.
- `/api/zena/*` is the canonical forward business surface.
- `/api/v1/*` is compatibility-only, not the forward owner surface.
- Do not change domain/app logic just to pass test or CI.

## 4. Recent Locked Rounds

### Round 23

- Date: 2026-03-29
- Scope: `S5.2` inspection-owned NCR slice + minimal CAPA handoff
- Outcome: locked runtime slice
- Key files:
  - `routes/api_zena.php`
  - `app/Http/Controllers/Api/InspectionController.php`
  - `tests/Feature/InspectionNcrWorkflowTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - head reviewed: `4607e0ea56ce61f5c1ff6721e2d2f47ef67e4c30`
  - runtime truth: `php artisan route:list | grep -E "inspection|ncr|task" || true` now shows canonical nested `/api/zena/inspections/{inspection}/ncrs*` routes alongside the existing canonical `/api/zena/tasks*` family and no standalone `/api/zena/ncrs*` family
  - NCR proof: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/InspectionNcrWorkflowTest.php` -> `OK (3 tests, 43 assertions)`
  - task regression proof: `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Task` -> `OK (135 tests, 843 assertions)`
  - lint: `composer ssot:lint`; `php artisan optimize:clear`
- Deferred:
  - standalone `/api/zena/ncrs/*` owner family
  - `/api/zena/capa*`
  - persistent NCR-to-task reverse-link storage
  - escalation rules
  - notifications
  - dashboards
  - `under_review` as part of the canonical first-slice workflow
  - `S3.2a`
- Notes:
  - Canonical runtime proof is intentionally limited to create, list/show, and `open -> in_progress -> resolved -> closed` on the nested inspection owner path.
  - CAPA proof is intentionally only a handoff into canonical `/api/zena/tasks`; task lifecycle ownership remains on `TaskController`.
  - This round does not claim standalone NCR ownership, `under_review`, or broader NCR↔task reporting semantics.

### Round 22

- Date: 2026-03-29
- Scope: `S5.2` NCR + CAPA owner/contract planning lock
- Outcome: docs-only planning lock
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s5-2-ncr-capa-owner-contract.md`
  - `app/Models/Ncr.php`
  - `database/migrations/2025_09_20_142033_create_ncrs_table.php`
  - `app/Policies/NcrPolicy.php`
  - `app/Models/QcInspection.php`
  - `routes/api_zena.php`
  - `app/Http/Controllers/Api/TaskController.php`
  - `tests/Feature/InspectionNcrWorkflowTest.php`
  - `tests/Feature/Api/InspectionTemplateRuntimeTest.php`
  - `tests/Feature/Api/TaskApiTest.php`
- Evidence:
  - head reviewed: `29810a614e9462b09ceb277a276bd23944830559`
  - runtime truth: `php artisan route:list | grep -E "inspection|ncr|task" || true` shows canonical `/api/zena/inspections*` and `/api/zena/tasks*`, but no canonical `/api/zena/ncr*` route family
  - NCR inventory: `App\\Models\\Ncr`, `database/migrations/2025_09_20_142033_create_ncrs_table.php`, `App\\Policies\\NcrPolicy`, `Database\\Factories\\NcrFactory`, and `tests/Feature/InspectionNcrWorkflowTest.php`
  - inspection linkage: `App\\Models\\QcInspection::ncrs()` plus canonical `/api/zena/inspections` ownership already proved in `tests/Feature/Api/InspectionTemplateRuntimeTest.php`
  - task owner evidence: canonical `/api/zena/tasks` route family in `routes/api_zena.php` and owner/route invariants in `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
  - contract gap: no active `NcrController`, no canonical NCR route tests, and no current canonical persistent NCR↔task link field/table proof
- Deferred:
  - notification semantics
  - escalation rules
  - dashboards
  - broad QMS cleanup
  - broader NCR↔task reverse-link semantics
  - standalone dedicated `/api/zena/ncrs/*` owner family
  - `under_review` semantics as part of the first canonical proof slice
  - `S3.2a`
- Notes:
  - The recommended first owner surface for `S5.2` is nested under inspections because that is the only current NCR linkage grounded by both model evidence and canonical route ownership.
  - CAPA must stay on the existing canonical task owner path; this planning lock intentionally does not create a second CAPA API family.
  - The `ncrs.inspection_id` column is nullable in schema, but the first canonical proof slice should not infer standalone NCR ownership from that alone.

### Round 21

- Date: 2026-03-29
- Scope: `S0.1` narrow WorkTemplate route-mounting slice
- Outcome: locked runtime slice
- Key files:
  - `routes/api.php`
  - `src/WorkTemplate/Providers/WorkTemplateServiceProvider.php`
  - `src/WorkTemplate/routes/api.php`
  - `tests/Unit/WorkTemplateRouteMountingLawTest.php`
  - `tests/Feature/Architecture/WorkTemplateRouteInvariantTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - head reviewed: `e069fb2346c7efc38ba561d4816614cd1c30f752`
  - routing law: `docs/architecture/routing-architecture.md` keeps `routes/api.php` as the sole API composition point and forbids provider route mounting
  - composition change: `routes/api.php` now explicitly requires `src/WorkTemplate/routes/api.php`
  - provider change: `src/WorkTemplate/Providers/WorkTemplateServiceProvider.php` no longer mounts routes and only keeps service bindings plus event-listener registration
  - offender scan: `rg -n 'loadRoutesFrom\\(|Route::middleware\\(\\[.?api.?\\]\\).*prefix\\(.?api.?' src/*/Providers config/app.php` shows only disabled/commented route mounts outside WorkTemplate after this slice
  - runtime truth: `php artisan route:list --path=api/v1/work-template` still shows the existing `/api/v1/work-template*` compatibility surface; the command also substring-matches `/api/v1/work-templates`, so targeted invariants count the WorkTemplate compatibility subset at `30 routes`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Route` -> `OK (70 tests, 2328 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Architecture` -> `OK (21 tests, 367 assertions)`
  - lint: `composer ssot:lint`; `php artisan optimize:clear`
- Deferred:
  - any canonical `/api/zena/*` redesign for WorkTemplate business ownership
  - any repo-wide route cleanup beyond the now-cleared active provider offender inventory
  - `S3.2a`
- Notes:
  - This round proves routing composition law and compatibility-surface stability only; it does not redesign WorkTemplate business behavior.
  - New targeted tests lock the explicit mount and the absence of duplicate METHOD+URI or double-prefix drift for `/api/v1/work-template*`.

### Round 20

- Date: 2026-03-29
- Scope: `S0.1` planning adjustment after the proved Notification route-mounting slice
- Outcome: docs-only keep-open verdict
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/architecture/routing-architecture.md`
  - `routes/api.php`
  - `src/WorkTemplate/Providers/WorkTemplateServiceProvider.php`
  - `src/Notification/Providers/NotificationServiceProvider.php`
  - `tests/Unit/NotificationRouteMountingLawTest.php`
  - `tests/Feature/Architecture/NotificationRouteInvariantTest.php`
- Evidence:
  - head reviewed: `e069fb2346c7efc38ba561d4816614cd1c30f752`
  - routing law: `docs/architecture/routing-architecture.md` forbids ServiceProvider route mounting and requires explicit composition in `routes/api.php`
  - explicit mounts present: `routes/api.php` requires `src/ChangeRequest/routes/api.php`, `src/RBAC/routes/api.php`, `src/DocumentManagement/routes/api.php`, `src/Compensation/routes/api.php`, `src/CoreProject/routes/api.php`, and `src/Notification/routes/api.php`
  - active offender: `src/WorkTemplate/Providers/WorkTemplateServiceProvider.php` still mounts `src/WorkTemplate/routes/api.php` via `Route::middleware(['api'])->prefix('api')->group(...)`
  - runtime truth: `php artisan route:list --path=api/v1/work-template` -> `30 routes`; `php artisan route:list --path=api/v1/work-templates` -> `6 routes`; `php artisan route:list --path=api/v1/notifications` -> `10 routes`; `php artisan route:list --path=api/v1/notification-rules` -> `8 routes`
- Deferred:
  - any implementation work against the remaining `WorkTemplateServiceProvider` offender
  - any repo-wide route cleanup beyond the active provider auto-mount offender inventory
  - any canonical `/api/zena/*` redesign for Notification or WorkTemplate compatibility surfaces
- Notes:
  - Notification remains the only slice explicitly proved by targeted law/invariant tests in this story at the locked snapshot.
  - Notification is not the only active offender worth tracking for `S0.1`; `WorkTemplateServiceProvider` remains an evidence-backed active provider-based route composer.
  - The planning direction is therefore to keep `S0.1` open with a narrowed, evidence-backed offender scope instead of declaring the story effectively complete around Notification alone.

### Round 19

- Date: 2026-03-29
- Scope: `S0.1` narrow Notification route-mounting law slice
- Outcome: locked runtime slice
- Key files:
  - `routes/api.php`
  - `src/Notification/routes/api.php`
  - `src/Notification/Providers/NotificationServiceProvider.php`
  - `tests/Unit/NotificationRouteMountingLawTest.php`
  - `tests/Feature/Architecture/NotificationRouteInvariantTest.php`
- Evidence:
  - head reviewed: `dc40d7d8b7de5a6af1cb43a90c93c02ab3664a6b`
  - routes: `php artisan route:list --path=api/v1/notifications` -> `10 routes`; `php artisan route:list --path=api/v1/notification-rules` -> `8 routes`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Route` -> `OK (67 tests, 2320 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Architecture` -> `OK (20 tests, 364 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Unit/NotificationRouteMountingLawTest.php` -> `OK (2 tests, 4 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/NotificationRouteInvariantTest.php` -> `OK (1 test, 3 assertions)`
  - lint: `composer ssot:lint`; `php artisan optimize:clear`
- Deferred:
  - remaining `S0.1` offenders outside Notification
  - any repo-wide route cleanup beyond active provider auto-mount inventory
  - any canonical `/api/zena/*` ownership redesign for notifications
- Notes:
  - `routes/api.php` now explicitly mounts `src/Notification/routes/api.php`, and `NotificationServiceProvider` no longer calls `loadRoutesFrom(...)`.
  - Notification compatibility route files now define `v1/*` prefixes so the runtime surface remains `/api/v1/*` after composition under the root API prefix.
  - This round proves only the Notification starter slice for `S0.1`; backlog status remains `todo` until broader module-skeleton and route-law coverage is evidenced.

### Round 24

- Date: 2026-03-29
- Scope: `S4.1` material catalog + vendor owner/contract planning lock
- Outcome: docs-only planning lock
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s4-1-material-vendor-owner-contract.md`
  - `routes/api_zena.php`
  - `app/Models/MaterialRequest.php`
  - `app/Http/Controllers/Api/SiteEngineerDashboardController.php`
  - `database/migrations/2025_09_14_110000_create_zena_system_tables.php`
  - `database/seeders/ZenaRbacSeeder.php`
  - `database/seeders/ZenaPermissionsSeeder.php`
  - `app/Services/PresetService.php`
- Evidence:
  - head reviewed: `84dd1397713b8cfff086448ee577be3ca9459bc0`
  - runtime truth: `php artisan route:list | grep -E "material|vendor|submittal|request" || true` shows canonical `/api/zena/site-engineer/material-requests` and `/api/zena/submittals*`, but no canonical `/api/zena/materials*` or `/api/zena/vendors*`
  - material-request residue: `app/Models/MaterialRequest.php` plus `database/migrations/2025_09_14_110000_create_zena_system_tables.php` define request/workflow fields only, not material catalog or vendor master-data ownership
  - dashboard projection: `app/Http/Controllers/Api/SiteEngineerDashboardController.php` reads `MaterialRequest` through a site-engineer projection and shows field/status drift relative to the model/schema, so it is not a safe owner contract source
  - RBAC drift: `database/seeders/ZenaRbacSeeder.php` seeds legacy `material.*`; `database/seeders/ZenaPermissionsSeeder.php` seeds `site-engineer.material-requests`; `app/Services/PresetService.php` expects `material_request.read` and `vendor.read`
  - proposal lock: `docs/change-proposals/2026-03-29-s4-1-material-vendor-owner-contract.md`
- Deferred:
  - `S4.2` BOQ ownership and linkage
  - `S4.3` submittal package ownership/approvals
  - `S4.4` delivery/receipt
  - `S4.5` compensation linkage
  - procurement approvals
  - notifications
- Notes:
  - The safe first owner surface for `S4.1` is new canonical master-data CRUD on `/api/zena/materials` and `/api/zena/vendors`.
  - `MaterialRequest` is explicitly out of owner scope for `S4.1`; current evidence supports it only as request/workflow residue plus dashboard projection.
  - This planning lock intentionally does not infer vendor ownership from menu links, purchase-order `vendor_name` strings, or compatibility/backup routes.

### Round 25

- Date: 2026-03-29
- Scope: `S4.1` canonical material + vendor master-data CRUD slice
- Outcome: locked runtime slice
- Key files:
  - `routes/api_zena.php`
  - `app/Http/Controllers/Api/MaterialController.php`
  - `app/Http/Controllers/Api/VendorController.php`
  - `app/Models/Material.php`
  - `app/Models/Vendor.php`
  - `app/Policies/MaterialPolicy.php`
  - `app/Policies/VendorPolicy.php`
  - `database/migrations/2026_03_29_180000_create_materials_and_vendors_tables.php`
  - `database/seeders/ZenaPermissionsSeeder.php`
  - `tests/Feature/MaterialApiTest.php`
  - `tests/Feature/VendorApiTest.php`
  - `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s4-1-material-vendor-owner-contract.md`
- Evidence:
  - head reviewed: `0412b980ba602bf92fa45723c576d93dc92783d4`
  - runtime truth: `php artisan route:list | grep -E "materials|vendors" || true` now shows canonical `/api/zena/materials*` and `/api/zena/vendors*` families
  - material proof: `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Material` -> `OK (3 tests, 19 assertions)`
  - vendor proof: `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Vendor` -> `OK (3 tests, 19 assertions)`
  - ownership proof: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `OK (2 tests, 68 assertions)`
  - lint: `composer ssot:lint`; `php artisan optimize:clear`
- Deferred:
  - BOQ linkage
  - submittal ownership and approvals
  - delivery/receipt
  - notifications
  - compensation linkage
  - any canonical `MaterialRequest` workflow
- Notes:
  - Canonical runtime proof is intentionally limited to minimal CRUD with basic identifying fields only.
  - Cross-tenant access returns `404`, and permission splits are limited to `material.view|create|update|delete` and `vendor.view|create|update|delete`.
  - `MaterialRequest` remains non-owner residue and is not used as proof for the master-data owner paths.

### Round 26

- Date: 2026-03-29
- Scope: `S4.2` BOQ owner/contract planning lock
- Outcome: docs-only planning lock
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s4-2-boq-owner-contract.md`
  - `routes/api_zena.php`
  - `app/Models/Project.php`
  - `app/Models/Component.php`
  - `app/Models/Material.php`
  - `app/Models/Vendor.php`
  - `app/Models/MaterialRequest.php`
  - `app/Models/Submittal.php`
  - `database/migrations/2025_09_14_110000_create_zena_system_tables.php`
  - `database/migrations/2026_03_29_180000_create_materials_and_vendors_tables.php`
  - `tests/Feature/MaterialApiTest.php`
  - `tests/Feature/VendorApiTest.php`
- Evidence:
  - head reviewed: `a1166f9f64b1546ae228dffa7c1524615848094c`
  - runtime truth: `php artisan route:list | grep -E "boq|project|component|material|vendor" || true` shows canonical `/api/zena/projects*`, `/api/zena/materials*`, and `/api/zena/vendors*`, no canonical `/api/zena/boqs*`, and only `/api/zena/components/{id}/apply-template` on the zena component path
  - BOQ inventory: `rg -n "BOQ|BillOfQuant|quantity|line item|component|material|vendor" app tests database docs src` finds no direct BOQ model/table/controller/test owner contract
  - project/component anchor evidence: `App\Models\Project` and `App\Models\Component` prove project ownership plus same-project component lineage suitable for linkage validation
  - master-data boundary: `App\Models\Material`, `App\Models\Vendor`, `tests/Feature/MaterialApiTest.php`, and `tests/Feature/VendorApiTest.php` prove only catalog CRUD, not BOQ ownership
  - residue boundary: `App\Models\MaterialRequest`, `App\Models\Submittal`, and `zena_purchase_orders.vendor_name` in `database/migrations/2025_09_14_110000_create_zena_system_tables.php` are procurement-adjacent residue only and not safe BOQ owner proof
- Deferred:
  - task linkage on BOQ line items
  - pricing rollups and cost summaries
  - approvals
  - submittal package linkage
  - delivery/receipt
  - compensation
  - notifications
  - `S3.2a`
- Notes:
  - The recommended first owner surface for `S4.2` is a dedicated canonical `/api/zena/boqs` family with nested `/line-items`, because no current module safely owns BOQ CRUD.
  - Project is the required BOQ anchor; component is an optional line-item link constrained to the same project.
  - `MaterialRequest` and `Submittal` are explicitly kept out of BOQ owner scope for this planning lock.

### Round 1

- Date: 2026-03-28
- Scope: `S2.3` canonical document workflow slice on `/api/zena/documents/*`
- Outcome: done
- Key files:
  - `app/Http/Controllers/Api/SimpleDocumentController.php`
  - `routes/api_zena.php`
  - `tests/Feature/Api/DocumentManagementTest.php`
  - `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - `docs/change-proposals/2026-03-27-s2-3-document-workflow-canonical-slice.md`
- Evidence:
  - commit: `bba51d3f042633884c1459903c085c9e0415f79f`
  - routes: `POST /api/zena/documents/{id}/submit`; `POST /api/zena/documents/{id}/decision`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DocumentManagementTest.php` -> `OK (12 tests, 56 assertions)`
- Deferred:
  - full review matrix
  - notifications
- Notes:
  - This round proves a minimal canonical workflow slice, not a complete document review system.

### Round 2

- Date: 2026-03-28
- Scope: `S3.2` change-request workflow state machine unification proposal
- Outcome: proposal-only
- Key files:
  - `docs/change-proposals/2026-03-28-s3-2-change-request-workflow-state-machine-unification.md`
- Evidence:
  - commit: `a16ed7c4b62bfc1a7c125bd505e6c5dd507628a4`
  - routes: `php artisan route:list --path=api/zena/change-requests` shows `submit`, `approve`, `reject`, `apply`
- Deferred:
  - implementation
  - audit alignment
  - notification proof
- Notes:
  - The proposal separates current runtime truth from the target implementation contract.

### Round 3

- Date: 2026-03-28
- Scope: backlog hygiene for evidence-backed completion on `S1.2` and `S2.4`
- Outcome: docs-only
- Key files:
  - `docs/roadmap/backlog.yaml`
- Evidence:
  - commit: `434b67d111006c1603925063971da555de7a349b`
  - backlog status: `S1.2=done`, `S2.4=done`
- Deferred:
  - no new runtime work in this round
- Notes:
  - This round locked existing proof into the planning SSOT and reduced status drift.

### Round 4

- Date: 2026-03-28
- Scope: tighten `S3.2` proposal wording to match runtime truth
- Outcome: locked
- Key files:
  - `docs/change-proposals/2026-03-28-s3-2-change-request-workflow-state-machine-unification.md`
- Evidence:
  - commit: `9600ef0c814b016a1caf92e646967bc77025f9e1`
- Deferred:
  - implementation remains out of scope
- Notes:
  - The wording now explicitly states that `approve/reject` lack strong transition-guard proof and that notifications are only capability-level proven.

### Round 5

- Date: 2026-03-28
- Scope: `S3.2` canonical change-request workflow runtime slice and audit alignment on `/api/zena/change-requests`
- Outcome: locked
- Key files:
  - `app/Http/Controllers/Api/ChangeRequestController.php`
  - `tests/Feature/Api/ChangeRequestApiTest.php`
  - `tests/Feature/ChangeRequestApiTest.php`
  - `docs/progress.md`
- Evidence:
  - commit: `fb45a35ab6ebd3a7177a7d1317a459c7d416e270`
  - canonical owner path: `/api/zena/change-requests`
  - runtime proof: `submit: draft -> submitted`; `approve: only from submitted`; `reject: only from submitted`; `apply: only from approved -> implemented`
  - guard proof: generic update-status bypass is blocked on the canonical path
  - audit proof: canonical audit coverage added for `submit`, `approve`, `reject`, and `apply`
- Deferred:
  - notifications remain deferred
  - `/api/v1/*` compatibility surface remains untouched
- Notes:
  - This round proves a narrow canonical runtime slice, not the full story acceptance as currently written in backlog.

### Round 6

- Date: 2026-03-28
- Scope: `S3.2` canonical change-request notification contract planning lock
- Outcome: docs-only
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-28-s3-2-canonical-change-request-notification-contract.md`
- Evidence:
  - head at start: `cd9c3d3f2fd593cf49aa5816dc1561354a4b44fa`
  - routes: `php artisan route:list | grep change-requests`
  - inventory: `rg -n "ChangeRequest|change request|Notification|notify|event|listener|mail|stakeholder|approver" app src tests docs`
- Deferred:
  - runtime notification proof
  - stakeholder fan-out semantics
  - `/api/v1/*`
- Notes:
  - This round locks a minimal canonical proof contract only: `submit -> one explicit approver recipient fixture`, `approve/reject -> requester`, `apply -> deferred`.
  - Broad stakeholder recipient semantics remain `UNKNOWN` and must not be invented in the next runtime round.

### Round 7

- Date: 2026-03-28
- Scope: `S3.2` minimal canonical in-app notification proof on `/api/zena/change-requests`
- Outcome: locked runtime slice
- Key files:
  - `app/Http/Controllers/Api/ChangeRequestController.php`
  - `tests/Feature/Api/ChangeRequestApiTest.php`
  - `tests/Feature/ChangeRequestApiTest.php`
- Evidence:
  - commit: `a41ee056`
  - routes: `php artisan route:list | grep change-requests`
  - tests: `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `11 passed`; `php artisan test tests/Feature/ChangeRequestApiTest.php` -> `18 passed`; `php artisan test tests/Feature/Zena/ZenaAuditInvariantTest.php --filter=change_request_workflow_mutations_write_audit_logs` -> `1 passed`
  - lint: `composer ssot:lint`
- Deferred:
  - `apply` notification
  - broad stakeholder fan-out semantics
  - `/api/v1/*`
- Notes:
  - Canonical `submit` now writes exactly one direct in-app notification to an explicit approver fixture via `change_requests.assigned_to`.
  - Canonical `approve` and `reject` now write one direct in-app notification to `change_requests.requested_by`.
  - This round intentionally does not prove broad stakeholder semantics and does not change backlog story status.

### Round 8

- Date: 2026-03-29
- Scope: `S3.2` planning split for canonical proved slice vs broader notification semantics
- Outcome: docs-only
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-28-s3-2-change-request-workflow-state-machine-unification.md`
  - `docs/change-proposals/2026-03-28-s3-2-canonical-change-request-notification-contract.md`
- Evidence:
  - routes: `php artisan route:list | grep change-requests`
  - backlog split: `S3.2` narrowed to the proved canonical workflow + minimal direct-recipient notification slice; `S3.2a` added for broader approver/stakeholder semantics
- Deferred:
  - any runtime/code changes
  - any `/api/v1/*` work
  - any stakeholder claim beyond explicit canonical evidence
- Notes:
  - This round is planning hygiene only and intentionally does not mark either story done.
  - `apply` notification remains deferred.

### Round 9

- Date: 2026-03-29
- Scope: `S3.2` done-verdict lock against narrowed acceptance on `/api/zena/change-requests`
- Outcome: docs-only
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - head reviewed: `9bf27efbb2437cd37fa0f7c8cd6f730523bab92a`
  - routes: `php artisan route:list | grep change-requests`
  - tests: `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `11 passed`; `php artisan test tests/Feature/ChangeRequestApiTest.php` -> `18 passed`; `php artisan test tests/Feature/Zena/ZenaAuditInvariantTest.php --filter=change_request_workflow_mutations_write_audit_logs` -> `1 passed`
- Deferred:
  - `S3.2a` broader approver/stakeholder semantics
  - any `apply` notification proof
  - `/api/v1/*`
- Notes:
  - Acceptance for `S3.2` is now fully covered by existing canonical controller, audit, and notification evidence.
  - No runtime/code changes were needed for this verdict round.

### Round 10

- Date: 2026-03-29
- Scope: `S3.4` docs-only planning lock for canonical timeline and attachment contracts
- Outcome: proposal-only
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s3-4-cr-timeline-attachments-contract.md`
- Evidence:
  - head reviewed: `a3a544bc82eb7f868093f86a1fd68ef7afed8315`
  - routes: `php artisan route:list | grep -E "change-requests|attachment|attachments|timeline|documents|files|media" || true`
  - inventory: `rg -n "ChangeRequest|timeline|attachment|attachments|file|media|document|audit|linked_entity_type|linked_entity_id" app src tests docs`
- Deferred:
  - runtime implementation
  - timeline payload/order details beyond audit-backed source and dedicated endpoint shape
  - attachment delete/version semantics
  - `/api/v1/*`
  - broad storage/media cleanup
- Notes:
  - This round locks the planning contract only: timeline source should come from canonical audit-backed workflow history, and CR attachments should stay owned by Document Center through `documents.linked_entity_type = 'cr'` plus `documents.linked_entity_id = {changeRequestId}`.
  - The round intentionally does not claim a runtime endpoint already exists for either surface.

### Round 11

- Date: 2026-03-29
- Scope: `S3.4` acceptance review against canonical runtime and tests
- Outcome: docs-only done verdict
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s3-4-cr-timeline-attachments-contract.md`
- Evidence:
  - head reviewed: `c9f2b89225aeff3e6b6b0a8b13d8ed8f15f8d765`
  - routes: `php artisan route:list | grep -E "change-requests|timeline|documents" || true`
  - tests: `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `12 passed`; `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `13 passed`; `php artisan test tests/Feature/Zena/ZenaAuditInvariantTest.php` -> `4 passed`
- Deferred:
  - timeline payload enrichment beyond audit-backed workflow history
  - attachment delete/upload/version semantics beyond current Document Center ownership
  - any `/api/v1/*`
- Notes:
  - Canonical `/api/zena/change-requests/{id}/timeline` now exists and is tested as an audit-backed workflow-history surface.
  - Canonical `/api/zena/documents` now proves CR-link discovery via `linked_entity_type=cr` and `linked_entity_id={changeRequestId}`.
  - No proof in this verdict depends on `change_requests.attachments`.

### Round 12

- Date: 2026-03-29
- Scope: `S2.1` canonical metadata/versioning permission proof on `/api/zena/documents*`
- Outcome: locked runtime slice + done verdict
- Key files:
  - `app/Http/Controllers/Api/SimpleDocumentController.php`
  - `routes/api_zena.php`
  - `tests/Feature/Api/DocumentManagementTest.php`
  - `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - runtime commit: `88f3872c68b590e3e8d309f480a2774f89505114`

### Round 13

- Date: 2026-03-29
- Scope: `S2.2` canonical document linking on `/api/zena/documents*`
- Outcome: locked runtime slice + done verdict
- Key files:
  - `app/Http/Controllers/Api/SimpleDocumentController.php`
  - `app/Models/Document.php`
  - `routes/api_zena.php`
  - `tests/Feature/Api/DocumentManagementTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - routes: `GET /api/zena/documents`; `GET /api/zena/documents/{id}`; `POST /api/zena/documents/{id}/link`; `DELETE /api/zena/documents/{id}/link`
  - tests: `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `25 passed`; `php artisan test tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `2 passed`
  - lint: `composer ssot:lint`
- Deferred:
  - no `/api/v1/*` changes
  - no broad document/media/storage cleanup
  - no second document-owner surface outside Document Center
- Notes:
  - Canonical link contract is `documents.linked_entity_type` + `documents.linked_entity_id`.
  - Canonical runtime now proves tenant-safe attach/detach to `task`, `component`, and `cr` targets on the Document Center owner path.
  - Reverse lookup proof stays on `GET /api/zena/documents` with explicit link filters instead of introducing a second file-owner API.
  - routes: `GET /api/zena/documents`; `POST /api/zena/documents`; `PUT /api/zena/documents/{id}`; `GET /api/zena/documents/{id}/versions`; `POST /api/zena/documents/{id}/versions`
  - tests: `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `19 passed`; `php artisan test tests/Feature/DocumentVersioningTest.php` -> `8 passed`; `php artisan test tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `2 passed`
  - lint: `composer ssot:lint`
- Deferred:
  - `DocumentApiTest` download-path failure remains unrelated and is not part of `S2.1` evidence
  - no `/api/v1/*` changes were used as forward-proof surface
- Notes:
  - This round closed a real runtime gap: canonical `/api/zena/documents/{id}/versions` was missing from mounted route truth even though backlog evidence already referenced it.
  - Canonical proof now covers metadata store/search/update, version history retention, tenant-safe version access, and permission enforcement on canonical version read/write paths.

### Round 14

- Date: 2026-03-29
- Scope: `S3.1` acceptance review against canonical runtime and tests
- Outcome: docs-only done verdict
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-29-s3-1-affected-scope-contract.md`
- Evidence:
  - head reviewed: `1a83a8c408fc53b99adfa216345709b26940989b`
  - routes: `php artisan route:list | grep -E "change-requests|documents|tasks|components|link" || true`
  - tests: `php artisan test tests/Feature/ChangeRequestApiTest.php` -> `24 passed`; `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `13 passed`; `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `25 passed`
- Deferred:
  - broader reverse-query surfaces beyond `GET /api/zena/change-requests/{id}` summary
  - richer affected-scope payload semantics beyond the current minimal summary
  - any cleanup/removal of legacy `CrLink::LINKED_TYPE_DOCUMENT`
  - any `/api/v1/*` work
- Notes:
  - Canonical `/api/zena/change-requests/{id}/links` now exists and is tested for attach/detach on `task` and `component` only.
  - Canonical same-tenant and same-project enforcement is proved on the CR-owned link mutation path.
  - Canonical `GET /api/zena/change-requests/{id}` now returns `affected_scope_summary` with tasks/components resolved from `cr_links` and documents resolved from Document Center ownership.
  - Explicit test evidence proves `type=document` is rejected on the CR-owned mutation path and that ignored `cr_links(document)` residue is not used as canonical document proof.

### Round 15

- Date: 2026-03-29
- Scope: `S5.1` canonical inspection checklist-template slice on `/api/zena/work-templates` and `/api/zena/inspections`
- Outcome: locked runtime slice + done verdict
- Key files:
  - `app/Http/Controllers/Api/InspectionController.php`
  - `app/Models/QcInspection.php`
  - `database/migrations/2026_03_29_120000_add_work_instance_step_id_to_qc_inspections_table.php`
  - `tests/Feature/Api/WorkTemplateBaselineSeederTest.php`
  - `tests/Feature/Api/InspectionTemplateRuntimeTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - head reviewed: `692aff0f5ff06d9fcc6d83934ea88dd91cb59544`
  - routes: `php artisan route:list | grep -E "work-template|inspection|checklist" || true`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateBaselineSeederTest.php` -> `OK (3 tests, 379 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/InspectionTemplateRuntimeTest.php` -> `OK (3 tests, 30 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=Inspection` -> `OK (11 tests, 95 assertions)`
  - lint: `composer ssot:lint`
- Deferred:
  - notification semantics
  - NCR/CAPA/escalation/dashboard work
  - `/api/v1/*`
  - exact status-code semantics for foreign-tenant generated-step linkage
- Notes:
  - Canonical proof reuses the existing published baseline template `WT-BL-INSPECTION`; no WorkTemplate redesign was introduced.
  - Preview/apply prove an `inspection` step generates checklist snapshot artifacts under `work_instance_steps` ownership.
  - Canonical inspection runtime now persists an optional `work_instance_step_id` link, returns generated checklist metadata, and syncs checklist execution back into `work_instance_field_values`.
  - `php -d pcov.enabled=0 ./vendor/bin/phpunit --filter=WorkTemplateBaselineSeederTest` currently returns `No tests executed!` in this repo, so direct-file execution is the positive evidence path for that suite.

### Round 16

- Date: 2026-03-29
- Scope: `S1.1` canonical WorkTemplate v2 data-model contract on `/api/zena/work-templates`
- Outcome: locked runtime slice + done verdict
- Key files:
  - `app/Http/Controllers/Api/WorkTemplateController.php`
  - `tests/Feature/Api/WorkTemplateMvpApiTest.php`
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
- Evidence:
  - head reviewed: `635e4bc6c7a2792684ec23ad03afaade9be69330`
  - routes: `php artisan route:list --path=api/zena/work-templates`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateMvpApiTest.php` -> `OK (23 tests, 228 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateBaselineSeederTest.php` -> `OK (3 tests, 379 assertions)`
  - lint: `composer ssot:lint`
- Deferred:
  - broad reviewer/approver semantics beyond the persisted template contract
  - notification fan-out
  - `/api/v1/*`
  - broad WorkTemplate architecture cleanup
- Notes:
  - Canonical proof is intentionally limited to the persisted template contract: `content_json`, `work_template_versions`, `work_template_steps`, and `work_template_fields`.
  - A real runtime gap was closed in the canonical CRUD owner path: `show()` now returns nested `versions.steps.fields`, so API round-trip matches the persisted relational contract instead of exposing only version headers.
  - New targeted feature coverage proves canonical create/show/update round-trip for `steps`, `fields`, `assignee_rule`, checklist/docs config, `approvals`, and `rules`, plus CRUD RBAC proof.
  - Preview/publish/apply stay aligned to the same persisted contract after API create/update; this round does not claim broader reviewer/approver decision semantics or notifications.

## 5. Progress By Roadmap

### EPIC-1: Process Template Engine (WorkTemplate v2)

#### S1.1 WorkTemplate v2 data model

- Roadmap status: done
- Progress status: done
- Current state:
  - Canonical `/api/zena/work-templates` now proves create/show/update round-trip for the persisted WorkTemplate v2 contract.
  - Proved contract includes `steps`, nested `fields`, `assignee_rule`, checklist/docs config, `approvals`, and `rules`.
  - Tenant isolation and CRUD RBAC are now explicitly covered on the canonical owner path.
  - Preview/publish/apply continue to consume the same persisted contract after canonical create/update without `/api/v1/*` proof.
- Evidence:
  - routes: `GET /api/zena/work-templates`; `POST /api/zena/work-templates`; `GET /api/zena/work-templates/{id}`; `PUT /api/zena/work-templates/{id}`; `POST /api/zena/work-templates/{id}/preview`; `POST /api/zena/work-templates/{id}/publish`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateMvpApiTest.php` -> `OK (23 tests, 228 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateBaselineSeederTest.php` -> `OK (3 tests, 379 assertions)`
  - lint: `composer ssot:lint`
- Deferred / remaining:
  - semantic meaning of `approvals` and `rules` beyond persisted contract is still `UNKNOWN`
  - reviewer/approver discovery semantics remain out of scope for this story
  - notification semantics remain out of scope for this story
- Next action:
  - keep WorkTemplate CRUD, preview, publish, and apply aligned to the same canonical persisted contract without widening semantics beyond evidence.

#### S1.2 Apply template to Project/Component

- Roadmap status: done
- Progress status: locked
- Current state:
  - Canonical apply endpoints exist for both project and component scope.
  - Evidence-backed backlog notes say apply creates tasks, assignments, due dates, and checklist or required-document snapshots.
  - Idempotent behavior is recorded as fingerprint-based in backlog evidence.
- Evidence:
  - routes: `POST /api/zena/projects/{id}/apply-template`; `POST /api/zena/components/{id}/apply-template`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateMvpApiTest.php` -> `OK (20 tests, 168 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/WorkTemplateBaselineSeederTest.php` -> `OK (2 tests, 366 assertions)`
  - locking commit: `434b67d111006c1603925063971da555de7a349b`
- Deferred / remaining:
  - none on this story based on current evidence
- Next action:
  - keep this surface stable and avoid introducing drift between preview/apply/runtime ownership.

#### S1.3 Template preview & dry-run

- Roadmap status: done
- Progress status: locked
- Current state:
  - Preview and dry-run are already represented as completed in backlog.
  - Route and notes indicate preview returns planned workflow artifacts and dry-run avoids DB writes.
- Evidence:
  - route: `POST /api/zena/work-templates/{id}/preview`
  - dry-run evidence: project/component apply supports `dry_run=true` with no DB writes
  - tests: backlog records `php artisan test tests/Feature/Api/WorkTemplateMvpApiTest.php (20 passed)`
- Deferred / remaining:
  - UNKNOWN beyond keeping parity with apply behavior
- Next action:
  - no new work unless a later epic requires preview/apply parity verification.

### EPIC-2: Document Center (DocumentManagement v2)

#### S2.1 Document types + metadata + versioning

- Roadmap status: done
- Progress status: done
- Current state:
  - Canonical `/api/zena/documents` now proves metadata store/search/update for `document_type`, `discipline`, `package`, `status`, and `revision`.
  - Canonical `/api/zena/documents/{id}/versions` now exists in mounted runtime truth and proves version history retention on the forward business surface.
  - Canonical permission evidence now covers tenant-safe document/version access, version-list read access, and version-create rejection when `document.update` is missing.
  - No proof for this story depends on `/api/v1/*`.
- Evidence:
  - runtime commit: `88f3872c68b590e3e8d309f480a2774f89505114`
  - routes: `php artisan route:list | grep -E "documents|versions" || true`
  - tests: `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `19 passed`; `php artisan test tests/Feature/DocumentVersioningTest.php` -> `8 passed`; `php artisan test tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `2 passed`
  - lint: `composer ssot:lint`
- Deferred / remaining:
  - `DocumentApiTest` download-path failure remains outside this story
  - later document workflow expansion stays in `S2.3`
- Next action:
  - keep `S2.1` stable and use later stories for linkages, workflow expansion, or broader document-center behavior.

#### S2.3 Document workflow canonical slice

- Roadmap status: done
- Progress status: locked
- Current state:
  - A minimal canonical workflow slice exists on the active owner path.
  - Current proven scope is `draft -> submitted -> approved|rejected` through `submit` and `decision`.
  - Acceptance is now narrowed to this proved canonical slice and should not be overclaimed as a full review workflow.
- Evidence:
  - commit: `bba51d3f042633884c1459903c085c9e0415f79f`
  - routes: `POST /api/zena/documents/{id}/submit`; `POST /api/zena/documents/{id}/decision`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DocumentManagementTest.php` -> `OK (12 tests, 56 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `OK (2 tests, 56 assertions)`
- Deferred / remaining:
  - separate review matrix
  - reviewer or approver expansion beyond management-policy authorization on `decision()`
  - notifications on state change
- Next action:
  - keep `S2.3` closed at the proved canonical slice; require a later evidence-backed story or proposal before claiming review-matrix or notification behavior.

### Round 13

- Date: 2026-03-29
- Scope: `S2.3` planning adjustment to align backlog acceptance with canonical document workflow proof
- Outcome: docs-only done verdict
- Key files:
  - `docs/roadmap/backlog.yaml`
  - `docs/progress.md`
  - `docs/change-proposals/2026-03-27-s2-3-document-workflow-canonical-slice.md`
- Evidence:
  - head reviewed: `a5928953c4f41cacf54bc3630ded1b20e8ca0772`
  - routes: `php artisan route:list | grep -E "documents|submit|decision|review|approve|reject" || true`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DocumentManagementTest.php` -> `OK (12 tests, 56 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `OK (2 tests, 56 assertions)`
- Deferred:
  - separate `review` route or matrix semantics on canonical documents
  - broad reviewer/approver discovery semantics
  - notification behavior on document state changes
  - any `/api/v1/*` work
- Notes:
  - This round chooses the narrow-first planning adjustment: `S2.3` is now defined only as the already-proved canonical `submit` + `decision` slice on `/api/zena/documents`.
  - No runtime proof in this verdict claims a separate review stage or any notification contract.

#### S2.4 Document search & filters

- Roadmap status: done
- Progress status: locked
- Current state:
  - Canonical document index search is treated as complete in backlog with tenant-safe filtering proof.
  - Search is scoped to canonical `/api/zena/documents` rather than compatibility-first surfaces.
- Evidence:
  - route: `GET /api/zena/documents`
  - tests: backlog records `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DocumentManagementTest.php (6 passed)`
  - locking commit: `434b67d111006c1603925063971da555de7a349b`
- Deferred / remaining:
  - none for this story based on current backlog evidence
- Next action:
  - preserve filter behavior and tenant isolation while later document workflow slices expand.

### EPIC-3: Change Order (ChangeRequest v2)

#### S3.2 Canonical workflow + audit + minimal direct-recipient notifications

- Roadmap status: done
- Progress status: done
- Current state:
  - The narrowed `S3.2` acceptance is fully proved on canonical `/api/zena/change-requests`.
  - Runtime truth proves `submit: draft -> submitted`.
  - Runtime truth proves `approve` and `reject` are only allowed from `submitted`.
  - Generic update-status bypass is blocked on the canonical path.
  - Canonical audit proof exists for the workflow mutations covered by this story.
  - Minimal canonical in-app notification proof exists end-to-end for `submit`, `approve`, and `reject`.
  - The proved notification boundary is `submit -> one explicit approver recipient fixture`, `approve/reject -> requester`, with broader semantics intentionally deferred.
  - `/api/v1/*` compatibility routes were not touched.
- Evidence:
  - proposal commit: `a16ed7c4b62bfc1a7c125bd505e6c5dd507628a4`
  - wording-tighten commit: `9600ef0c814b016a1caf92e646967bc77025f9e1`
  - runtime-lock commit: `fb45a35ab6ebd3a7177a7d1317a459c7d416e270`
  - notification runtime commit: `a41ee056`
  - verdict head: `9bf27efbb2437cd37fa0f7c8cd6f730523bab92a`
  - planning split date: `2026-03-29`
  - routes: `php artisan route:list --path=api/zena/change-requests` shows `submit`, `approve`, `reject`, `apply`
  - planning lock: `docs/change-proposals/2026-03-28-s3-2-canonical-change-request-notification-contract.md`
- Deferred / remaining:
  - none inside the narrowed `S3.2` boundary
- Next action:
  - keep `S3.2` stable and use `S3.2a` for any later stakeholder or broader notification work.

#### S3.2a Broader approver/stakeholder notification semantics for canonical change requests

- Roadmap status: todo
- Progress status: deferred
- Current state:
  - This follow-up story exists to isolate semantics that the canonical path does not currently define.
  - Current evidence does not define broad approver discovery.
  - Current evidence does not define who counts as a stakeholder on `/api/zena/change-requests`.
  - `apply` notification remains outside the proved minimal canonical notification boundary.
- Evidence:
  - current canonical proof only covers `submit -> assigned_to fixture` and `approve/reject -> requested_by`
  - planning references: `docs/change-proposals/2026-03-28-s3-2-change-request-workflow-state-machine-unification.md`; `docs/change-proposals/2026-03-28-s3-2-canonical-change-request-notification-contract.md`
- Deferred / remaining:
  - define canonical stakeholder semantics from evidence-backed owner fields or route truth
  - prove any broader fan-out end-to-end on `/api/zena/change-requests`
  - decide separately whether `apply` notification belongs in this follow-up or another later slice
- Next action:
  - do not implement this slice until the repo has explicit canonical evidence for broader recipient semantics.

#### S3.1 CR affected scope split

- Roadmap status: done
- Progress status: done
- Current state:
  - `S2.2` and `S3.4` already lock document ownership to Document Center through `documents.linked_entity_type` and `documents.linked_entity_id`.
  - `S3.3` already proves canonical task link-back through `cr_links` from `apply()`.
  - Canonical `/api/zena/change-requests/{id}/links` now exists for CR-owned affected-scope mutation and is explicitly limited to `task|component`.
  - Canonical `GET /api/zena/change-requests/{id}` now exposes `affected_scope_summary`, with document scope sourced from Document Center ownership instead of `cr_links(document)`.
  - `App\Models\CrLink` still exposes `document` as a legacy link type, but current canonical proof explicitly ignores it for `S3.1`.
- Evidence:
  - route truth: `php artisan route:list | grep -E "change-requests|documents|tasks|components|link" || true`
  - controller truth: `app/Http/Controllers/Api/ChangeRequestController.php`
  - canonical document owner path: `app/Http/Controllers/Api/SimpleDocumentController.php`
  - planning lock: `docs/change-proposals/2026-03-29-s3-1-affected-scope-contract.md`
  - tests: `php artisan test tests/Feature/ChangeRequestApiTest.php` -> `24 passed`; `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `13 passed`; `php artisan test tests/Feature/Api/DocumentManagementTest.php` -> `25 passed`
- Deferred / remaining:
  - exact `affected_scope_summary` response shape beyond the current minimal first slice
  - any broader reverse-query family
  - any cleanup of legacy/web controllers
  - any runtime removal of legacy `CrLink::LINKED_TYPE_DOCUMENT`
- Next action:
  - keep the canonical split stable: task/component affected scope on `cr_links`, document ownership on Document Center, and no `/api/v1/*` proof.

#### S3.3 Approved CR creates delta tasks + baseline delta

- Roadmap status: done
- Progress status: done
- Current state:
  - Canonical runtime truth places downstream implementation at `POST /api/zena/change-requests/{id}/apply`, not at `approve()`.
  - `apply()` now keeps `approve()` approval-only while persisting one canonical task delta, one canonical task link-back, and one canonical baseline delta on the same canonical runtime path.
  - `apply()` still proves `approved -> implemented`, now with persisted `implementation_notes`.
  - Baseline proof anchor is a canonical `baselines.linked_contract_id` row created from the applied change request, with matching `baseline_history` evidence.
- Evidence:
  - route surface: `routes/api_zena.php` exposes canonical `change-requests` actions plus canonical `/api/zena/tasks`
  - controller truth: `app/Http/Controllers/Api/ChangeRequestController.php` keeps budget/schedule mutation in `approve()` and creates the minimal canonical task + `cr_links` + baseline artifacts from `apply()`
  - persistence truth: `database/migrations/2026_03_29_000000_create_canonical_change_request_delta_tables.php` creates the canonical `cr_links`, `baselines`, and `baseline_history` tables missing from the current migration set
  - model truth: `app/Models/BaselineHistory.php` now matches the canonical ULID/string baseline foreign key used by `baselines`
  - proposal lock: `docs/change-proposals/2026-03-29-s3-3-apply-boundary-delta-contract.md`
  - tests: `php artisan test tests/Feature/Api/ChangeRequestApiTest.php` -> `11 passed`; `php artisan test tests/Feature/ChangeRequestApiTest.php` -> `19 passed`; `composer ssot:lint` -> passed
- Deferred / remaining:
  - exact task-delta semantics beyond minimal persisted create-or-update proof
  - exact baseline-delta shape beyond minimal persisted baseline row + history proof
  - any `/api/v1/*` involvement
  - any broad baseline architecture cleanup
- Next action:
  - keep later CR delta semantics, richer baseline behavior, and any notification follow-up as separate stories.

#### S3.4 CR timeline + attachments

- Roadmap status: done
- Progress status: locked
- Current state:
  - Canonical `/api/zena/change-requests/{id}/timeline` exists and is backed by audit-log workflow history for `submit`, `approve`, `reject`, and `apply`.
  - Canonical `/api/zena/documents` supports CR-link query proof through `linked_entity_type=cr` and `linked_entity_id={changeRequestId}`.
  - `App\Models\ChangeRequest` does carry an `attachments` JSON field, but current canonical acceptance does not use that field as the forward attachment proof surface.
  - Current evidence is sufficient for the narrowed `S3.4` acceptance wording in backlog and does not require any `/api/v1/*` proof.
- Evidence:
  - route truth: `php artisan route:list | grep -E "change-requests|timeline|documents" || true`
  - canonical CR owner: `app/Http/Controllers/Api/ChangeRequestController.php`
  - canonical document owner: `app/Http/Controllers/Api/SimpleDocumentController.php`
  - timeline proof: `tests/Feature/Api/ChangeRequestApiTest.php`
  - audit source proof: `tests/Feature/Zena/ZenaAuditInvariantTest.php`
  - CR-link filter proof: `tests/Feature/Api/DocumentManagementTest.php`
  - proposal lock: `docs/change-proposals/2026-03-29-s3-4-cr-timeline-attachments-contract.md`
- Deferred / remaining:
  - exact timeline inclusion rules beyond audit-backed workflow history
  - exact timeline ordering and response schema details
  - nested attachment write/delete routes
  - attachment delete/version semantics
  - any broad document/media/storage cleanup
- Next action:
  - keep any future work narrow: extend timeline or attachment semantics only with fresh canonical evidence, without reopening `/api/v1/*` or using `change_requests.attachments` as proof.

#### S4.1 Material catalog + vendor list

- Roadmap status: done
- Progress status: locked
- Current state:
  - Canonical runtime now exposes `/api/zena/materials` and `/api/zena/vendors` with minimal CRUD only.
  - Current proof covers tenant-safe anti-enumeration, dedicated RBAC, and basic identifying master-data fields only.
  - `MaterialRequest` remains residue plus dashboard projection and is explicitly excluded from owner proof.
  - `SubmittalController` still owns `/api/zena/submittals`, so submittal package semantics stay out of `S4.1`.
- Evidence:
  - route truth: `php artisan route:list | grep -E "materials|vendors" || true`
  - canonical owners: `app/Http/Controllers/Api/MaterialController.php`; `app/Http/Controllers/Api/VendorController.php`
  - model/schema: `app/Models/Material.php`; `app/Models/Vendor.php`; `database/migrations/2026_03_29_180000_create_materials_and_vendors_tables.php`
  - proof tests: `tests/Feature/MaterialApiTest.php`; `tests/Feature/VendorApiTest.php`; `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - contract lock: `docs/change-proposals/2026-03-29-s4-1-material-vendor-owner-contract.md`
- Deferred / remaining:
  - BOQ linkage
  - submittal ownership or approvals
  - delivery/receipt
  - notifications
  - compensation linkage
  - any canonical material-request workflow story
- Next action:
  - keep future procurement work narrow by layering BOQ, submittals, delivery/receipt, or compensation only on fresh evidence without reopening `MaterialRequest` as the owner surface.

## 6. Next Action Queue

1. Preserve the current canonical notification write path on `/api/zena/change-requests` without reopening `/api/v1/*` compatibility surfaces.
2. Keep `apply` notifications, broad stakeholder fan-out, and email/job/mail paths deferred until separate evidence exists.
3. Keep `S3.2` closed at the proved canonical slice and treat stakeholder/broader notification semantics as deferred `S3.2a` work.
4. Use the `S3.1` proposal lock before any runtime work: task/component affected scope via `cr_links`, document scope via Document Center ownership, and no overlapping canonical document contract.
5. Treat S3.3 as complete at the proved minimal canonical `apply()` slice and avoid reopening it for broader baseline or compatibility redesign.
6. Keep `S3.4` closed at the proved canonical slice: timeline from audit-backed workflow history and attachments from Document Center CR links, with richer semantics deferred to later stories if needed.
7. Treat `S4.1` as closed at the proved canonical master-data slice and keep any follow-up procurement work on later stories without reopening `MaterialRequest` as owner proof.

## 7. Out Of Scope / Deferred

- Broad notification fan-out beyond proof-backed canonical workflow behavior.
- Broad baseline architecture redesign or cleanup beyond the narrow `S3.3` proposal contract.
- Broad document/media/storage architecture cleanup beyond the narrow `S3.4` contract proposal.
- Full document review matrix until route/runtime/test evidence exists.
- `/api/v1/*` compatibility remapping or forward-owner expansion.
- BOQ, submittals, delivery/receipt, procurement approvals, notifications, or compensation linkage inside the first `S4.1` runtime round.
- Any implementation claim not backed by route, runtime, test, or lint evidence.
