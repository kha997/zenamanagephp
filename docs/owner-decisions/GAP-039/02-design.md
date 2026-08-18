---
work_id: GAP-039
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: null
  plan: null
  branch: docs/GAP-039-mysql-fk-testing-integrity
  pr: "https://github.com/kha997/zenamanagephp/pull/266"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-18T23:37:00+07:00"
  updated_at: "2026-08-18T23:37:00+07:00"
generated_by: agent
---

## Owner Summary
Gate 2 design for GAP-039's quality/testing contract: which CI jobs must genuinely run on MySQL, how CI proves that instead of just claiming it, and how to make the FK/unique regression coverage actually reachable. Recommendation: a canonical fail-closed MySQL entrypoint (Option B) used only by the specific suites that genuinely need MySQL production parity, with every other job honestly relabeled/kept on SQLite (Option C) — not "make everything run MySQL." This is design/research only; no code, workflow, or test changes are made or authorized by this document.

## 0. Scope guardrails (carried from Gate 1, restated)
This document is engineering design and evidence only. It authorizes nothing beyond producing this design for Owner review. No workflow file, `tests/bootstrap.php`, PHPUnit configuration, test code, or application/production code has been changed to produce it. No implementation plan exists yet. Gate 3 has not started. PR #266 remains Draft. GAP-037/GAP-038 are untouched.

## 1. Correction to a Gate 1 open question (evidence-level, does not reopen Gate 1's decision)
Gate 1 flagged `button-tests.yml`'s `browser-tests` (Dusk) job as a possible split-brain (web server vs. test-assertion process on different databases) but explicitly did **not** assert this as observed — it was recorded as "requires reproduction." This Gate 2 pass reproduced it by reading `vendor/laravel/dusk/src/Console/DuskCommand.php`:

- `php artisan dusk` does **not** use `phpunit.xml` (the file with `bootstrap="tests/bootstrap.php"` and `<env name="DB_CONNECTION" value="sqlite"/>`). It uses `phpunit.dusk.xml`/`phpunit.dusk.xml.dist`, and since neither is committed in this repo, `DuskCommand::writeConfiguration()` **auto-generates one from Dusk's own stub** (`vendor/laravel/dusk/stubs/phpunit.xml`) at run time and deletes it afterward. That stub has **no `bootstrap` attribute and no `<php><env>` block at all.**
- Consequence: `tests/bootstrap.php` — the file that forces `DB_CONNECTION=sqlite` for every other job in this repo — **never executes** for any `tests/Browser/**` (Dusk) test, in any job, regardless of `ZENA_INVARIANTS_DB`. Dusk tests inherit whatever `.env` is on disk, unmediated.
- In `button-tests.yml`'s `browser-tests` job specifically: the "Generate application key" step does `cp .env.testing .env` — and `.env.testing` was built with `DB_CONNECTION=mysql`/real host/port/db/user/pass. That `.env` is what both the `php -S` web server (started later, also bypassing `tests/bootstrap.php` since it's not a PHPUnit process) and the `php artisan dusk` process read.
- **Corrected finding: this is not a split-brain.** Both the web server and the Dusk/PHPUnit assertion process read the same `.env` and genuinely connect to the same MySQL instance. `browser-tests` should be reclassified from "affected" to a **4th genuinely-MySQL execution** (alongside `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`) — it just got there by accident of how `artisan dusk` resolves its own PHPUnit config, not by any deliberate `ZENA_INVARIANTS_DB` gating. Confirmed empirically consistent with CI history: `gh run view` on the most recent `button-tests.yml` run shows `browser-tests` completed successfully in ~17.5 minutes with real Chrome + a real MySQL round trip per request — behavior that would not be internally consistent if the two processes disagreed on which database held the seeded users/tenants `loginAs()` and the visited pages depend on.
- This revises the Gate 1 count downward by one for "affected, silently-SQLite" purposes and upward by one for "genuinely MySQL, but *accidentally* so, not verified": **15 job definitions / 16 executions are affected (down from 16/17); 4 executions are genuinely MySQL (up from 3), one of which (`browser-tests`) has never had its MySQL-ness deliberately verified or protected against regression** — nothing stops a future change from adding a `phpunit.dusk.xml` file, or an `.env.dusk.testing` file, that silently reintroduces a real split-brain for Dusk specifically. This is folded into the acceptance requirements below (§6, guard G3).

This correction does not touch or reopen the Gate 1 packet — it refines a fact that Gate 1 explicitly left open, as Gate 2 design evidence.

## 2. Which suites genuinely need MySQL production parity (classification, not a per-test audit)

Per acceptance requirement, not all ~3,037 affected test methods should be assumed to need MySQL. Auditing each individually is not feasible or useful; the design instead classifies by **what correctness property a suite is actually checking**, using the repo's own existing precedent (the 3 pre-existing genuine-MySQL jobs) as the pattern to generalize, not invent from scratch:

| Correctness property being verified | Needs MySQL? | Why | Current examples |
|---|---|---|---|
| Real FK/unique/CHECK constraint enforcement at the DB layer | **Yes** | SQLite's constraint enforcement is a different engine with different edge cases (and, per Gate 1 §4, is not even reliably ON in this repo's SQLite path); the whole point is verifying what MySQL actually does. | `QualityAssuranceTest::test_database_constraints` (once split — see §5) |
| RBAC/tenant-isolation invariants expressed as real queries against real schema | **Yes** | Already the exact reason `zena-invariants-mysql` exists as a "parity" job distinct from the sqlite `zena-invariants`. | `--group=zena-invariants`, `TenantIsolationProjectsTest` (`routes-guardrails.yml`) |
| Concurrency/locking semantics (race conditions, row locks, transaction isolation) | **Yes** | SQLite's single-writer model cannot exercise real lock contention; this is precisely why `rfi-escalation-concurrency-mysql` and `document-workflow-concurrency-mysql` exist as dedicated jobs already. | `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql` |
| Browser/E2E flows exercising the full stack end-to-end | **Recommended yes** (already the case for Dusk, by accident — see §1) | End-to-end fidelity is the point of the suite; cheap to make deliberate since it already happens. | `tests/Browser/**` (Dusk), `tests/E2E` (9 methods/2 files — currently silently SQLite) |
| Query-plan-sensitive performance assertions | **Recommended yes** | SQLite and MySQL have materially different query planners/indexing; a performance assertion tuned against SQLite says nothing about production. Small suite (20 methods/2 files + 2 `@group performance_*` jobs), cheap to move. | `tests/Performance/**`, `performance-budget`/`performance-heavy` |
| Business logic, HTTP contract, validation, authorization decisions, view rendering | **No — SQLite is legitimate and cheaper** | These do not depend on which SQL engine stores the row; the assertions are about application code paths, not database engine behavior. | `tests/Unit`, most of `tests/Feature` (Buttons, Accessibility, SecurityFeaturesSimpleTest, general Feature suite), `tests/Feature/Api`, most of `tests/Integration`, `RouteHygieneTest` (pure route-table introspection) |

This yields a **starter allow-list** for "must be MySQL" (small, currently-known set): the split FK/unique tests (§5), `TenantIsolationProjectsTest`, `tests/E2E`, `tests/Performance/**` + the two `performance_*` PHPUnit groups, and `tests/Browser/**` (Dusk, made deliberate). Everything else defaults to SQLite. The mechanism (§3) is designed so this list can grow by tagging, not by inventing a new job pattern each time — Gate 2 is choosing the *contract*, not hand-picking a frozen list Gate 3 can never revisit.

**Explicitly not resolved here, flagged for Owner awareness, not proposed as GAP-039 scope:** `ci-cd.yml`'s `test` job and `automated-testing.yml`'s `unit-tests`/`feature-tests`/`integration-tests` jobs currently run substantially overlapping/duplicate suites (confirmed via CI history: `ci-cd.yml`'s `test` job took ~28 minutes covering Unit+Feature+Integration+coverage in one serial job, while `automated-testing.yml` runs the same three suites as three separate parallel jobs). That redundancy is a real cost but is a CI-architecture question independent of the MySQL-truthfulness question this gap is about — recorded here per the instruction to record unrelated findings separately, not fix them under GAP-039.

## 3. Recommended mechanism — Option B (canonical fail-closed entrypoint) + Option C (truthful two-tier)

### Options considered

- **Option A — per-workflow patching.** Add `ZENA_INVARIANTS_DB=mysql` to each of the 15 affected job definitions individually. Rejected as the primary approach: it repeats the same three-line incantation in 15+ places with no shared verification, which is exactly how this defect was created in the first place (every affected job already *thought* it was configuring MySQL correctly). It also does nothing about the ~11 jobs that don't actually need MySQL — it would make the "make everything genuinely MySQL" mistake for real, at real cost (§7).
- **Option B — canonical fail-closed MySQL entrypoint.** Generalize the pattern that already works (`scripts/ci/zena-invariants-mysql`) into one shared, reusable entrypoint that any job needing MySQL calls, which both sets the correct environment **and verifies it before running substantive tests** (fail-closed, not just fail-silent-if-wrong).
- **Option C — explicit two-tier CI.** SQLite stays the fast default for everything that doesn't need production parity (§2); jobs that provision a MySQL service container but don't need it lose that container (removing paid-for, unused infrastructure and the false claim in the same move); jobs that do need MySQL are named/labeled so it's obvious from the workflow file alone which tier a job is in (e.g. a `(MySQL parity)` suffix in the job `name:`, matching the existing `zena-invariants-mysql` naming precedent).

**Recommendation: converge on B + C together**, matching the instruction's framing — B without C would make every job genuinely-but-needlessly slow (§7); C without B would just be relabeling without a durable guarantee that a job named "(MySQL parity)" actually is.

### Reusing the existing pattern (not inventing parallel semantics)

`scripts/ci/zena-invariants-mysql` already contains a working fail-closed shape:
1. Export `ZENA_INVARIANTS_DB=mysql` and the resolved `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` (with sensible fallback resolution via `resolve_with_precedence`).
2. `print_zena_config` — prints the resolved runtime config for CI log auditability.
3. `ensure_mysql_connection` — boots Laravel via `bootstrap/app.php` directly (not through PHPUnit) and hard-fails if `config('database.default') !== 'mysql'`.
4. `mysql_preflight_connection` — opens a real `PDO` connection to the target and hard-fails if it cannot connect.
5. Only after all of the above pass: `migrate:fresh --force` then `php artisan test --group=...`.

This is exactly the fail-closed shape acceptance requirement 2 asks for ("runtime evidence establishes the intended MySQL backend and live connection" before substantive tests run) — it should be **extracted into a shared library** (e.g. `scripts/ci/lib/mysql-fail-closed.sh`, sourced by `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`, and new callers) rather than reinventing it, per the instruction to reuse/generalize rather than invent parallel semantics. The one gap in the existing pattern worth closing: today, `migrate:fresh` and `php artisan test` are separate process invocations inside the same script — correct for isolating the disable-FK migration's session-scoped effect (Gate 1 §4/§3b), but the *reason* that's correct is currently tribal knowledge, not an asserted, tested invariant. The shared library should assert it explicitly (e.g. re-run the `ensure_mysql_connection`/`FOREIGN_KEY_CHECKS` check immediately before the `php artisan test` step, not just once at the top) so a future refactor that accidentally merges the two steps into one process fails loudly instead of silently reintroducing Gate 1 §4's exact defect.

### Prior art found, not reused as-is
A `phpunit.mysql.xml` exists at `.claude/worktrees/gap037-treasury-migrations/phpunit.mysql.xml` (local commit `c2e12150`, "prove GAP-032 migrations on MySQL") — identical to `phpunit.xml` but with the `<env name="DB_CONNECTION" value="sqlite"/>` line removed. It is **not present on `origin/main`** (not merged, not part of any open PR found) and, used alone, would **not** actually solve the fail-closed problem: without also exporting `ZENA_INVARIANTS_DB=mysql`, `tests/bootstrap.php`'s unconditional `putenv()` would still force SQLite regardless of this file's contents (per the Gate 1 §2 mechanism proof). It is corroborating evidence that someone else independently hit this exact problem and partially, not fully, solved it — cited here for engineering context, not proposed for direct reuse.

## 4. Durable regression guard (acceptance requirement 6)

A static lint check, following the existing `docs-lint.sh`/`owner_governance_lint.php` precedent of "a script that fails CI if a structural contract is violated," not a new class of tooling:

- **New script** (e.g. `scripts/ci/lint-mysql-claim-truthfulness.php`), run as an early step in every workflow (or as its own lightweight job): parses every `.github/workflows/*.yml`, finds every job that declares a `services:` block with an `image: mysql:*` entry, and requires each such job to do exactly one of:
  - (a) route its test-invoking step through the shared fail-closed entrypoint (detected by grep for the entrypoint script name in the job's `run:` steps), or
  - (b) carry an explicit, greppable acknowledgment that the MySQL service is provisioned for a non-PHPUnit purpose (e.g. `lighthouse-ci`, which migrates real schema for a Lighthouse run but never invokes PHPUnit — already a legitimate, existing case that must not be flagged).
- Any job matching neither (a) nor (b) fails the lint with a message naming the job and workflow file, mirroring `owner_governance_lint.php`'s existing "print exact violation, exit 1" contract.
- This is static/structural (parses YAML, does not execute the actual jobs) — cheap, fast, and catches the exact failure mode this gap is about (a job's author *believes* they wired up MySQL correctly, as every one of the 15 affected jobs' authors did) at PR-review time instead of relying on someone noticing months later.

This is an engineering mechanism choice (script name, detection heuristic, placement) — Owner is asked only to approve that such a guard must exist (§8), not its implementation.

## 5. FK/unique regression coverage (acceptance requirement 5)

`QualityAssuranceTest::test_database_constraints` splits into two independently-reachable test methods, in a class (or classes) **not** tagged `@group performance`:

- `test_unique_constraint_violation_throws()` — keeps the existing `Dashboard::create([...'name' => 'Unique Dashboard'...])` assertion. Does not require MySQL (SQLite also enforces `UNIQUE`); stays on the default/fast tier.
- `test_foreign_key_constraint_violation_throws()` — keeps the existing `Widget::create(['dashboard_id' => 999999, ...])` assertion, now in its own method so no earlier `expectException()` can swallow it. Tagged for the MySQL-parity tier (§2/§3) and routed through the fail-closed entrypoint, since Gate 1 §4 established that SQLite's enforcement of this specific constraint is not reliably present in this repo's test path.
- Both methods get independent factory setup (currently shared via the class's `setUp()` — verified compatible, no `setUp()` changes needed beyond what already exists).

This directly satisfies "independent, reachable regression coverage... the FK assertion must not remain dead or accidentally excluded by grouping."

## 6. Acceptance scenarios

- **Given** a workflow job provisions a `mysql:` service and its PHPUnit step does not route through the fail-closed entrypoint, **when** the new regression guard (§4) runs on that PR, **then** CI fails with a named violation identifying the job and file — not a silent pass.
- **Given** a job is routed through the fail-closed entrypoint but the target MySQL server is unreachable or `config('database.default')` does not resolve to `mysql`, **when** the entrypoint's preflight runs, **then** the job fails before any substantive test executes (fail-closed) — it does not silently fall back to SQLite and report green.
- **Given** `QualityAssuranceTest`'s FK and unique tests are split per §5, **when** the full suite runs (any tier), **then** both methods execute and are individually visible in test output — neither is dead code nor silently excluded by `@group performance`.
- **Given** the `browser-tests` (Dusk) job (§1), **when** its MySQL-ness is made deliberate rather than accidental (either by explicit fail-closed verification added to the job, or by an equivalent guard specific to Dusk's `.env` mechanism), **then** a future change that introduces `phpunit.dusk.xml`/`.env.dusk.testing` and silently reverts it to SQLite is caught by CI, not discovered by a future audit.
- **Given** a suite classified SQLite-only (§2), **when** it runs, **then** its workflow job does not provision an unused `mysql:` service container (Option C's "truthful naming and removal of unused MySQL services").

## 7. CI cost estimate (acceptance requirement 4) — using real data, not a guess

Pulled from this repo's actual recent CI run history (`gh run view`, same run, 2026-08-18), the one clean apples-to-apples comparison the repo already contains — `zena-invariants` (SQLite) vs. `zena-invariants-mysql` (MySQL), same underlying test group, same shape of work (`migrate:fresh` + `php artisan test --group=...`):

| Job | Backend | Duration |
|---|---|---|
| Zena RBAC/Tenant Invariants | SQLite | **44s** |
| Zena RBAC/Tenant Invariants (MySQL parity) | MySQL | **862s (~14.4 min)** — **~19.6×** |

Other real durations from the same run, for scale: `RFI Escalation Concurrency (real MySQL)` 89s; `Document Workflow Concurrency (real MySQL)` 85s; `ci-cd.yml`'s `test` job (currently silently SQLite, full Unit+Feature+Integration+coverage) ~1,674s (~27.9 min); `button-tests.yml`'s `browser-tests` (genuinely MySQL + real Chrome) ~1,050s (~17.5 min) — dominated by browser automation, not the DB.

**Implication for the recommendation:** the ~19.6× multiplier applies to a suite that is DB-round-trip-heavy per assertion (many RBAC/tenant queries). It is not a universal constant — `tests/Unit` (largely mocked, minimal DB contact) would not scale anywhere near that much, while a query-heavy suite might scale more. Given §2's classification keeps the MySQL tier deliberately small (FK/unique split, `TenantIsolationProjectsTest`, `tests/E2E` at 9 methods, `tests/Performance` at 20 methods, Dusk already paying its own cost), the **added wall-clock cost of implementing this design is expected to be on the order of the existing `zena-invariants-mysql`/concurrency jobs' cost each** (roughly 1–15 minutes per newly-MySQL job, run in parallel with other jobs as GitHub Actions already does), not a multiplication of the ~28-minute `ci-cd.yml` job. Moving `tests/Performance`/`tests/E2E` to real preflight-verified MySQL adds at most a few new short-lived jobs. **This estimate should be verified with real numbers during implementation** (Gate 3), not trusted blindly — flagged explicitly, consistent with not overstating design-phase confidence.

## 8. What Owner is being asked to decide vs. what engineering owns

Per `OWNER_OPERATING_MODEL.md` (as directed): Owner is asked to approve the **quality guarantee and acceptable risk**, not implementation mechanics.

**Owner decision surface:**
- Approve that CI must never claim MySQL coverage it doesn't deliver (§4's guard existing at all).
- Approve the classification principle in §2 (a small, explicit set of suites get real MySQL parity; everything else stays SQLite by default) rather than requiring 100% MySQL coverage — i.e., accept the SQLite-for-business-logic / MySQL-for-DB-engine-specific-correctness split as the intended, permanent quality bar, not a temporary compromise.
- Approve that the FK/unique split (§5) is in scope for GAP-039 (it was already Gate-1-approved as scope; reconfirmed here since it's now a concrete design, not just a problem statement).
- Accept the estimated CI cost order-of-magnitude in §7 as tolerable, pending real measurement at implementation time.

**Engineering-owned (not presented for Owner approval):** which script is the fail-closed entrypoint and its exact name/location; whether it's a bash library, a PHP class, or a Composer script; how the regression guard detects entrypoint usage (grep vs. AST vs. a required job-name annotation); whether `phpunit.mysql.xml`-style separate config files are used at all versus environment-variable-only gating; exact test method names and file organization for the FK/unique split; whether the Dusk fix is a fail-closed check inside the job or a `phpunit.dusk.xml` committed to the repo with an explicit bootstrap. These are implementation choices for Gate 3/implementation, informed by this design, not a menu for Owner to pick from.

## 9. Unrelated findings recorded, not acted on (acceptance requirement 9)

- `ci-cd.yml`'s `test` job substantially duplicates `automated-testing.yml`'s `unit-tests`/`feature-tests`/`integration-tests` jobs (§2, final paragraph) — a CI-architecture redundancy, not a MySQL-truthfulness defect. Not in GAP-039 scope; recorded for a future, separate work item if Owner wants it pursued.
- No application/business-logic defects were exposed by this design pass (no real MySQL execution against production-shaped data was performed yet — that happens at implementation/Gate 3, per the instruction to record such findings separately if they surface then, not fix them under GAP-039).

## Decision Needed
Owner chooses one: **Approve** this Gate 2 design (authorizes an implementation plan to be written next, still requiring its own Gate-3-adjacent review before code changes) / **Request changes** to the design / **Decline**.

## What the owner is NOT being asked to decide
Owner is not being asked to approve any script name, file path, environment-variable name, XML config file, or other implementation mechanic (§8) — only the quality guarantee (§2's classification principle, §4's guard requirement, §5's test-split requirement) and the estimated cost order of magnitude (§7). Owner is not being asked to approve implementation, and no code, workflow, or test file has been changed by this document. Gate 3 has not started. This decision does not touch GAP-037 or GAP-038.
