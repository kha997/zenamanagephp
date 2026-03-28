# Progress

## 1. Project Header

- Project: Build zena webapp / zenamanage-golden
- Last updated: 2026-03-28
- Branch: main
- Goal: deploy the real webapp and do not change domain/app logic just to pass test/CI

## 2. Executive Snapshot

The repo is in a controlled evidence-locking phase around the canonical `/api/zena/*` business surface. Recent work locked backlog-backed completion for `S1.2` and `S2.4`, shipped a minimal canonical document workflow slice for `S2.3`, and wrote the first implementation-ready proposal for `S3.2` without overclaiming runtime proof. The open work is now concentrated in change-request workflow unification, where runtime routes exist but status vocabulary, transition-guard proof, canonical audit alignment, and notification proof still drift. The next focus should stay narrow: patch `S3.2` on the active owner path, prove the smallest valid state machine, and defer anything not supported by route/test/runtime evidence.

## 3. Operating Rules

- `docs/roadmap/backlog.yaml` is the story-status and planning SSOT.
- `docs/progress.md` is the execution-progress SSOT for round history, current state, and next actions.
- Runtime truth comes from `php artisan route:list`.
- Evidence first: if evidence is missing or weak, write `UNKNOWN`.
- `/api/zena/*` is the canonical forward business surface.
- `/api/v1/*` is compatibility-only, not the forward owner surface.
- Do not change domain/app logic just to pass test or CI.

## 4. Recent Locked Rounds

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

## 5. Progress By Roadmap

### EPIC-1: Process Template Engine (WorkTemplate v2)

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

#### S2.3 Document workflow canonical slice

- Roadmap status: todo
- Progress status: locked
- Current state:
  - A minimal canonical workflow slice exists on the active owner path.
  - Current proven scope is `draft -> submitted -> approved|rejected` through `submit` and `decision`.
  - This is narrower than the full backlog story and should not be overclaimed as a full review workflow.
- Evidence:
  - commit: `bba51d3f042633884c1459903c085c9e0415f79f`
  - routes: `POST /api/zena/documents/{id}/submit`; `POST /api/zena/documents/{id}/decision`
  - tests: `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Api/DocumentManagementTest.php` -> `OK (12 tests, 56 assertions)`; `php -d pcov.enabled=0 ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php` -> `OK (2 tests, 56 assertions)`
- Deferred / remaining:
  - full review matrix
  - reviewer or approver expansion
  - notifications on state change
- Next action:
  - decide whether to keep backlog story status as `todo` until broader acceptance proof exists, or split the minimal slice into its own explicitly accepted sub-scope.

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

#### S3.2 Change Request workflow state machine unification

- Roadmap status: todo
- Progress status: planning-ready
- Current state:
  - This story is not implemented in the current round.
  - Runtime truth currently proves `submit: draft -> submitted`.
  - Runtime truth currently proves `apply: approved -> implemented`.
  - `approve/reject` do not yet have explicit transition-guard proof on the canonical path.
  - Canonical audit via `ZenaAuditLogger` is not yet aligned on the active owner controller.
  - Notifications are only capability-level proven, not canonical end-to-end proven on `/api/zena/change-requests`.
- Evidence:
  - proposal commit: `a16ed7c4b62bfc1a7c125bd505e6c5dd507628a4`
  - wording-tighten commit: `9600ef0c814b016a1caf92e646967bc77025f9e1`
  - routes: `php artisan route:list --path=api/zena/change-requests` shows `submit`, `approve`, `reject`, `apply`
- Deferred / remaining:
  - status vocabulary unification across controller/model/request/tests
  - explicit valid and invalid transition proof for `approve/reject`
  - canonical audit alignment through `App\Services\ZenaAuditLogger`
  - notification work unless direct proof exists on the canonical path
- Next action:
  - move from proposal to a narrow implementation plan targeting only the active `App\Http\Controllers\Api\ChangeRequestController` path and the smallest provable state machine.

## 6. Next Action Queue

1. Plan `S3.2` implementation against the canonical `/api/zena/change-requests` owner path.
2. Run a status-vocabulary inventory across controller, model, request, and tests to define one patch plan.
3. Add explicit transition guards for `submit`, `approve`, and `reject` with positive and negative proof.
4. Align canonical audit logging through `App\Services\ZenaAuditLogger` on the active owner controller.
5. Add notification behavior only if direct canonical proof can be established during implementation; otherwise defer it explicitly.

## 7. Out Of Scope / Deferred

- Broad notification fan-out beyond proof-backed canonical workflow behavior.
- Full document review matrix until route/runtime/test evidence exists.
- `/api/v1/*` compatibility remapping or forward-owner expansion.
- Any implementation claim not backed by route, runtime, test, or lint evidence.
