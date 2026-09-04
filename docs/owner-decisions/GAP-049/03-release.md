---
work_id: GAP-049
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md
  plan: docs/superpowers/plans/2026-09-03-gap-049-production-deployment-implementation.md
  branch: impl/GAP-049-production-deployment
  pr: "https://github.com/kha997/zenamanagephp/pull/302"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-04T02:00:00Z"
  owner_response_reference: "No Owner decision has been requested or recorded yet. This is the initial Gate-3 packet presentation for GAP-049, submitted for Owner technical review following full implementation, task-level review, a final whole-branch review, and one consolidated fix round addressing that review's findings."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-04T02:00:00Z"
  updated_at: "2026-09-04T02:00:00Z"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "GAP-049 implements the Owner-approved Gate-2 architecture (Candidate A — hardened, exact-SHA release-based SSH deployment) across 12 tasks: retirement of every competing production-deployment entry point (deploy.yml/deploy.sh deleted; ci-cd.yml's placeholder deploy job removed; automated-deployment.yml and release-management.yml production-reaching jobs disabled via if:false), a minimal non-diagnostic-leaking production readiness endpoint, an expand-vs-breaking migration classification contract enforced in code, immutable-release filesystem tooling with a true atomic current-symlink switch on GNU/Linux, a queue-worker liveness canary, a production-safe first-database bootstrap command, least-privilege backup/restore scripts with a proven disposable-environment restore drill, pre-release two-tenant negative-isolation evidence, three operational runbooks, and the hardened production.yml workflow itself as the sole live production entry point. All GAP-049-scoped tests pass (48/48, tests/Feature/Deployment/**). Task-level review found and fixed 6 genuine implementation bugs during the build (activate-release.sh atomicity regression, backup.sh checksum non-portability, queue-canary-drill.sh race condition, plus 3 self-consistency issues where generated docblocks literally contained their own forbidden guard-test substrings). A final whole-branch review (dispatched on the most capable available model specifically to catch cross-task integration issues no single-task review could see) found 2 Critical and 10 Important findings; all 10 Important findings plus 1 Critical (a deploy-job checkout gap that would have made the workflow fail on its first real invocation) were fixed and re-reviewed clean in one consolidated fix wave. The second Critical finding (a pre-existing, out-of-scope security defect in app/Http/Controllers/AuthController.php, unrelated to and untouched by this branch, but inadvertently made exploitable at the super_admin level once GAP-049's production:bootstrap command creates a super_admin role) is NOT fixed here — it is business/authentication code outside GAP-049's authorized scope, and is escalated below as a blocking pre-deployment risk requiring a separate Work ID. One architectural gap (no automated rollback/maintenance-mode branch on post-cutover health-check failure — one of the six designed deployment states, rolled_back, is consequently unreachable from this workflow) is documented below as an explicit Owner decision point rather than silently implemented or omitted. Zero actual production deployment, secret configuration, host provisioning, or production database mutation occurred anywhere in this branch's history."
technical_evidence:
  subject_sha: "29ba1a7f047cb442a4111d6fff5599e6a1cc9d7e"
  implementation_tree_digest: "659f54dc8256b697b1122b81a68477c4646c435c0e0dcc2e60b1f9277b4e3211"
  verified_pr_head_sha: "PENDING_UPDATE_AFTER_PUSH"
  verified_at: "2026-09-04T02:30:00Z"
owner_decision_binding:
  implementation_tree_digest: "659f54dc8256b697b1122b81a68477c4646c435c0e0dcc2e60b1f9277b4e3211"
  decision_recorded_at: "2026-09-04T02:30:00Z"
---

# GAP-049 — Production Deployment Hardening: Gate 3 Release Request

**Gate 3 packet status: `awaiting_owner`.** This is the first presentation of
this packet. Implementation, task-level review, a final whole-branch review,
and one consolidated fix round are complete. No Owner decision has been
requested or recorded before this submission. Production deployment,
secret configuration, host provisioning, and production database mutation
remain **not authorized** by this Work ID at any point up to and including
this submission.

## Canonical implementation baseline

- Expected canonical `origin/main` at handoff (per task instructions):
  `dfd936dbbd88400013488e0bb2e3bb21e126e535` — confirmed exact match at
  session start (`git fetch origin --prune`, `git rev-parse origin/main`).
- Gate 1: APPROVED, merged via PR #300.
- Gate 2: APPROVED, merged via PR #301 (this packet's `owner_gate_2_record`
  is `docs/owner-decisions/GAP-049/02-design.md`, `gate_status: approved`,
  satisfying the gate-ordering precondition for this Gate-3 packet).
- Independently confirmed at session start that no prior implementation
  branch/PR already owned this work (`git branch -a`, `gh pr list --search
  "GAP-049"` showed only PR #300/#301, both merged design/evidence gates).
- Implementation branch: `impl/GAP-049-production-deployment`, created from
  `origin/main` at `dfd936db...`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-gap-049-production-deployment-implementation.md`
  (12 tasks, written before any implementation code, per
  `superpowers:writing-plans`).

## What was implemented (task-by-task summary)

1. **Retired every competing production-deployment entry point.** Deleted
   `.github/workflows/deploy.yml` and `deploy.sh`. Removed `ci-cd.yml`'s
   placeholder `deploy` job (the one that only echoed "Deploying to
   production..."). Disabled (`if: false`, preserved not deleted, per the
   Owner's Gate-2 "documented future upgrade path" directive) every
   production-reaching job in `automated-deployment.yml`
   (`deploy-production`, `rollback`, `blue-green-deployment`,
   `canary-deployment`) and — discovered mid-implementation, a genuine gap
   in the original research/plan that was corrected before merging — every
   production-reaching job in `release-management.yml` (`deploy-release`,
   `rollback-release`, `cleanup-release`), a fourth live SSH-deploy path
   using `git pull origin main` and `git reset --hard HEAD~1` that the
   original Gate-1/Gate-2 evidence review had missed entirely. A standing
   regression guard (`tests/Feature/Deployment/DeploymentGuardTest.php`)
   asserts `production.yml` is the only workflow file with a live,
   non-disabled SSH deploy step.
2. **Minimal production readiness endpoint** — `GET
   /api/v1/public/production/ready` (`app/Http/Controllers/Api/ProductionReadinessController.php`):
   genuine DB (`SELECT 1`), cache (write/read round-trip), and storage
   (write/read/delete round-trip) probes; 200 `{"status":"ready"}` only
   when all pass, 503 `{"status":"not_ready","failed":[...]}` otherwise;
   no PHP/Laravel version, `APP_ENV`, memory/load, or credentials in the
   response body. Cleanup of probe artifacts uses try/finally so a
   partial failure never orphans a probe key/file.
3. **Migration classification contract** —
   `database/migrations/classifications.json` (209 migrations classified;
   6 reclassified from `expand` to `breaking` during final review after a
   reviewer correctly identified genuine schema-breaking renames/recreates
   that had been defaulted to `expand`) + `app/Console/Commands/DeployMigrateCommand.php`
   (`deploy:migrate`), which fails closed (exit 1) on any unclassified
   pending migration, refuses (exit 2) to run a classified-breaking
   migration without `--allow-breaking`, and refuses (exit 3) to proceed
   even with `--allow-breaking` unless the application is already in
   maintenance mode. Never invokes `migrate:rollback` — enforced by a
   dedicated regression test asserting the literal substring never appears
   in the command's source.
4. **Immutable release filesystem tooling** —
   `scripts/deploy/{lib,link-shared,activate-release,rollback,cleanup-releases}.sh`.
   `activate-release.sh` performs a genuinely atomic `current` symlink
   switch via `mv -T` (single `rename()` syscall) on GNU/Linux production
   hosts, with a documented non-atomic fallback used only for local
   macOS/BSD test runs (never production) — this atomicity distinction was
   caught and corrected by the controller during task-level review after
   an initial portability fix silently sacrificed the binding "atomic
   switch" design contract. `rollback.sh` requires an explicit target SHA
   argument (no `HEAD~1`/"most recent" inference). `cleanup-releases.sh`
   never touches anything under `shared/` (test-verified, including a
   `keep_count=0` edge case).
5. **Queue-worker liveness canary** — `app/Jobs/QueueCanaryJob.php` +
   `app/Console/Commands/QueueCanaryCommand.php` (`deploy:queue-canary`),
   which deliberately fails (exit 2, no dispatch) when
   `QUEUE_CONNECTION=sync`, so synchronous execution can never falsely
   satisfy the canary. A disposable, non-CI drill script
   (`scripts/deploy/queue-canary-drill.sh`) proves a real async worker
   processes a real queued job; a race condition in this drill script
   (`queue:work --once` could exit before the probe job was dispatched)
   was found and fixed during Task 11 verification, re-verified 3/3
   consecutive passes after the fix (versus a demonstrated 1-in-3 spurious
   failure rate before it).
6. **Production-safe first-database bootstrap** —
   `app/Console/Commands/ProductionBootstrapCommand.php`
   (`production:bootstrap`): never invokes `DatabaseSeeder`/`db:seed`
   (enforced by a source-scanning regression test); creates exactly one
   real tenant and one real admin with a `Str::password(20, symbols: true)`
   generated credential (never a fixed/default value); idempotent
   fail-closed via an explicit Cache-based completion marker (distinguishes
   "already bootstrapped by this command" from "pre-existing unrelated
   data", since bare `Tenant::count()` cannot).
7. **Least-privilege backup/restore** —
   `scripts/deploy/{backup,restore}.sh`: `mysqldump` scoped to a single
   named database (never `--all-databases`) plus a tar of shared storage,
   both with checksums. A checksum-portability bug (checksums recorded
   against the full backup-time path rather than a basename, which would
   have broken verification the moment an artifact was copied to a
   different directory/host — exactly what a real restore does) was found
   and fixed before the restore-drill evidence below was collected.
8. **Pre-release two-tenant negative-isolation evidence** —
   `tests/Feature/Deployment/TwoTenantIsolationEvidenceTest.php`: proves
   Tenant A cannot read, cannot mutate, and never sees Tenant B's data via
   the real, live `App\Models\Project`/`ProjectController` code paths
   (chosen after reading the repo's existing
   `tests/Feature/MultiTenantIsolationTest.php` and
   `tests/Traits/TenantUserFactoryTrait.php` conventions, per Gate-2 Round-2
   Clarification 2). Final review confirmed the 403/404s genuinely
   originate from tenant-ownership checks in `ProjectController`, not from
   an incidental validation failure or an unrelated permission denial —
   and separately identified that the test's docblock had overclaimed RBAC
   coverage (the default `super_admin` actor bypasses
   `RoleBasedAccessControlMiddleware` entirely), corrected to accurately
   describe what is and is not proven.
9. **Three operational runbooks** —
   `docs/runbooks/gap-049-{host-provisioning,migration-safety,backup-restore}.md`.
   The host-provisioning runbook originally overclaimed that the full
   Gate-2 §6 seven-item production-smoke sequence was automated; corrected
   during final review to honestly state only the queue canary (item 5)
   and the readiness endpoint's own probes are automated, with items 1-4,
   6, 7 explicitly named as a manual post-deploy checklist pending future
   automation.
10. **Hardened `production.yml`** — the sole authoritative production
    entry point: `workflow_dispatch`-only trigger (no automatic deploy on
    push to `main`) with a required exact-SHA input; the checked-out SHA
    is verified to exactly match the requested input and to be reachable
    from `origin/main` (`git merge-base --is-ancestor`, fail-closed on
    arbitrary/unmerged commits); a CI-built, checksummed release artifact
    (mechanism (a) from the Owner's exact-SHA-delivery ruling — the
    production host never independently fetches from GitHub and needs no
    git credential); a secrets gate that reports `not_configured` truthfully
    rather than silently skipping; checksum-verify-before-extract; shared
    `.env`/storage linked before any Artisan command; `deploy:migrate`
    (never a bare `migrate --force`) with `--allow-breaking` gated behind a
    second required `breaking_migration_backup_confirmed` input (added
    during final review — the original design let a breaking migration
    proceed without the runbook's mandatory fresh-backup step being
    enforced); atomic activation only after successful migration;
    `concurrency: {group: production-deploy, cancel-in-progress: false}`;
    and a truthful six-state deployment model where `health_verified` now
    requires BOTH the readiness endpoint AND the queue canary to succeed
    (a final-review finding: the queue canary's own failure was not
    originally reflected in the reported state).
11. **Full verification pass** (Task 11) — see "Verification evidence"
    below.
12. **Final whole-branch review + fix wave** (this task) — see "Final
    review findings and disposition" below.

## Verification evidence (Task 11, exact commands and results)

Full report retained locally at
`.superpowers/sdd/2026-09-03-gap-049-production-deployment-implementation/task-11-report.md`
(gitignored working artifact, not part of this commit — summarized here):

| Check | Command | Result |
|---|---|---|
| Full default-suite test run | `php vendor/bin/phpunit` | 2508 tests, 15602 assertions, 526 errors, 13 failures. **100% pre-existing environment-provisioning debt**: this worktree has no `.env`/`APP_KEY` at all (`MissingAppKeyException` cascades across most HTTP tests) plus one already-documented pre-existing `RedisStore::publish()` bug. Grepped every failure/error for every GAP-049 class/file name — zero matches. Not fixed, per instructions (pre-existing, unrelated). |
| GAP-049-scoped suite | `php vendor/bin/phpunit --filter=Deployment` | **48/48 PASS**, 120 assertions (re-confirmed 48/48 after the final-review fix wave, see below) |
| Route guard | `php artisan route:list --json \| php scripts/ci/route-guard.php` | **PASS** (required a local `-n -d display_errors=stderr` workaround for an unrelated host PHP-extension stdout-pollution issue; the guard's actual logic against real route data passes cleanly) |
| Owner Governance Lint | `php scripts/ssot/owner_governance_lint.php` + `php scripts/ci/lint-mysql-claim-truthfulness.php` | **PASS** for both locally-runnable structural checks (0 violations, 103 files scanned). 3 of 5 CI sub-checks require a real PR + `GH_TOKEN` (gate-ordering, gate-3-before-ready, evidence-freshness) and are NOT-RUNNABLE-HERE — expected, real CI is authoritative for these once this PR is open. |
| PHPStan | `./vendor/bin/phpstan analyse --error-format=json` | **BLOCKED** — this specific worktree's `vendor/phpstan` is a symlink back to the original (non-worktree) repo, causing the same duplicate-autoload-class fatal documented for `php artisan test`. Environment limitation, not a GAP-049 code defect; real CI (a clean checkout, not a worktree copy) is authoritative. |
| Workflow YAML validation | `Symfony\Component\Yaml\Yaml::parseFile()` over every `.github/workflows/*.yml` | **PASS**, 14/14 files (re-confirmed after the final-review fix wave) |
| Frontend build | `npm ci && npm run build` | **PASS**, exit 0/0 |
| Disposable MySQL backup/restore drill | Two disposable Docker containers (`mysql:8.0`) on a private network; unmodified `scripts/deploy/backup.sh`/`restore.sh` | **PASS** — representative DB row (`gap-049-restore-drill-representative-row`) and representative file content, both backed up (checksummed) and restored into a clean disposable database/directory, verified byte-identical after restore. Full container/network cleanup confirmed (`docker ps -a` empty afterward). |
| Disposable queue-canary drill | `scripts/deploy/queue-canary-drill.sh database` against a scratch SQLite `database` queue connection (never touching any real `.env`) | **CONDITIONAL PASS initially** (1 failure in 3 runs, root-caused to a genuine race condition in the drill script — `queue:work --once` could exit before the probe job was dispatched) → **fixed and re-verified 3/3 consecutive passes** after switching to `--max-jobs=1` |

## Final review findings and disposition

A final whole-branch review, dispatched specifically to catch cross-task
integration issues no single task-scoped review could see (most capable
available model, full 21-commit/251KB diff read in full), found:

**2 Critical findings:**
- **C1 — FIXED.** The `deploy` job in `production.yml` had no repository
  checkout, so `scripts/deploy/*.sh` (referenced by the SCP transfer step)
  did not exist anywhere reachable — the workflow would have failed on its
  first real invocation. Fixed by using the scripts already inside the
  extracted release tarball (`${ROOT}/releases/${SHA}/scripts/deploy/`)
  instead of a separate runner-side checkout, which additionally
  guarantees the scripts are byte-identical to the verified SHA. Re-verified
  end-to-end by the scoped fix-wave re-review.
- **C2 — NOT FIXED, ESCALATED TO OWNER (see below).**

**10 Important findings — all fixed in one consolidated fix wave, re-reviewed clean:**
script-injection hardening for `workflow_dispatch` inputs (routed through
`env:`/`envs:` instead of direct interpolation); removed a shallow-fetch
flag that could cause the SHA-ancestry check to false-negative; reordered
cache-warming to occur before the atomic switch (so a cache-build failure
no longer leaves production live on a half-optimized release with no
recovery path); added the missing `php artisan storage:link` call (Gate-2
§A-3 requires `public/storage` to resolve through shared storage — it did
not); the queue canary's own success is now required (not just the HTTP
readiness check) before `health_verified` is ever reported; corrected the
host-provisioning runbook's overclaim about automated production smoke
coverage; reclassified 6 genuinely schema-breaking migrations from
`expand` to `breaking`; corrected the isolation-evidence test's docblock
to stop overclaiming RBAC coverage; added a required
`breaking_migration_backup_confirmed` input that fails the deploy closed
if a breaking migration is requested without confirming a fresh backup was
taken.

**6 Minor findings** — deferred, non-blocking (see the SDD ledger at
`.superpowers/sdd/2026-09-03-gap-049-production-deployment-implementation/progress.md`
for the full list; none affect correctness or the binding design
contracts).

## C2 — Escalated security risk requiring Owner attention before any real deployment (NOT fixed in this branch)

**This is not a GAP-049 defect — it is a pre-existing, untouched-by-this-branch
issue in `app/Http/Controllers/AuthController.php` that this branch's
`production:bootstrap` command inadvertently arms.**

`AuthController.php` (lines ~48-93, unchanged by any commit in this
branch) contains a hardcoded fallback: if `Auth::attempt()` fails, it
compares submitted credentials against a literal hardcoded map including
`superadmin@zena.com` / `password123`, and on a match, **creates the user
via `User::firstOrCreate`**, attaches the named role if a `Role` row with
that name already exists, and logs the user in. There is no
`app()->environment()` guard, and the route is mounted unconditionally in
`routes/web.php`.

The cross-task interaction: `ProductionBootstrapCommand::handle()`
(Task 6 of this plan) runs `Role::firstOrCreate(['name' => 'super_admin'],
['scope' => 'system'])` as part of legitimate first-tenant bootstrap.
Before bootstrap, the backdoor grants a role-less account. **After
bootstrap, the `super_admin` row exists, so the same backdoor grants
`super_admin`** — and `RoleBasedAccessControlMiddleware::checkAccess()`
opens with `if ($user->isSuperAdmin()) { return true; }`, bypassing every
permission check in the application.

**Practical consequence: deploying this branch and running
`production:bootstrap` (both of which this Work ID's Gate 3 evidence
otherwise supports) would create a production system where anyone who
knows this hardcoded credential obtains full super-admin access.** This
directly contradicts Gate-2 design §3a's own binding rule ("no fixed or
default password is ever used for any account") — the rule was written
for this plan's OWN bootstrap flow, but the pre-existing backdoor violates
its spirit for the system as a whole.

**Disposition:** per this Work ID's explicit scope boundary (no
CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line or other business
code may be modified) and the Design Dependency Preflight convention for
discoveries that require touching code outside a Work ID's authorized
surface, this is **not fixed in this branch**. It is recorded here as a
blocking pre-deployment risk. **Recommendation: file a separate Work ID
to remove or environment-gate this backdoor before `production:bootstrap`
is ever run against a real production database**, and treat that Work ID
as a hard prerequisite for GAP-049's eventual first real deployment,
independent of this Gate-3 packet's own disposition.

## I2 — Owner decision point: no automated rollback/maintenance-mode branch on post-cutover health failure

Gate-2 design A-2 specifies that on post-cutover health-check failure, the
system should execute a classification-aware code rollback (if migrations
were expand-only) or enter/remain in maintenance mode (if migrations were
breaking) — never simply leave a failed release serving traffic with no
recovery action. **`production.yml` does not implement this branch**: the
readiness-check step exits 1 and the job stops; `scripts/deploy/rollback.sh`
exists (Task 4) and is fully tested standalone, but is never invoked from
anywhere in the production workflow itself. Consequently, of the six
designed deployment states, `rolled_back` is currently unreachable from an
automatic path — it can only be produced by a human operator manually
running `rollback.sh` against an explicit prior release SHA after
diagnosing a failure.

This was surfaced by the final whole-branch review as a genuine gap
between the approved binding design and the implementation. It is
presented here as an explicit decision point rather than silently
implemented (which would require a nontrivial design choice about exactly
how maintenance-mode entry should be automated and is not itself specified
at that level of detail in the approved Gate-2 design) or silently omitted
(which would misrepresent this packet's completeness). **Owner options:**
(a) authorize a follow-up task to implement the classification-aware
automatic rollback/maintenance branch before first real deployment, or
(b) ratify that recovery is manual-only for the pilot phase (a human
operator runs `rollback.sh`/`artisan down` per
`docs/runbooks/gap-049-migration-safety.md` after diagnosing a failure),
which is a legitimate, if less automated, choice for a first controlled
deployment.

## What this packet authorizes now

Repository-side implementation is proposed for Owner technical review via
a Draft PR. **No production deployment, secret configuration, host
provisioning, or production database mutation has occurred, or is
authorized by this packet.**

## Proven in repository / disposable environment (this packet's evidence basis)

- Workflow/static contracts: single live production entry point (test-verified
  regression guard), no `git pull origin main`, no automatic `migrate:rollback`,
  explicit-target-only rollback, `workflow_dispatch`-only trigger with
  required SHA, SHA-ancestry verification, concurrency serialization.
- Release-tooling tests: shared-link, atomic switch (with the true
  atomicity distinction proven and fixed), explicit-target rollback,
  cleanup safety (`shared/` never touched), first-deployment-without-existing-`current`
  support.
- Migration-safety contract: expand accepted, breaking-without-flag
  rejected, breaking-with-flag-but-no-maintenance-mode rejected, manifest
  completeness (209/209 real migrations classified, no gaps).
- Readiness endpoint behavior: DB/cache/storage failure → 503, healthy →
  200, no diagnostic leakage (all test-verified).
- Queue canary: real async-worker completion proven via disposable drill
  (3/3 after the race-condition fix); sync-connection cannot produce a
  false-green (test-verified).
- Bootstrap safety: real tenant/admin creation, no `DatabaseSeeder`, no
  fixed password, idempotent fail-closed (all test-verified).
- Two-tenant isolation: negative read/write/list proven against real,
  live tenant-scoping code (test-verified, final-review-confirmed the
  403/404s are genuinely tenant-boundary-caused).
- Backup/restore: real MySQL 8.0 disposable-environment restore drill,
  byte-identical DB row and file content proven, checksums verified
  (not skipped/overridden).

## NOT yet proven in production (external Owner inputs, per Gate-2 §5)

Real VPS provisioning; real domain/DNS/TLS; real GitHub Environment
approval configuration (required reviewers vs. the authorized-manual-dispatch
fallback — A-1 specifies both are acceptable, but which is actually
configured on this repository's `production` Environment is external to
this codebase and not verifiable from a local worktree); real production
secret installation (`PRODUCTION_HOST`, `PRODUCTION_USER`,
`PRODUCTION_SSH_KEY`, `PRODUCTION_URL`, `PRODUCTION_HOST_KEY_FINGERPRINT`);
real off-host production backup destination; real production database
contents; real public URL; real production deploy of any kind. None of
these are claimed as complete by this packet merely because scripts,
tests, or runbooks exist for them.

## Explicit statements

- **NO PRODUCTION DEPLOYMENT OCCURRED.**
- **NO PRODUCTION SECRET WAS CONFIGURED.**
- **NO PRODUCTION HOST WAS PROVISIONED.**
- **NO PRODUCTION DATABASE WAS MUTATED.**

## Decision Needed

Owner chọn một trong: **Approve** (repository-side implementation
technically acceptable; C2 escalation and I2 decision point both
acknowledged and their disposition ratified as stated above) / **Request
correction** (specific changes required before re-presentation) / **Defer**
(hold pending further external input, e.g. resolution of C2's separate
Work ID first).

This packet does **not** ask the Owner to approve any real secret value,
real host, real domain, or an actual production deployment — only whether
the repository-side implementation and disposable-environment evidence
are technically acceptable, with C2 and I2 explicitly surfaced for
Owner-level risk acknowledgment rather than resolved unilaterally by this
session.
