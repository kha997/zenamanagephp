# 106 — OWN-2026-024: SPLIT-PERCENT repair and Symfony advisories (record of commits and CI)

Work ID: OWN-2026-024. PR #422 (Draft). **Gate 3 NOT approved. MERGE_NO_GO.** Not merged, not deployed. No staging or production
access. `production:rbac` and production H0 were not run.

This report records facts only. It approves nothing.

## 1. Commit record (branch `integration/uat-recovery-s1-s4d`)

These SHAs were not yet written in the repository docs.

| Commit | Change | Owner authorization (in chat) |
|---|---|---|
| `454545b41` | fix(security): enforce explicit RBAC and platform isolation (H1–H4, one commit) | Commit plan C; controlled push |
| `5ef16fc30` | test: H4 documents-simple test independent of debug routes (DBG-1 fixture fix) | "Approve both" |
| `0894be192` | ci: full UAT Recovery suite on disposable MySQL 8 (`recovery-mysql`, fail-closed) | "Approve both" |
| `c68604330` | test(recovery): prevent transactional backup deadlock | "THREE_COMMITS_PUSH_APPROVED" |
| `eccc1ea2d` | ci(security): preserve Trivy vulnerability gate | "THREE_COMMITS_PUSH_APPROVED" |
| `7ffdf9048` | test(routes): guard debug API route collisions (DBG-1 guard; DBG-1 itself UNRESOLVED) | "THREE_COMMITS_PUSH_APPROVED" |
| `69bd6799b` | **fix(tasks): repair assignment percentage persistence** | "SECURITY_AND_ASSIGNMENT_REPAIR_LOCAL_APPROVED"; push: "Approve push" |
| `38f51e3df` | **fix(deps): resolve Symfony security advisories** (`composer.lock` only) | same as above |

PR head: `38f51e3df`.

## 2. SPLIT-PERCENT (`69bd6799b`)

### Root cause

No migration ever created `task_assignments.split_percent`.
- **MySQL:** every assignment write and every SUM failed with `SQLSTATE[42S22] 1054`. These were the 2 known `UatRecovery04b` failures.
- **SQLite:** the unknown double-quoted identifier was read as a string literal, so the SUM was always 0. The 100 % rule therefore never held.
- **Mass assignment:** the App model also dropped the share silently. `split_percent` and `assigned_by` were not fillable.

### Other defects on the same write paths

- The Src model had no ULID generation. On MySQL the insert failed with error 1364; on SQLite it stored a NULL primary key.
- v1 rows were written with a NULL `tenant_id`.
- The 100 % and duplicate pre-checks ran in FormRequests, before authorization. This leaked task state to unauthorized actors.
- `POST api/v1/tasks` with `assignments` returned 500 **after committing**, for two reasons:
  - Src `UserResource` called a nonexistent `assignedTasks()` relation;
  - the Src model had no `assigned_at` cast.

### Fix

- **Migration `2026_11_26_100000`:** expand-only. A nullable `DECIMAL(5,2)` column with no default and no backfill, classified `expand`.
- **Historical rows:** they stay NULL. They are neither counted nor rewritten.
- **`App\Services\TaskAssignmentShares`:** one rule for the dashboard and v1 assignment surfaces and for v1 task create/update.
  - Every check-then-write runs inside a `FOR UPDATE` lock on the task row.
  - The lock order is always the task row first, then assignment rows.
  - The rule: the recorded shares total at most 100 %, and a user is assigned to a task at most once.
- **Order of checks:** authorization (403), then tenant membership of the assignee (422), then share or duplicate errors (400). `task.assign` is not widened.
- **Compatibility:**
  - an omitted share defaults to 100;
  - responses are re-read from the database;
  - responses carry `split_percent` and the legacy `split_percentage` key with the stored value.
- **Tests:**
  - `UatRecovery08SplitPercentTest` (11 tests) covers persistence equal to the response, the 100 % limit, duplicates, update and unassign, PM authority, cross-tenant assignment, historical rows, v1 task creation, and a guard that every write path holds the lock.
  - `UatRecovery08bSplitPercentConcurrencyTest` runs a two-process race on real MySQL only (group `stress`, recorded in the skip baseline). Removing the lock makes it fail.
- **`scripts/ci/recovery-mysql-known-failures.txt`:** now empty.

## 3. Dependencies (`38f51e3df`)

`composer.json` is unchanged; `composer.lock` changes only the four packages below. All are patch releases on the same 7.4 line.

| Package | Before | After |
|---|---|---|
| `symfony/http-kernel` | v7.4.23 | v7.4.24 |
| `symfony/mailer` | v7.4.19 | v7.4.24 |
| `symfony/routing` | v7.4.23 | v7.4.24 |
| `symfony/http-foundation` | v7.4.23 | v7.4.24 (required by http-kernel 7.4.24) |

Advisories resolved:
- CVE-2026-133743 — inline fragments let untrusted forwarded headers change asset URLs;
- CVE-2026-133746 — UrlGenerator can still generate URLs to another host (incomplete earlier fix);
- CVE-2026-133754 — host placeholder values send generated URLs to another host;
- CVE-2026-133751 — Mailer `require_tls` bypassed by a man in the middle;
- CVE-2026-133755 — Mailer trusts replies injected right after STARTTLS.

`composer audit` finds no advisories; `composer validate` passes. No advisory was ignored, and the audit and Security CI are unchanged.

## 4. Verification

### Local, before commit

**Full Recovery on isolated official `mysql:8.0.46`** (`scripts/ci/recovery-mysql`):
- 233 listed, 233 executed, 0 skipped;
- 0 failed, 0 mapped to a baseline;
- 3240 assertions, exit 0.

**Migration on MySQL 8:**
- up, rollback, up again: all succeed, and a second migrate reports nothing to migrate;
- a pre-existing row is byte-identical after the migration (md5 compared), with `split_percent` NULL.

**MariaDB 10.4.28:** tests 08, 08b and 04b pass, 29/29 with 0 skipped.

**SQLite:**

| Suite | Result |
|---|---|
| Feature | 2315 tests, 0 failures |
| Api | 825 OK |
| Integration | 31/31 |
| Architecture | 26 OK |
| Security | OK |
| Unit | 960 tests, 5 failures |

The 5 Unit failures are the known `EvidenceFreshnessLiveCheckTest` cases. They are pre-existing and depend on the environment.

**Static checks:**
- PHPStan, SSOT `lint_tests`, the baseline guard, governance, gate-ordering, MySQL-claim, docs-lint and domain-ownership all pass.
- Code style: no new findings; the remaining findings were already present at HEAD.

**Independent review:** APPROVE. Its one finding introduced by the diff, a lock-order inversion in `TaskService::updateTaskAssignments`, was fixed before commit. All of the checks above were re-run on the final tree.

### CI at `38f51e3df`

- **37/37 checks SUCCESS.**
- `UAT Recovery suite (real MySQL 8)` (run 45855172807) reports:
  - MySQL server version `8.0.46`;
  - 233 listed, 233 executed, 0 not executed;
  - 0 failed or errored, 0 skipped;
  - OK (233 tests, 3243 assertions).
- `Security Vulnerability Scan` and `Dependency Vulnerability Scan` (run 45855172717): SUCCESS.
- `Feature Tests` and `browser-tests`: SUCCESS.

## 5. Open items (not decided, not fixed)

- **M4 — Owner gate (business):** what does a NULL (unrecorded) share mean?
  - Today, NULL rows do not count toward 100 %.
  - `app/Http/Controllers/Api/TaskController.php:854` and `app/Http/Controllers/Api/WorkTemplateController.php:618` still write NULL shares.
- **Unlocked legacy assignment writers.** Whether they are routed is unverified:
  - `app/Services/TaskAssignmentService`;
  - `app/Http/Controllers/TaskAssignmentController`;
  - `TaskController::assign*`.
- **No database-level UNIQUE(`task_id`, `user_id`).** The duplicate rule relies on the lock.
- **Pre-existing defects:**
  - `POST api/task-assignments` always returns 500 (`store()` lacks its `taskId` argument);
  - the status and role vocabulary (`pending`, `observer`) differs from the model constants;
  - dashboard `exists:` validation runs before authorization.
- **Carried over:**
  - DBG-1 unresolved;
  - `MaintenanceController::backupDatabase` dump has no `--single-transaction`;
  - the CI tag `mysql:8.0` is not digest-pinned;
  - RB-1;
  - the H4 gates G1–G3;
  - H0 on production before any deploy (Owner only);
  - the PR §L blockers (O-18 convergence, O-17j/k, `TaskPolicy::view`, governance packets, O-19).
