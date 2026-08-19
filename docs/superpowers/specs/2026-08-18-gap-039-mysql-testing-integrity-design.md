---
work_id: GAP-039
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-039/02-design.md
---

# GAP-039 — MySQL Testing Integrity: Gate 2 Engineering Design & Evidence

**Status:** Gate 1 approved. Gate 2 design submitted, awaiting Owner decision (round 2, after Owner round-1 REQUEST CHANGES separated this engineering-mechanics content out of the Owner packet — see `docs/owner-decisions/GAP-039/02-design.md`). Gate 3 not started; implementation, merge and release are not authorized. No workflow, `tests/bootstrap.php`, PHPUnit configuration, test code, or application/production code has been changed to produce this document.

**Objective:** Every CI job that provisions or claims MySQL for PHPUnit must either genuinely execute against MySQL, verified before substantive tests run, or be honestly reclassified as SQLite with the unused MySQL service container removed. FK and unique-constraint regression coverage must be independently reachable, not dead code or accidentally group-excluded. A durable, automated guard must prevent this contract from silently regressing again.

**Owner decision surface:** see `docs/owner-decisions/GAP-039/02-design.md` — this document is engineering evidence and design mechanics supporting that packet's recommendation (Option B + Option C), not itself a decision surface. All script names, environment-variable names, config-file choices, detection heuristics, and test-method/file organization below are engineering-owned and not something Owner is asked to approve individually.

---

## 1. Corrected fact-pattern: `browser-tests` (Dusk) is not a split-brain

Gate 1 flagged `button-tests.yml`'s `browser-tests` job as a possible split-brain (web-server process and test-assertion process on different databases) but explicitly left it unreproduced. This was reproduced during Gate 2 preparation.

**Mechanism:** `vendor/laravel/dusk/src/Console/DuskCommand.php::phpunitArguments()` hardcodes `-c phpunit.dusk.xml` (falling back to `phpunit.dusk.xml.dist`) — it never uses the root `phpunit.xml` that every other job in this repo goes through. Neither `phpunit.dusk.xml` nor `.dist` is committed in this repo, so `DuskCommand::writeConfiguration()` auto-generates one at run time by copying Dusk's own stub (`vendor/laravel/dusk/stubs/phpunit.xml`) and deletes it afterward (`removeConfiguration()`). That stub has:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit backupGlobals="false" ...>
    <testsuites>
        <testsuite name="Browser Test Suite">
            <directory suffix="Test.php">./tests/Browser</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

No `bootstrap` attribute, no `<php><env>` block. Consequence: `tests/bootstrap.php` — the file responsible for forcing `DB_CONNECTION=sqlite` in every other affected job (Gate 1 §1–§3) — **never executes for any Dusk-driven test, in any job, regardless of `ZENA_INVARIANTS_DB`.**

**Confirmed for `button-tests.yml`'s `browser-tests` job specifically:**
1. `Setup environment` step: `cp .env.example .env.testing`, then appends `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=zenamanage_test`, `DB_USERNAME=root`, `DB_PASSWORD=password`, plus `APP_URL`/`DUSK_URL=http://127.0.0.1:8000`.
2. `Generate application key` step: after writing `APP_KEY` into `.env.testing`, does **`cp .env.testing .env`** — the real-MySQL config becomes the root `.env`.
3. `Run migrations` / `Run seeders`: `php artisan migrate --env=testing` / `php artisan db:seed --env=testing` — real schema and seed data land in the real MySQL service.
4. `Start Laravel server`: `php -S 127.0.0.1:8000 -t public /tmp/dusk-router.php` — a plain PHP built-in server, not a PHPUnit process, boots Laravel's normal `LoadEnvironmentVariables` bootstrapper which reads `.env` directly (real MySQL, from step 2).
5. `php artisan dusk tests/Browser/Projects/ tests/Browser/Crm/ ...` — per the mechanism above, bypasses `tests/bootstrap.php`, so the PHPUnit/Dusk process boots the app the same way step 4 did: reading `.env` directly (same real MySQL).

Both the web server and the Dusk assertion process (which run `Tenant::create()`/`User::create()` etc. directly via Eloquent in `setUp()`, e.g. `tests/Browser/Projects/ProjectCreateTest.php:29-53`) read the identical `.env`, hence the identical database. **Not a split-brain.** This is consistent with `browser-tests`' actual CI history (`gh run view` on a recent `button-tests.yml` run: `browser-tests` completed successfully in ~17.5 minutes doing real Chrome automation against data seeded by both processes into the same store — internally inconsistent if the two processes actually disagreed on the data).

**Reclassification:** `browser-tests` moves from "Gate 1's 17 affected executions" to a 4th genuinely-MySQL execution (alongside `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`). It reached that state by accident of Dusk's own config-resolution implementation detail, not by any deliberate gate — nothing today stops a future change (committing a `phpunit.dusk.xml`, or adding a `.env.dusk`/`.env.dusk.testing` file, which `DuskCommand::setupDuskEnvironment()` explicitly checks for and will swap in) from silently reintroducing a real split-brain. This must be made a **deliberate, guarded** fact, not left as an implementation-detail accident (see §5, guard scope).

---

## 2. Full workflow/job inventory with reclassification

Carrying forward Gate 1's inventory (`docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md` §3) with the §1 correction applied:

- **Genuinely MySQL (4 executions, up from 3):** `automated-testing.yml` → `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`; `button-tests.yml` → `browser-tests` (newly reclassified, undeliberate).
- **Affected — claim/provision MySQL, silently run SQLite (15 job definitions / 16 executions, down from 16/17):** everything else in Gate 1 §3a except `browser-tests`.
- **Honest SQLite / non-PHPUnit-invoking:** unchanged from Gate 1 §3c/§3d.

---

## 3. Classification mechanism: which suites get the MySQL-parity tier

Not a one-time frozen list (Owner packet §2 correctly keeps this at the principle level: DB constraints, tenant/data isolation, concurrency, full-stack E2E/browser, DB-sensitive performance). Engineering implementation of that principle:

**Starter allow-list**, derived by matching existing suites against the principle, using the repo's own precedent (the pre-existing 3 genuine-MySQL jobs) as the template to generalize rather than invent from scratch:

| Correctness property | Tier | Current example(s) |
|---|---|---|
| Real FK/unique/CHECK enforcement at the DB layer | MySQL parity | `QualityAssuranceTest` split (§6) |
| RBAC/tenant-isolation invariants as real queries against real schema | MySQL parity | `--group=zena-invariants` (mysql variant), `TenantIsolationProjectsTest` (`routes-guardrails.yml`) |
| Concurrency/locking semantics | MySQL parity | `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql` (already correct, unchanged) |
| Full-stack browser/E2E flows | MySQL parity | `tests/Browser/**` (Dusk, §1 — made deliberate), `tests/E2E` (9 methods / 2 files, currently silently SQLite) |
| Query-plan-sensitive performance assertions | MySQL parity | `tests/Performance/**` (20 methods / 2 files), `performance-budget`/`performance-heavy` (`a11y-perf-testing.yml`) |
| Business logic, HTTP contract, validation, authorization decisions, view rendering | SQLite (default, unchanged) | `tests/Unit`, most of `tests/Feature` (Buttons, Accessibility, `SecurityFeaturesSimpleTest`, general suite), `tests/Feature/Api`, most of `tests/Integration`, `RouteHygieneTest` |

**Tagging mechanism (implementation detail, not Owner-decided):** a PHPUnit `@group mysql-parity` convention (or equivalent), so the classification is extensible by tagging future tests rather than requiring a new job pattern per addition — Gate 3/implementation is not locked to this exact starter list.

**Recorded, not acted on:** `ci-cd.yml`'s `test` job substantially duplicates `automated-testing.yml`'s `unit-tests`/`feature-tests`/`integration-tests` jobs (confirmed via CI history: `ci-cd.yml`'s `test` job took ~1,674s / ~27.9 min covering Unit+Feature+Integration+coverage serially, while `automated-testing.yml` runs the same three suites as three separate parallel jobs). This is a CI-architecture redundancy independent of the MySQL-truthfulness question — out of GAP-039 scope, flagged for a possible separate future work item.

---

## 4. Mechanism: fail-closed MySQL entrypoint (Option B) + reuse of existing pattern

### Why B, not A
Option A (patch each of the 15 affected job definitions individually, adding `ZENA_INVARIANTS_DB=mysql` inline) was rejected as the primary approach: it repeats the same incantation with no shared verification in 15+ places — exactly how the current defect arose (every affected job's author already believed they'd configured MySQL correctly; Gate 1's audit is the proof that belief was wrong 15 times over). It also does nothing to stop the ~11 jobs that don't need MySQL from being wired up needlessly (real cost, see engineering cost detail below).

### Existing working pattern to generalize, not replace
`scripts/ci/zena-invariants-mysql` already implements the correct fail-closed shape:

1. `export ZENA_INVARIANTS_DB=mysql` plus resolved `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` (via a `resolve_with_precedence` helper reading `MYSQL_*`/`ZENA_MYSQL_*`/`DB_*` in that precedence order, with a `resolve_host_with_fallback` guard for `mysql`-hostname-not-resolving-on-macOS-runners).
2. `print_zena_config()` — prints resolved runtime config to the CI log for auditability (`ZENA INVARIANTS MYSQL CONFIG: env=... | mode=... | default=... | mysql_host=... | ...`).
3. `ensure_mysql_connection()` — boots Laravel directly via `bootstrap/app.php` (not through PHPUnit/`tests/bootstrap.php`) and hard-fails (`exit 1`) if `config('database.default') !== 'mysql'`.
4. `mysql_preflight_connection()` — opens a real `PDO` connection with a 5s timeout and hard-fails if it cannot connect.
5. Only after 1–4 pass: `php artisan optimize:clear`, `php artisan migrate:fresh --force`, `php artisan migrate:status`, `php artisan test --group=zena-invariants`.

**Recommended implementation shape:** extract steps 1–4 into a shared shell library (e.g. `scripts/ci/lib/mysql-fail-closed.sh`), sourced by the existing 3 scripts plus new callers for the newly-tagged MySQL-parity tests. Exact file name/location/language is an engineering choice.

**One gap in the existing pattern to close, not merely copy forward:** today `migrate:fresh` and `php artisan test` are separate OS process invocations inside the same script — this is *why* the disable-FK migration's session-scoped `SET FOREIGN_KEY_CHECKS=0` (Gate 1 §1) doesn't leak into the test process's fresh MySQL connection (which defaults to `FOREIGN_KEY_CHECKS=1`). That correctness property is currently tribal knowledge, not an asserted, tested invariant. The shared library should make it explicit — e.g., re-verify the MySQL connection and `FOREIGN_KEY_CHECKS` state immediately before the `php artisan test` step, not only once at the top — so a future refactor that merges the two steps into one process fails loudly instead of silently reintroducing the exact defect this gap is about.

### Prior art found, explicitly not reused as-is
`.claude/worktrees/gap037-treasury-migrations/phpunit.mysql.xml` (local commit `c2e12150`, "prove GAP-032 migrations on MySQL") — identical to `phpunit.xml` minus the `<env name="DB_CONNECTION" value="sqlite"/>` line. **Not present on `origin/main`**, not part of any open PR found. Used alone it would **not** solve the fail-closed problem: without also exporting `ZENA_INVARIANTS_DB=mysql`, `tests/bootstrap.php`'s unconditional `putenv()` would still force SQLite (per Gate 1 §2's mechanism proof), since PHPUnit's `<env>` directive never overrides an env var `tests/bootstrap.php` has already set via `putenv()`. Cited as corroborating evidence someone else independently hit this exact defect and partially solved it — not proposed for direct reuse.

---

## 5. Durable regression guard

A static lint check, following the existing `docs-lint.sh` / `scripts/ssot/owner_governance_lint.php` precedent (a script that fails CI on a structural-contract violation, run early in the pipeline):

- New script, e.g. `scripts/ci/lint-mysql-claim-truthfulness.php`: parses every `.github/workflows/*.yml`, finds every job declaring a `services:` block with an `image: mysql:*` entry, and requires each to satisfy one of:
  - (a) its PHPUnit-invoking step routes through the shared fail-closed entrypoint (detected by matching the entrypoint script's invocation in the job's `run:` steps), or
  - (b) it carries an explicit, greppable acknowledgment that MySQL is provisioned for a non-PHPUnit purpose (already-legitimate existing case: `a11y-perf-testing.yml`'s `lighthouse-ci` job migrates real schema for a Lighthouse run but never invokes PHPUnit at all — must not be flagged).
- Any job matching neither fails the lint with a named violation (job + file), mirroring `owner_governance_lint.php`'s "print exact violation, exit 1" contract.
- **Dusk-specific guard scope (§1):** because Dusk resolves its PHPUnit config independently of `phpunit.xml`/`tests/bootstrap.php`, the generic guard above cannot see inside `artisan dusk`'s auto-generated config. A second, narrower check is needed specifically for `tests/Browser/**`: either (i) commit an explicit `phpunit.dusk.xml` (making the currently-implicit MySQL dependency visible and diffable) with its own preflight verification wired into the job before `artisan dusk` runs, or (ii) add a fail-closed preflight step to `browser-tests` itself (mirroring §4's `ensure_mysql_connection`/`mysql_preflight_connection` shape) run immediately before the `Start Laravel server` step. Either approach is engineering-owned; the requirement is that a future `.env.dusk`/`.env.dusk.testing` file or committed `phpunit.dusk.xml` that silently reverts this job to SQLite must fail CI, not go unnoticed.
- Static/structural (parses YAML, does not execute jobs) — fast, catches the exact failure mode this gap is about at PR-review time.

---

## 6. FK/unique regression coverage — independent, reachable

`QualityAssuranceTest::test_database_constraints` splits into two independent test methods, in a class (or classes) not tagged `@group performance`:

- `test_unique_constraint_violation_throws()` — retains the existing `Dashboard::create([...'name' => 'Unique Dashboard'...])` assertion. SQLite also enforces `UNIQUE` correctly; stays on the default/fast tier.
- `test_foreign_key_constraint_violation_throws()` — retains the existing `Widget::create(['dashboard_id' => 999999, ...])` assertion, now unreachable-dead-code-free since no earlier `expectException()` in the same method can swallow it. Tagged for the MySQL-parity tier (§3) and routed through the fail-closed entrypoint (§4), since Gate 1 §4 established SQLite's enforcement of this specific constraint is not reliably present in this repo's default test path.
- Both retain the class's existing `setUp()` factory data (`$this->user`, `$this->admin`) — verified compatible, no `setUp()` restructuring required.

Exact class/file organization (same file, split file, or moved elsewhere) is an engineering choice for implementation.

---

## 7. CI cost — measured, not estimated in the abstract

Pulled from this repo's actual CI run history (`gh run view`, run from 2026-08-18), the one clean before/after comparison already present in the repo — `zena-invariants` (SQLite) vs. `zena-invariants-mysql` (MySQL), same test group, same shape of work (`migrate:fresh` + `php artisan test --group=...`):

| Job | Backend | Duration |
|---|---|---|
| Zena RBAC/Tenant Invariants | SQLite | 44s |
| Zena RBAC/Tenant Invariants (MySQL parity) | MySQL | 862s (~14.4 min) — **~19.6×** |

Other real durations from the same run: `RFI Escalation Concurrency (real MySQL)` 89s; `Document Workflow Concurrency (real MySQL)` 85s; `ci-cd.yml`'s `test` job (currently silently SQLite, full suite + coverage) ~1,674s (~27.9 min); `button-tests.yml`'s `browser-tests` (genuinely MySQL + real Chrome) ~1,050s (~17.5 min, dominated by browser automation, not the DB).

**Interpretation:** the ~19.6× multiplier is specific to a DB-round-trip-heavy suite (many RBAC/tenant queries per assertion); it is not a universal constant. `tests/Unit` (largely mocked) would not scale anywhere near that much. Given §3 keeps the MySQL-parity tier deliberately small (FK/unique split, `TenantIsolationProjectsTest`, `tests/E2E` at 9 methods, `tests/Performance` at 20 methods, Dusk already paying its own existing cost), the added wall-clock cost is expected to be on the order of the existing `zena-invariants-mysql`/concurrency jobs' cost each (roughly 1–15 minutes per newly-MySQL job, run in parallel with other jobs as GitHub Actions already does), not a multiplication of the ~28-minute `ci-cd.yml` job. **This must be re-measured with real numbers during implementation**, not trusted as a final figure — flagged explicitly per the Owner packet's requirement that added cost be "đo thực tế trong implementation evidence."

---

## 8. Unrelated findings — recorded, not acted on

- `ci-cd.yml` / `automated-testing.yml` suite duplication (§3, final paragraph) — CI-architecture redundancy, not a MySQL-truthfulness defect, out of GAP-039 scope.
- No application/business-logic defects have been exposed yet — none of the newly-classified MySQL-parity suites have actually been run against real MySQL as part of this design pass (that happens at implementation). Per Owner's standing instruction (Gate 1 packet, "Loại trừ rõ ràng"), any such defect surfaced during implementation must be recorded separately, not silently fixed under GAP-039, and must not cause weakened assertions.

---

## 9. Lifecycle correction (applies to both this document and the Owner packet)

Earlier Gate 2 drafting language incorrectly implied a "Gate-3-adjacent review before code changes" step between Gate 2 approval and implementation. Corrected canonical authorization, consistent with this repo's standing Gate 1/2/3 model (`OWNER_OPERATING_MODEL.md`, `docs/owner-governance/packet-schema.yml`): if Owner **approves** Gate 2, engineering is authorized to write an implementation plan and carry out implementation, testing, and review. **Gate 3 opens its decision surface only once implementation is complete and technical readiness is established** (`technical_readiness: ready` per the Gate 3 schema fields); Gate 3 **approval** is what authorizes release, merge, and deploy — not an intermediate gate between Gate 2 and implementation.
