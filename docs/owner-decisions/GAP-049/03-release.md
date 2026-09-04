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
  owner_response_reference: "GAP-049 Gate 3 Owner Round 1 (relayed via coordinator session, reviewed head 52d66a095c053f0b41ce81983df0411e9b186b28 of PR #302, canonical base dfd936dbbd88400013488e0bb2e3bb21e126e535): 'GATE 3 ROUND 1 — CHANGES REQUESTED. TECHNICAL READINESS: BLOCKED. The implementation is substantially advanced, but it is NOT technically ready for Owner release review yet. CI is no longer pending. Exact-head CI has completed with failures.' Owner directed 8 correction items: (1) correct the Gate-3 packet truth immediately (this edit); (2) close the demo-login production backdoor in AuthController.php as a narrow GAP-049 security correction (explicitly authorized in-scope, no separate Work ID); (3) fix the new readiness route's security-contract failure via the repository's canonical infrastructure-health designation/allowlist mechanism, not by adding business auth; (4) fix all 7 branch-introduced PHPStan errors (ProductionBootstrapCommand.php static-call reporting, MigrationClassificationService.php array value types) with proper types, not suppression; (5) prove whether the MySQL invariant failure (ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource, expected E404.NOT_FOUND actual TENANT_INVALID) is pre-existing (test against canonical base dfd936db) or branch-induced before touching any tenant/RBAC/product behavior; (6) implement the approved Gate-2 A-2/A-5 post-cutover recovery contract (explicit-target automatic rollback on expand-migration readiness/queue-canary failure, maintenance-mode-only recovery on breaking-migration failure, no automatic migrate:rollback, first-deploy-with-no-prior-release handled without inventing a rollback target, failed readiness must never be reduced to deployed_unverified); (7) re-run full correction verification and a new final whole-branch review against the approved Gate-2 design, the two Owner binding clarifications, and this Round-1 directive; (8) refresh Gate-3 evidence only after code is frozen and every mandatory exact-head CI check is green, then re-present as awaiting_owner/ready and STOP for Round-2 review. Do not merge, deploy, configure production secrets, provision a production host, or mutate a production database.' | GAP-049 Gate 3 Owner CI Exception Ruling (relayed via coordinator session, Owner independently inspected the live PR state at head b08795a715396b953d766c305f1088f26def9f6f): 'Do NOT retry the MySQL parity job again merely to chase a green result. Do NOT fix the underlying pre-existing MySQL invariant defect under GAP-049. Do NOT open GAP-050 yet. Do NOT merge or deploy. At this head: PASS — Owner Governance Lint, Routes Guardrails, Code Quality & Security, CI/CD Pipeline, Button Test Suite, Staging Smoke. Automated Testing is FAILURE only because Zena RBAC/Tenant Invariants (MySQL parity) fails on ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource. All other jobs within Automated Testing pass, including Security Tests/PHPStan, Unit, Feature, Integration, API tests, performance jobs, real-MySQL concurrency jobs, and repo-hygiene guards. The MySQL parity failure has already been proven to reproduce identically on canonical base dfd936dbbd88400013488e0bb2e3bb21e126e535 using the same MySQL invariant invocation, with query-log evidence identifying the pre-existing transaction-nesting / RefreshDatabase race. GAP-049 Deployment tests do not participate in that job, and no tenant/RBAC/product correction is authorized under GAP-049 for this pre-existing defect. OWNER RULING: the previous requirement that every mandatory exact-head CI check must be green is hereby narrowed for this Gate-3 presentation. GAP-049 MAY be re-presented to Owner with exactly ONE documented CI exception (Zena RBAC/Tenant Invariants (MySQL parity)) provided: (1) the failure is identical in signature to the proven canonical-base failure; (2) no new GAP-049-attributable failure appears; (3) every other workflow/job relevant to GAP-049 remains green; (4) the exception evidence remains bound to the canonical-base reproduction; (5) the packet and PR do NOT describe the overall CI suite as all green; (6) no tenant/RBAC/product behavior is modified merely to force this legacy check green. This exception applies ONLY to GAP-049 Gate-3 evaluation, not a general waiver for future red CI. Re-present Gate 3 truthfully: gate_status: awaiting_owner, technical_readiness.value: ready, owner_decision.value: none, decision_requested: approve_or_correction_or_defer, preserving the complete permanent Round-1 CHANGES REQUESTED history, adding a clearly titled permanent section Owner-authorized CI exception for Gate-3 Round 2 recording canonical base SHA, exact implementation subject SHA, current/final PR head, exact failing workflow/job/test, expected vs actual error code, 4/4 failed reruns this session, exact base reproduction evidence, MySQL query-log root-cause evidence, proof GAP-049 deployment tests do not execute in this job, proof no tenant/RBAC/product code was changed for this exception, exact list of all other successful workflows/jobs, and a statement that this exception does NOT mean the red check passed. Refresh the stale PR body (AuthController backdoor and post-cutover rollback are no longer accurately described as unfixed). Do not rerun the MySQL parity job a fifth time unless implementation code changes for another legitimate reason or Owner explicitly requests an additional diagnostic experiment — four identical failures plus canonical-base reproduction are sufficient evidence. Record as a recommended immediate follow-up after GAP-049: GAP-050 — MySQL Invariant Transaction Isolation / CI Reliability, to be started in a NEW session after GAP-049 is closed/released, not in this session. Keep residual_risk_rating at least medium. Then STOP for Owner Gate-3 Round-2 decision. Do NOT merge, deploy, or self-approve.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-04T02:00:00Z"
  updated_at: "2026-09-05T05:00:00Z"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "GAP-049 implements the Owner-approved Gate-2 architecture (Candidate A — hardened, exact-SHA release-based SSH deployment) across 12 tasks: retirement of every competing production-deployment entry point (deploy.yml/deploy.sh deleted; ci-cd.yml's placeholder deploy job removed; automated-deployment.yml and release-management.yml production-reaching jobs disabled via if:false), a minimal non-diagnostic-leaking production readiness endpoint, an expand-vs-breaking migration classification contract enforced in code, immutable-release filesystem tooling with a true atomic current-symlink switch on GNU/Linux, a queue-worker liveness canary, a production-safe first-database bootstrap command, least-privilege backup/restore scripts with a proven disposable-environment restore drill, pre-release two-tenant negative-isolation evidence, three operational runbooks, and the hardened production.yml workflow itself as the sole live production entry point. All GAP-049-scoped tests pass (58/58, tests/Feature/Deployment/**). A first final whole-branch review found 2 Critical and 10 Important findings; all were fixed and re-reviewed clean, EXCEPT the AuthController demo-login backdoor (C2), which was deliberately left unfixed pending Owner authorization since it is pre-existing authentication code outside GAP-049's default scope. Owner Gate-3 Round 1 (see permanent history below) REJECTED the resulting awaiting_owner/ready presentation as premature (exact-head CI had completed with real failures) and directed 6 correction items: (1) correct the packet truth immediately; (2) close the AuthController backdoor as a narrow, explicitly-authorized in-scope correction (done, commit 5593d287 — see C2 below, now resolved); (3) allowlist the readiness endpoint in the API security middleware gate rather than adding business auth (done, commit d017225f); (4) resolve all 7 branch-introduced PHPStan errors with proper types, zero suppressions, verified 0 errors full-repo (done, commit c96fe4bc); (5) prove the reported MySQL invariant CI failure's provenance before touching any tenant/RBAC code — proven pre-existing via two diagnostic investigations reproducing it identically on the canonical base commit using the exact CI invocation, with direct MySQL query-log evidence; no tenant/RBAC/product code touched, a genuinely unrelated latent DeployMigrateCommand --path-forwarding bug found during this investigation was also fixed (done, commit 73db452a); (6) implement the Gate-2 A-2/A-5 automatic post-cutover recovery contract (done, commit 456efd25 — see I2 below, now resolved). A SECOND final whole-branch review (run per item 7, checking the Round-1 corrections themselves) then caught a Critical defect in item 6's first implementation: the recovery step's if: condition omitted an explicit status-check function, so GitHub Actions' implicit success()-gating silently made the entire A-2/A-5 contract unreachable dead code despite all 7 of its own structural tests passing — fixed in commit c3ee1a27, with 2 new regression tests specifically guarding against this exact class of bug recurring; the packet body's own stale claims (that the AuthController backdoor and the recovery contract were still unfixed) were also corrected to match the actual code state, plus a genuinely unrelated latent DeployMigrateCommand --path-forwarding bug was fixed with real RED/GREEN evidence (commit dd6b0c86, b08795a7). ALL GAP-049-attributable technical gates pass. On exact head b08795a715396b953d766c305f1088f26def9f6f, CI ran to completion with exactly ONE red job: Zena RBAC/Tenant Invariants (MySQL parity), failing 4/4 times in this session (1 original run + 3 reruns) on the identical pre-existing test (ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource), already proven (before this CI run) to reproduce identically on canonical base dfd936dbbd88400013488e0bb2e3bb21e126e535 using the exact same CI invocation, with direct MySQL query-log evidence of a transaction-nesting/RefreshDatabase race — GAP-049's own Deployment test files never execute in that CI job at all. Every other GAP-049-relevant workflow/job passed: Owner Governance Lint, Routes Guardrails, Code Quality & Security, CI/CD Pipeline, Button Test Suite, Staging Smoke, and every other job within Automated Testing (Security Tests/PHPStan, Unit, Feature, Integration, API Tests Fast/Slow, Performance Tests, Document/RFI/GAP-048/Treasury real-MySQL concurrency jobs, Zena RBAC/Tenant Invariants (non-parity), browser-tests, Test Coverage Report, Repo Hygiene Guards, Trivy, Docker/Dependency/License/Security Vulnerability Scans). The Owner independently reviewed this exact live state and issued a narrow, GAP-049-Gate-3-only CI exception ruling authorizing re-presentation with this one documented, proven-pre-existing, unrelated exception — see 'Owner-authorized CI exception for Gate-3 Round 2' below for the complete record. This packet does NOT claim an all-green repository CI state. Zero actual production deployment, secret configuration, host provisioning, or production database mutation occurred anywhere in this branch's history."
technical_evidence:
  subject_sha: "b08795a715396b953d766c305f1088f26def9f6f"
  implementation_tree_digest: "76af4ab59e988e602e7b9c12d829e0363d2bedc127c7fcb201a103e3b454e22d"
  verified_pr_head_sha: "b08795a715396b953d766c305f1088f26def9f6f"
  verified_at: "2026-09-05T00:00:00Z"
owner_decision_binding:
  implementation_tree_digest: "76af4ab59e988e602e7b9c12d829e0363d2bedc127c7fcb201a103e3b454e22d"
  decision_recorded_at: "2026-09-05T00:00:00Z"
---

# GAP-049 — Production Deployment Hardening: Gate 3 Release Request

**Gate 3 packet status: `awaiting_owner` (Round 2).** Owner Gate-3 Round 1
**REJECTED** this packet's first `awaiting_owner`/`technical_readiness: ready`
presentation as **premature** — it was presented before exact-head CI had
finished, and exact-head CI (head `52d66a095c053f0b41ce81983df0411e9b186b28`)
subsequently completed with real failures. All 6 Round-1 correction items
are now complete (see "Owner Decision History — Round 1" and "Round-1
correction status" below), re-verified by a second final whole-branch
review that caught and fixed one further Critical defect. The packet is
re-presented against exact head `b08795a715396b953d766c305f1088f26def9f6f`
with every GAP-049-attributable CI check green and exactly one
Owner-authorized, proven-pre-existing, unrelated CI exception — see "Owner
Decision History — Round 1", "Round-1 correction status", and
"Owner-authorized CI exception for Gate-3 Round 2" below for the complete
record.

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate-3 Round 1 decision: CHANGES REQUESTED. TECHNICAL READINESS: BLOCKED.**
Reviewed exact PR head `52d66a095c053f0b41ce81983df0411e9b186b28` of PR #302
against canonical base `dfd936dbbd88400013488e0bb2e3bb21e126e535`. Full
verbatim directive preserved in this file's frontmatter
`decision_provenance.owner_response_reference` above. The Owner found the
implementation "substantially advanced" but explicitly NOT technically
ready: exact-head CI had completed with failures at the moment of the
prior `awaiting_owner`/`ready` presentation, which was therefore premature.

**8 correction items directed, all binding, addressed in this round (see
each item's own evidence section below once implemented):**
1. Correct the Gate-3 packet truth immediately (this edit) — `gate_status:
   changes_requested`, `technical_readiness.value: blocked`,
   `owner_decision.value: correction_requested`, `decision_requested: null`,
   this permanent Round-1 history section, prior engineering evidence
   preserved (not erased) below.
2. Close the demo-login production backdoor in `AuthController.php` —
   explicitly authorized as a **narrow GAP-049 security correction**, not a
   separate Work ID, because GAP-049's own `production:bootstrap` command is
   what turns the pre-existing demo-fallback defect into an exploitable
   privileged production authentication path.
3. Fix the new readiness route's security-contract failure via the
   repository's canonical infrastructure-health designation/allowlist
   mechanism — not by adding business authentication to the endpoint.
4. Fix all 7 branch-introduced PHPStan errors with proper types, not
   suppression.
5. Prove whether the MySQL invariant failure is pre-existing (test against
   canonical base first) or branch-induced, before touching any
   tenant/RBAC/product behavior.
6. Implement the approved Gate-2 A-2/A-5 post-cutover recovery contract
   (automatic explicit-target rollback for expand deployments, maintenance-only
   recovery for breaking deployments, no automatic `migrate:rollback`,
   first-deploy-with-no-prior-release handled safely, failed readiness never
   silently reduced to `deployed_unverified`).
7. Re-run full correction verification and a new final whole-branch review.
8. Refresh Gate-3 evidence only after code is frozen and every mandatory
   exact-head CI check is green, then re-present as `awaiting_owner`/`ready`
   and STOP for Round-2 review.

**This Round-1 record is preserved permanently and must not be removed by
any future revision.**

## Round-1 correction status (updated as each item completes)

1. **Complete.** Packet truth corrected (this section and the frontmatter
   fields above).
2. **Complete.** `app/Http/Controllers/AuthController.php`'s demo-login
   fallback wrapped in `if (app()->environment(['local', 'testing']))`,
   reusing the identical existing pattern from `routes/web.php`. RED→GREEN
   evidence: `tests/Feature/Security/AuthControllerDemoLoginProductionGuardTest.php`
   (commit `5593d287`).
3. **Complete.** `GET /api/v1/public/production/ready` added to
   `tests/Feature/Api/ApiSecurityMiddlewareGateTest.php`'s existing
   per-exact-URI allowlist (same mechanism as `/api/v1/public/health/*`).
   RED→GREEN evidence in-commit (`d017225f`).
4. **Complete.** `ProductionBootstrapCommand.php`/`MigrationClassificationService.php`
   routed Eloquent static calls through `Model::query()->...` (no Larastan
   in this repo's PHPStan config) and added array-shape PHPDoc annotations.
   Verified 0 PHPStan errors, both per-file and full-repo
   (`php -d memory_limit=2G vendor/bin/phpstan analyse`, 1155 files, `[OK] No errors`).
   Commit `c96fe4bc`.
5. **Complete — pre-existing, not branch-induced.** Two diagnostic
   investigations (reports retained locally at
   `.superpowers/sdd/2026-09-03-gap-049-production-deployment-implementation/mysql-invariant-{provenance,rootcause}-report.md`)
   proved the CI failure
   (`ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`,
   `TENANT_INVALID` vs `E404.NOT_FOUND`) is a pre-existing, intermittent
   transaction-nesting race condition (`RefreshDatabase` + nested
   `SAVEPOINT`-based `DB::transaction()` calls under real MySQL 8.0),
   reproduced identically on the canonical base commit
   `dfd936dbbd88400013488e0bb2e3bb21e126e535` using the **exact CI
   invocation** (`--group=zena-invariants -c phpunit.mysql.xml`) — proven
   via direct MySQL general-query-log evidence showing a bare `ROLLBACK`
   firing mid-test immediately before a `TenantIsolationMiddleware::handle()`
   `Tenant::find()` lookup for the tenant that same test had just inserted.
   None of GAP-049's own `tests/Feature/Deployment/*` files carry
   `@group zena-invariants`, so they never execute in this CI job at all —
   confirmed by `grep -rl "@group zena-invariants" tests/`. **No
   tenant/RBAC/product code was touched**, per the Owner's explicit ruling
   for this outcome. This belongs to a new, separately governed Work ID
   (likely descending from the same transaction-nesting family as
   GAP-040/GAP-044). A genuinely unrelated secondary latent bug was found
   during this investigation and fixed in-scope (small, safe, does not
   touch tenant/RBAC/product code): `DeployMigrateCommand::handle()` now
   forwards `--path` to its internal `migrate` call only when
   `--migrations-path` was explicitly overridden (testing only) — real
   production invocations are completely unaffected. RED→GREEN evidence
   in commit `73db452a`.

   **Raw evidence excerpt** (MySQL general query log, captured during a
   reproducing run on the impl branch, `RouteHygieneTest.php` +
   `tests/Feature/Zena` together under `--group=zena-invariants`):
   ```
   insert into `tenants` (...) values (..., '01M1P9S0SD99YC18C08PWY0E6X', 'Kozey, Larson and Mosciski', ...)
   insert into `users` (...) values (..., tenant_id = '01M1P9S0SD99YC18C08PWY0E6X', ...)
   select * from `users` where `email` = ? ...
   ...
   ROLLBACK                                                    <-- unexpected here
   select * from `tenants` where `tenants`.`id` = ? limit 1
   select * from `tenants` where `tenants`.`id` = '01M1P9S0SD99YC18C08PWY0E6X' limit 1
   ```
   The `SELECT ... FROM tenants WHERE id = '01M1P9S0SD99YC18C08PWY0E6X'`
   runs immediately after a bare `ROLLBACK` (not `ROLLBACK TO SAVEPOINT`)
   on the same connection, for the exact tenant ID that `ROLLBACK` had
   just undone. This query shape is an exact textual match for
   `TenantIsolationMiddleware::handle()`'s `Tenant::find($tenantId)` call,
   whose failure branch returns exactly the observed `404` +
   `TENANT_INVALID`. Consistent with a transaction-nesting bookkeeping
   desync between `RefreshDatabase`'s in-PHP nesting counter and MySQL's
   real transaction depth, most likely triggered by `SAVEPOINT trans2`
   reuse from nested `DB::transaction()` calls
   (`firstOrCreate`/`updateOrCreate`) inside per-test `setUp()` seeding —
   not traced to an exact PHP call site (would require `DB::listen()`
   instrumentation, out of scope for this diagnostic-only investigation).
   The complete investigation methodology, all command output, and the
   ruled-out alternative hypotheses remain in the two report files named
   above (retained in this worktree's gitignored SDD workspace, not
   committed to keep this Gate-3 packet focused and to avoid interacting
   with the OWN-2026-005 gate-ordering exemption's `docs/audits/**`
   boundary).
6. **Complete (with one Critical fix along the way — see item 7).**
   `production.yml` implements the Gate-2 A-2/A-5 automatic post-cutover
   recovery contract: explicit pre-cutover capture of the previous
   release, automatic explicit-target rollback for expand deployments
   (reported `rolled_back` only if a post-recovery readiness check also
   passes), maintenance-mode-only recovery for breaking deployments (no
   automatic code/schema rollback, dedicated Slack escalation), no
   invented rollback target for first-ever deployments, and the final
   state-computation step no longer reduces an explicitly-failed
   readiness check to `deployed_unverified`. Commit `456efd25` (first
   implementation) + `c3ee1a27` (Critical fix — see item 7).
7. **Complete.** Full correction verification: Deployment suite 58/58,
   AuthController regression 2/2, API security gate 1/1, PHPStan 0 errors
   full-repo (1155 files), workflow YAML valid (14/14 files), route guard
   OK, Owner Governance Lint clean, frontend build OK. A new final
   whole-branch review (dispatched specifically against the approved
   Gate-2 design, the two binding clarifications, and this Round-1
   directive) found item 6's first implementation had a **Critical**
   defect: the `recovery` and `post-recovery-readiness` steps' `if:`
   conditions omitted an explicit status-check function
   (`always()`/`failure()`/`cancelled()`), so GitHub Actions' implicit
   `success()`-gating silently skipped both steps in every scenario they
   exist to handle — the entire A-2/A-5 contract was reachable in text
   but dead at runtime, despite all 7 of its own structural regression
   tests passing (they only ever parsed the committed YAML, never
   executed the workflow). Fixed in commit `c3ee1a27`: `always()` added
   to both conditions, the recovery trigger broadened to also cover an
   `activate`-step failure after a successful atomic switch (a related
   Important gap the same review found), and 2 new regression tests added
   specifically to guard against this exact class of bug recurring
   (asserting the presence of an explicit status-check function). The
   same review also found the packet body (this file) still asserted the
   AuthController backdoor was unfixed and the recovery contract
   unreachable, after both had in fact been fixed — corrected in this
   revision (see the C2/I2 sections above, now marked resolved, and the
   `mandatory_technical_gate_summary` field above).
8. **Complete, under an Owner-authorized narrow exception.** Evidence
   refreshed against exact head `b08795a715396b953d766c305f1088f26def9f6f`
   (subject SHA and implementation-tree digest below). Every
   GAP-049-attributable check is green; exactly one repository-level,
   proven-pre-existing, unrelated check remains red. The Owner
   independently reviewed this live state and issued a narrow Gate-3-only
   exception — see "Owner-authorized CI exception for Gate-3 Round 2"
   immediately below — rather than requiring an indefinite rerun loop
   against a check this branch cannot make deterministic. Re-presented as
   `gate_status: awaiting_owner`, `technical_readiness.value: ready`,
   `owner_decision.value: none`.

---

## Owner-authorized CI exception for Gate-3 Round 2 (permanent record, never erased)

**This section documents a narrow, Owner-issued exception — it does not
claim the exception check passed, and it does not waive future CI
requirements for any other Work ID.**

### The facts

- **Canonical base SHA:** `dfd936dbbd88400013488e0bb2e3bb21e126e535`
- **Exact implementation subject SHA (this packet's evidence basis):**
  `b08795a715396b953d766c305f1088f26def9f6f`
- **Current/final PR head (identical to subject SHA — no further code
  commits since):** `b08795a715396b953d766c305f1088f26def9f6f`
- **Exact failing workflow/job/test:**
  - Workflow: `Automated Testing`
  - Job: `Zena RBAC/Tenant Invariants (MySQL parity)`
  - Test: `Tests\Feature\Zena\ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
- **Expected vs actual:** expected error code `E404.NOT_FOUND`, actual
  error code `TENANT_INVALID` (both HTTP status 404 — only the JSON error
  code differs). `PHPUnit\Framework\ExpectationFailedException`, "Failed
  asserting that two strings are identical."
- **4/4 failed attempts this session, all the identical test/signature,
  independently confirmed from job logs for each:**
  1. Original CI run on push, job `101069287304` — failed.
  2. Rerun 1, job `101075733442` — failed (identical test/exception,
     confirmed via `gh api .../logs`).
  3. Rerun 2, job `101076966454` — failed (identical test/exception,
     confirmed via `gh api .../logs`).
  4. Rerun 3, job `101078011232` — failed (identical test/exception,
     confirmed via `gh api .../logs`).
  No rerun beyond the fourth was performed, per Owner instruction — "four
  identical failures plus canonical-base reproduction are sufficient
  evidence, further retrying would not increase confidence."
- **Exact base-commit reproduction evidence:** documented above under
  Round-1 correction item 5 — two independent diagnostic investigations
  (reports retained locally, key evidence embedded in this packet).
  Investigation Round 2 ran the **exact CI invocation**
  (`--group=zena-invariants -c phpunit.mysql.xml`, real MySQL 8.0) against
  the canonical base commit `dfd936dbbd88400013488e0bb2e3bb21e126e535` in
  an isolated worktree and reproduced the identical failure
  (`test_document_show_returns_not_found_for_scoped_cross_tenant_resource`,
  identical `E404.NOT_FOUND`/`TENANT_INVALID` mismatch) there too — proving
  this is not branch-induced.
- **MySQL query-log root-cause evidence:** embedded above under Round-1
  correction item 5 — a bare `ROLLBACK` (not `ROLLBACK TO SAVEPOINT`) fires
  mid-test on the same connection, immediately before
  `TenantIsolationMiddleware::handle()`'s `Tenant::find($tenantId)` lookup
  for the tenant that same test had just inserted — consistent with a
  transaction-nesting bookkeeping desync between `RefreshDatabase`'s
  in-PHP nesting counter and MySQL's real transaction depth, most likely
  triggered by `SAVEPOINT trans2` reuse from nested `DB::transaction()`
  calls in per-test `setUp()` seeding.
- **Proof GAP-049 deployment tests do not execute in this job:**
  `grep -rl "@group zena-invariants" tests/` shows the annotation exists
  only on `tests/Feature/RouteHygieneTest.php` and `tests/Feature/Zena/*`
  (15 files) — **none** of `tests/Feature/Deployment/*.php` (GAP-049's own
  test files) carry `@group zena-invariants`, so none of them ever execute
  in the `zena-invariants-mysql` / `Zena RBAC/Tenant Invariants (MySQL
  parity)` job.
- **Proof no tenant/RBAC/product code was changed for this exception:**
  `git diff --name-status dfd936db..b08795a7` contains zero files under
  `app/Models/`, `app/Http/Controllers/` (other than the narrowly
  Owner-authorized `AuthController.php` login-fallback guard, item 2),
  `app/Http/Middleware/` (tenant/RBAC middleware unchanged), or
  `tests/Feature/Zena/`. `ZenaApiContractPhase2InvariantTest.php` itself
  is byte-for-byte unchanged between base and this head.
- **Exact list of all other successful workflows/jobs on this exact
  head** (from `gh pr checks 302`, all with a `pass` conclusion):
  Owner Governance Lint; Routes Guardrails (`test-routes-guardrails`);
  Code Quality Analysis; `code-quality`; CI/CD Pipeline (`test`); Button
  Test Suite (`button-inventory-check`, `feature-tests`); Staging Smoke
  (`staging-smoke`); API Tests (Fast); API Tests (Slow); Dependency
  Vulnerability Scan; Docker Security Scan; Document Workflow Concurrency
  (real MySQL); Feature Tests; GAP-048 Service-Line Concurrency (real
  MySQL); Integration Tests; License Compliance Scan; Performance Tests
  (`DashboardPerformanceTest.php`, `PerformanceMonitoringTest.php`); RFI
  Escalation Concurrency (real MySQL); Repo Hygiene Guards; Security
  Tests; Security Vulnerability Scan; Test Coverage Report / `coverage-report`
  / `quality-gate`; Treasury Native CHECK Constraints (real MySQL); Trivy;
  Unit Tests; `Zena RBAC/Tenant Invariants` (the non-parity variant of
  this same suite — passes); `browser-tests`; `security-tests`.
- **Statement that this exception does NOT mean the red check passed:**
  `Zena RBAC/Tenant Invariants (MySQL parity)` is, and remains, RED on
  this exact head. It did not pass on any of the 4 attempts made in this
  session. This packet does not claim otherwise, does not claim an
  all-green repository CI state, and does not mark this specific check as
  resolved.

### The ruling

**Owner independently inspected the live PR state at head
`b08795a715396b953d766c305f1088f26def9f6f`** and issued the following
narrow ruling (full verbatim text preserved in this file's frontmatter
`decision_provenance.owner_response_reference` above): the prior
requirement that every mandatory exact-head CI check must be green is
narrowed, **for this Gate-3 presentation only**, to permit re-presentation
with exactly the one documented exception above, conditioned on: (1) the
failure signature matching the proven canonical-base reproduction
(confirmed above); (2) no new GAP-049-attributable failure appearing
(confirmed — the only red job is this pre-existing one); (3) every other
GAP-049-relevant workflow/job remaining green (confirmed, listed above);
(4) the exception evidence remaining bound to the canonical-base
reproduction (this section); (5) this packet not describing the overall
CI suite as "all green" (it does not — see the explicit statement above);
(6) no tenant/RBAC/product behavior being modified merely to force this
legacy check green (confirmed — zero such changes made).

**This exception applies only to GAP-049 Gate-3 evaluation. It is not a
general waiver for future red CI on this or any other Work ID.**

### Recommended immediate follow-up (not started in this session)

**GAP-050 — MySQL Invariant Transaction Isolation / CI Reliability.**
Purpose: eliminate the `RefreshDatabase`/nested-transaction race under
genuine MySQL; restore deterministic `Zena RBAC/Tenant Invariants (MySQL
parity)`; ensure test isolation truthfulness; do not change the intended
`E404`/`TENANT_INVALID` semantics merely to satisfy the test. Per Owner
instruction, GAP-050 is **not** started in this session — it requires a
new session after GAP-049 is closed/released.

### Addendum (2026-09-05): Owner Governance Lint itself now shows red on this exact head — expected consequence of this exception, not a new defect

The `gate_status: awaiting_owner` transition made in this same commit
activates `scripts/ci/check-evidence-freshness.sh`'s live check (it only
runs once a packet is actively claiming readiness). That script counts
**any** non-`pass`/`skipping` check on the current PR head as blocking,
with **no exception carve-out** for the Owner-authorized MySQL-parity
exception recorded above — the script was never updated to know about
this ruling. Consequence: while `gate_status: awaiting_owner` and `Zena
RBAC/Tenant Invariants (MySQL parity)` remains red (by Owner instruction,
it must), the `Owner Governance Lint` job **will itself always report
failure** on this PR, for as long as this packet is presented this way.
This is a structural gap in the enforcement tooling, not a new
GAP-049-attributable regression, and no fix to it is authorized in this
session (would be an unreviewed change to shared CI governance tooling
outside GAP-049's approved scope).

Evidence distinguishing this from a mere timing artifact: at the moment
this packet's `gate_status` first flipped to `awaiting_owner` (head
`797d9319e4db1503c9a6d833d55528f6a853e3e3`, workflow run `33920489625`),
the freshness check's own 300s poll reported **2** non-green checks
(`::error::...2 other check(s)...not green...`) — at that instant,
`browser-tests`/`coverage-report`/`quality-gate` (the `Button Test Suite`
chain) had not yet completed (they finished at 21:56:xx–21:56:52Z, after
the poll's 21:50:07Z timeout). A single confirmatory rerun of only the
`Owner Governance Lint` job (same head, no code or evidence change,
`gh run rerun 33920489625 --failed`, job `101186840478`,
21:58:49Z–22:04:38Z) — **not** a rerun of the MySQL-parity job itself,
which Owner instructed not to retry — now reports exactly **1** non-green
check: `::error::...1 other check(s)...not green...`. `gh pr checks 302`
at this point confirms exactly two red checks and no others: `Owner
Governance Lint` (`fail`) and `Zena RBAC/Tenant Invariants (MySQL
parity)` (`fail`); every other check is `pass`. This proves the original
2-check failure was a real, transient scheduling race (now resolved to
1), and that the remaining 1 is the permanent, expected, Owner-ruled
exception surfacing through a check that has no logic to distinguish it
from an ordinary failure — it is not a new independent defect and no
further reruns of either job are planned in this session.

**Practical implication for Owner Round-2 review:** the GitHub PR checks
UI for #302 will show `Owner Governance Lint` as a second red check
alongside the MySQL-parity exception, not the single-red-check state this
packet's body above describes it as when the digest was last refreshed.
The underlying technical evidence, implementation-tree digest, and code
state are unchanged (this addendum and the PR-body correction below are
the only changes since `subject_sha` `b08795a715396b953d766c305f1088f26def9f6f`,
and both are excluded from the implementation-tree digest by the digest
function's own design, which excludes only this file). If `Owner
Governance Lint` is a required/blocking status check for merge on this
repository, resolving that will require either an explicit Owner
instruction on how to proceed (e.g., an administrative merge override,
or authorizing a follow-up correction to
`scripts/ci/check-evidence-freshness.sh` to implement a real exception
carve-out) — neither of which this session takes unilaterally.

---

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
| GAP-049-scoped suite | `php vendor/bin/phpunit --filter=Deployment` | **48/48 PASS** at initial Task 11 verification, 120 assertions; **58/58 PASS**, 161 assertions after all Round-1 corrections and the second final-review fix wave (10 additional regression tests added across the DeployMigrateCommand secondary fix and the recovery-contract Critical-defect fix — see the corresponding correction items above for detail) |
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

## C2 — Security risk originally escalated to the Owner: RESOLVED by Owner Round-1 correction item 2

**Original finding (first final whole-branch review, before Owner Round 1):**
`app/Http/Controllers/AuthController.php`'s `login()` method (pre-existing,
untouched by GAP-049 until the fix below) contained a hardcoded demo-user
fallback: if `Auth::attempt()` failed, it compared submitted credentials
against a literal map including `superadmin@zena.com` / `password123`, and
on a match created the user via `User::firstOrCreate`, attached the named
role if a matching `Role` row existed, and logged the user in — with **no
controller-level `app()->environment()` guard**. This branch's own
`production:bootstrap` command (§ Task 6) creates a real `super_admin`
role as part of legitimate first-tenant bootstrap, which meant the
pre-existing demo fallback would grant full super-admin access to anyone
who knew the hardcoded credential, the moment a real production database
had been bootstrapped — directly against the spirit of Gate-2 design
§3a's binding rule ("no fixed or default password is ever used for any
account").

**Owner Round-1 directive (item 2):** explicitly authorized this as a
**narrow, in-scope GAP-049 security correction** — not a separate Work
ID — because GAP-049's own bootstrap command is what turns the
pre-existing defect into a live production risk.

**Fix applied (commit `5593d287`):** the entire demo-user fallback block
in `AuthController::login()` is now wrapped in
`if (app()->environment(['local', 'testing']))` — the identical guard
pattern already used in `routes/web.php` for the same purpose, not a new
configurable toggle. When the guard is false (production, staging, or any
other environment), execution falls straight through to the existing
rate-limited generic-failure response, identical to any other
wrong-credential attempt. RED→GREEN evidence:
`tests/Feature/Security/AuthControllerDemoLoginProductionGuardTest.php` —
with the environment forced to `production` at request time, a real
`POST /login` with the known demo credentials no longer authenticates, no
`users` row is created, and the `super_admin` role gains zero attached
users; a paired test confirms the intentionally-preserved `testing`/`local`
behavior still works.

**Correction to the original finding's own wording:** the original
finding also stated "the route is mounted unconditionally in
`routes/web.php`" — this was inaccurate. `routes/web.php` already
registered `POST /login → AuthController::login` only inside the same
`if (app()->environment(['local', 'testing']))` block (this was pre-existing,
not something that changed), meaning production exposure via the web
route was narrower than originally described. The controller-level fix
above is still the correct, necessary defense-in-depth measure — it is
what makes the vulnerability provable/testable at all under PHPUnit
(routes are registered once at process boot under a fixed `APP_ENV`, so
only a request-time environment override can exercise the guard in an
automated test), and it protects any other current or future code path
that might reach `AuthController::login()` directly.

**Status: resolved.** No further action needed on this item before
re-presentation.

## I2 — Owner decision point on automated recovery: RESOLVED by Owner Round-1 correction item 6 (+ a critical fix caught in Round-1's own review)

**Original finding (first final whole-branch review, before Owner Round
1):** `production.yml` implemented no automated rollback/maintenance-mode
branch on post-cutover health-check failure — a failed release would keep
serving traffic with no recovery action, and `rolled_back` was
unreachable as an automatic state.

**Owner Round-1 directive (item 6):** implement the full Gate-2 A-2/A-5
recovery contract — explicit-target automatic rollback for expand
deployments, maintenance-mode-only recovery for breaking deployments
(never automatic schema rollback), no invented rollback target for
first-ever deployments, and a final state-computation step that never
reduces an explicitly-failed readiness check to `deployed_unverified`.

**Fix applied (commit `456efd25`):** implemented exactly this. A second,
independent final whole-branch review (run per Owner Round-1 item 7, to
check this correction itself) then caught a **Critical** defect in that
first implementation: the recovery step's `if:` condition omitted an
explicit status-check function, so GitHub Actions' implicit `success()`
gating silently skipped the step in every scenario it was written to
handle — the entire A-2/A-5 contract was reachable in text but dead at
runtime, despite all 7 of its own structural regression tests passing
(they only ever parsed the committed YAML, never executed the workflow).
**Fixed in commit `c3ee1a27`**: prefixed `always()` to both the
`recovery` and `post-recovery-readiness` steps' `if:` conditions, and
broadened the recovery trigger to also cover the case where the
`activate` step itself fails after a successful atomic switch (e.g. a
post-switch service-restart failure) — previously excluded entirely. Two
new regression tests were added specifically to prevent this exact class
of bug from silently regressing again, including one asserting the
presence of an explicit status-check function on both steps.

**Status: resolved.** `rolled_back` is now a genuinely reachable
automatic state; breaking-migration failures correctly retain maintenance
mode with a dedicated Slack escalation rather than being silently
absorbed into a generic `failed`; no automatic schema rollback exists
anywhere in the workflow.

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

## Decision Needed — Round 2

All 6 Owner Round-1 correction items are complete (see "Round-1
correction status" above), re-verified by a second final whole-branch
review, and re-verification found and fixed one further Critical defect
(the recovery-contract status-check gating bug — see item 7 above). The
Gate-3 packet has been refreshed against exact head
`b08795a715396b953d766c305f1088f26def9f6f`. CI on that exact head is green
for every GAP-049-attributable check, with exactly one Owner-authorized,
proven-pre-existing, unrelated exception (`Zena RBAC/Tenant Invariants
(MySQL parity)` — see "Owner-authorized CI exception for Gate-3 Round 2"
above).

Owner chọn một trong: **Approve** (repository-side implementation
technically acceptable for release; the documented CI exception and the
recommended GAP-050 follow-up both acknowledged and ratified as stated
above) / **Request correction** (specific changes required before
re-presentation) / **Defer** (hold pending further external input).

This packet does **not** ask the Owner to approve any real secret value,
real host, real domain, or an actual production deployment — only whether
the repository-side implementation and disposable-environment evidence
are technically acceptable for release, with the one documented CI
exception explicitly surfaced for Owner-level acknowledgment rather than
silently absorbed into an "all green" claim.
