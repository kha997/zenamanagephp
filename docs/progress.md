# Progress

## 1. Project Header

- Project: Build zena webapp / zenamanage-golden
- Last updated: 2026-03-29
- Branch: main
- Goal: deploy the real webapp and do not change domain/app logic just to pass test/CI

## 2. Executive Snapshot

The repo is in a controlled evidence-locking phase around the canonical `/api/zena/*` business surface. Recent work locked backlog-backed completion for `S1.2` and `S2.4`, shipped a minimal canonical document workflow slice for `S2.3`, and implemented the narrow runtime slices for `S3.2` on the canonical change-request owner path without overclaiming broader workflow ownership. With the acceptance boundary now narrowed to the already-proved canonical slice, `S3.2` is evidence-complete and can be marked done, while `S3.2a` remains the explicit follow-up for broader approver/stakeholder semantics and any later notification expansion.

For `S3.2`, the former planning gap was backlog wording that bundled `approvers and stakeholders` into one acceptance surface. That gap is now resolved by the planning split: narrowed `S3.2` owns only the proved canonical workflow + minimal direct-recipient notification slice, and `S3.2a` owns the still-unknown broader approver/stakeholder semantics.

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

## 6. Next Action Queue

1. Preserve the current canonical notification write path on `/api/zena/change-requests` without reopening `/api/v1/*` compatibility surfaces.
2. Keep `apply` notifications, broad stakeholder fan-out, and email/job/mail paths deferred until separate evidence exists.
3. Keep `S3.2` closed at the proved canonical slice and treat stakeholder/broader notification semantics as deferred `S3.2a` work.
4. Treat S3.3 as complete at the proved minimal canonical `apply()` slice and avoid reopening it for broader baseline or compatibility redesign.

## 7. Out Of Scope / Deferred

- Broad notification fan-out beyond proof-backed canonical workflow behavior.
- Broad baseline architecture redesign or cleanup beyond the narrow `S3.3` proposal contract.
- Full document review matrix until route/runtime/test evidence exists.
- `/api/v1/*` compatibility remapping or forward-owner expansion.
- Any implementation claim not backed by route, runtime, test, or lint evidence.
