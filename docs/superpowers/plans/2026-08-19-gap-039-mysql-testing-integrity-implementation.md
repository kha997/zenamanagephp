# GAP-039 — MySQL Testing Integrity: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every CI job that provisions or claims MySQL for PHPUnit either genuinely executes against MySQL (verified fail-closed before substantive tests run) or is honestly reclassified as SQLite with its unused MySQL service container removed; FK and unique-constraint regression coverage become independently reachable; a durable automated guard prevents this contract from silently regressing again.

**Architecture:** Extract the four already-working, near-identical MySQL fail-closed scripts (`scripts/ci/{zena-invariants,rfi-escalation-concurrency,document-workflow-concurrency,treasury-check-constraints}-mysql`) into one shared library (`scripts/ci/lib/mysql-fail-closed.sh`), then apply the same `ZENA_INVARIANTS_DB=mysql` gate — already proven correct by this repo's own working jobs — to every job classified MySQL-parity. Every job classified SQLite-only gets its unused `mysql:` service container and misleading `DB_CONNECTION: mysql` declaration removed. A new static lint (`scripts/ci/lint-mysql-claim-truthfulness.php`) makes this contract durable. `QualityAssuranceTest::test_database_constraints`'s two assertions become two independently-reachable tests in a new file, one of them MySQL-parity-tagged.

**Tech Stack:** Bash (CI scripts), PHP 8.2 / PHPUnit 11 (tests, lint), GitHub Actions YAML.

## Global Constraints

- No release, merge, or deploy. This plan produces a reviewable implementation only.
- No unrelated cleanup: do not touch GAP-037, GAP-038, or the `ci-cd.yml`/`automated-testing.yml` suite-duplication finding recorded (not acted on) in the Gate 2 engineering spec.
- If real MySQL execution exposes an unrelated application/business-logic defect, record it separately (a comment + a note in the task's completion report) — do not weaken an assertion or silently fix it under GAP-039.
- Every workflow-file edit must be followed by a real CI run on the pushed head before the task is considered done — YAML config has no local unit-test equivalent; GitHub Actions is the test harness.
- `tests/bootstrap.php`, `phpunit.xml`, and the existing 4 MySQL scripts' core logic (`resolve_with_precedence`, `resolve_host_with_fallback`, `ensure_mysql_connection`, `mysql_preflight_connection`) must not change behavior during extraction — Task 1/2 is a refactor, not a rewrite.
- Current `origin/main` state (verified 2026-08-19, HEAD `cb5cb893`) already contains **more genuinely-MySQL infrastructure than GAP-039's Gate 1 evidence described**: `phpunit.mysql.xml` was committed by GAP-032 (commit `19c96cfa`), and `scripts/ci/treasury-check-constraints-mysql` was added by GAP-038 — both postdate this gap's Gate 1 audit. Reuse both; do not treat either as absent. This plan builds on accurate current state, not the Gate 1 evidence snapshot.

---

## File Structure

**New:**
- `scripts/ci/lib/mysql-fail-closed.sh` — shared fail-closed library (env resolution + `ensure_mysql_connection` + `mysql_preflight_connection`)
- `scripts/ci/lib/mysql-fail-closed.test.sh` — self-test for the library (mocks `php`/`bootstrap/app.php` via a PATH-shadowed fake, no real MySQL needed)
- `scripts/ci/lint-mysql-claim-truthfulness.php` — static regression guard
- `scripts/ci/lint-mysql-claim-truthfulness.test.php` — fixture-based self-test for the guard
- `tests/Feature/DatabaseConstraintsTest.php` — the two independently-reachable constraint tests

**Modified:**
- `scripts/ci/zena-invariants-mysql`, `scripts/ci/rfi-escalation-concurrency-mysql`, `scripts/ci/document-workflow-concurrency-mysql`, `scripts/ci/treasury-check-constraints-mysql` — source the shared library instead of duplicating it
- `tests/Feature/QualityAssuranceTest.php` — remove `test_database_constraints` (moved to `DatabaseConstraintsTest`)
- `.github/workflows/automated-testing.yml` — 5 jobs (`unit-tests`, `feature-tests`, `api-tests-fast`, `api-tests-slow`, `integration-tests`) reclassified SQLite-only; `performance-tests` matrix reclassified MySQL-parity
- `.github/workflows/ci-cd.yml` — `test` job's "Execute tests" step reclassified SQLite-only (its separate "Prove GAP-032 migrations" step is untouched — already correct)
- `.github/workflows/button-tests.yml` — `feature-tests`/`security-tests` reclassified SQLite-only; `browser-tests` gets an explicit fail-closed MySQL preflight (making its already-real MySQL usage deliberate instead of accidental)
- `.github/workflows/a11y-perf-testing.yml` — `accessibility-tests` reclassified SQLite-only; `performance-budget`/`performance-heavy`/`e2e-tests` reclassified MySQL-parity
- `.github/workflows/production.yml` — `test` job reclassified SQLite-only
- `.github/workflows/routes-guardrails.yml` — `RouteHygieneTest` step reclassified SQLite-only; `TenantIsolationProjectsTest` step reclassified MySQL-parity
- `.github/workflows/owner-governance-lint.yml` — add the new regression-guard lint as a step

---

### Task 1: Shared fail-closed MySQL library

**Files:**
- Create: `scripts/ci/lib/mysql-fail-closed.sh`
- Create: `scripts/ci/lib/mysql-fail-closed.test.sh`

**Interfaces:**
- Produces (sourced by Task 2 and any future MySQL-parity script): `zena_mysql_resolve_env()`, `zena_mysql_print_config()`, `zena_mysql_ensure_connection()`, `zena_mysql_preflight_connection()`. All read/export the same `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`ZENA_INVARIANTS_DB` variable names the 4 existing scripts already use, so callers don't change their own env var names.

- [ ] **Step 1: Write the library**

```bash
cat > scripts/ci/lib/mysql-fail-closed.sh << 'LIBEOF'
#!/usr/bin/env bash
# Shared fail-closed MySQL entrypoint library (GAP-039).
#
# Extracted from scripts/ci/zena-invariants-mysql (the original working
# pattern) to eliminate the 4-way duplication that had grown across
# zena-invariants-mysql, rfi-escalation-concurrency-mysql,
# document-workflow-concurrency-mysql, and treasury-check-constraints-mysql.
# Behavior is unchanged from the original zena-invariants-mysql logic.
#
# Usage (source, then call in order):
#   source "$(dirname "${BASH_SOURCE[0]}")/lib/mysql-fail-closed.sh"
#   zena_mysql_resolve_env
#   zena_mysql_print_config
#   zena_mysql_ensure_connection
#   zena_mysql_preflight_connection
#   # ... only now is it safe to migrate/seed/run tests against MySQL ...

zena_mysql_resolve_with_precedence() {
    local default_value="$1"
    shift
    local env_var
    for env_var in "$@"; do
        local env_value
        env_value="${!env_var:-}"
        if [[ -n "$env_value" ]]; then
            printf '%s' "$env_value"
            return 0
        fi
    done
    printf '%s' "$default_value"
}

zena_mysql_resolve_host_with_fallback() {
    local host="$1"
    if [[ "$host" != "mysql" ]]; then
        printf '%s' "$host"
        return 0
    fi

    # Guard against macOS not resolving "mysql" by falling back to localhost.
    if python3 -c 'import socket, sys; socket.gethostbyname(sys.argv[1])' "$host" >/dev/null 2>&1; then
        printf '%s' "$host"
    else
        printf '127.0.0.1'
    fi
}

# Resolves DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD from
# MYSQL_*/ZENA_MYSQL_*/DB_* (in that precedence order) and exports them,
# plus ZENA_INVARIANTS_DB=mysql — the one variable tests/bootstrap.php
# checks before deciding whether to force SQLite.
zena_mysql_resolve_env() {
    local resolved_host resolved_port resolved_database resolved_username resolved_password

    resolved_host=$(zena_mysql_resolve_with_precedence "mysql" MYSQL_HOST ZENA_MYSQL_HOST DB_HOST)
    resolved_host=$(zena_mysql_resolve_host_with_fallback "$resolved_host")
    resolved_port=$(zena_mysql_resolve_with_precedence "3306" MYSQL_PORT ZENA_MYSQL_PORT DB_PORT)
    resolved_database=$(zena_mysql_resolve_with_precedence "zenamanage_test" MYSQL_DATABASE ZENA_MYSQL_DATABASE DB_DATABASE)
    resolved_username=$(zena_mysql_resolve_with_precedence "root" MYSQL_USERNAME ZENA_MYSQL_USERNAME DB_USERNAME)
    resolved_password=$(zena_mysql_resolve_with_precedence "" MYSQL_PASSWORD ZENA_MYSQL_PASSWORD DB_PASSWORD)

    export DB_HOST="$resolved_host"
    export DB_PORT="$resolved_port"
    export DB_DATABASE="$resolved_database"
    export DB_USERNAME="$resolved_username"
    export DB_PASSWORD="$resolved_password"
    export DB_CONNECTION=mysql
    export ZENA_INVARIANTS_DB=mysql
}

zena_mysql_print_config() {
    php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
    printf(
        "ZENA MYSQL FAIL-CLOSED CONFIG: env=%s | mode=%s | default=%s | mysql_host=%s | mysql_port=%s | mysql_db=%s | mysql_user=%s | mysql_pw=%s\n",
        $app->environment(),
        getenv("ZENA_INVARIANTS_DB") ?: "unset",
        config("database.default"),
        config("database.connections.mysql.host") ?? "null",
        config("database.connections.mysql.port") ?? "null",
        config("database.connections.mysql.database") ?? "null",
        config("database.connections.mysql.username") ?? "null",
        empty(config("database.connections.mysql.password")) ? "EMPTY" : "SET"
    );
'
}

# Fails closed (exit 1) unless Laravel's own resolved default connection is
# genuinely "mysql". Boots via bootstrap/app.php directly — never through
# tests/bootstrap.php/phpunit.xml — so this check cannot itself be fooled by
# the same override it is verifying was NOT applied.
zena_mysql_ensure_connection() {
    local default_connection
    default_connection=$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
printf("%s", config("database.default"));
')

    if [[ "$default_connection" != "mysql" ]]; then
        echo "ERROR: zena_mysql_ensure_connection requires mysql but database.default is '$default_connection'." >&2
        exit 1
    fi
}

# Fails closed (exit 1) unless a real PDO connection to the resolved MySQL
# target succeeds within 5 seconds.
zena_mysql_preflight_connection() {
    php -r '
try {
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: 3306;
    $db = getenv("DB_DATABASE") ?: "zenamanage_test";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $db);
    new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    printf("Preflight MySQL connection succeeded (%s:%s/%s)\n", $host, $port, $db);
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL connection preflight failed: " . $e->getMessage() . "\n");
    exit(1);
}
'
}
LIBEOF
chmod +x scripts/ci/lib/mysql-fail-closed.sh
```

- [ ] **Step 2: Write the self-test (no real MySQL required)**

```bash
mkdir -p scripts/ci/lib
cat > scripts/ci/lib/mysql-fail-closed.test.sh << 'TESTEOF'
#!/usr/bin/env bash
# Self-test for scripts/ci/lib/mysql-fail-closed.sh — no real MySQL or
# Laravel app boot required: this shadows `php` on PATH with a fake that
# returns canned output, so the test verifies the library's control flow
# (fail-closed on wrong connection, fail-closed on unreachable server)
# without needing infrastructure.
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT_DIR"

# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

FAKE_PHP_DIR="$(mktemp -d)"
trap 'rm -rf "$FAKE_PHP_DIR"' EXIT

pass=0
fail=0

assert_eq() {
    local desc="$1" expected="$2" actual="$3"
    if [[ "$expected" == "$actual" ]]; then
        echo "PASS: $desc"
        pass=$((pass + 1))
    else
        echo "FAIL: $desc (expected '$expected', got '$actual')"
        fail=$((fail + 1))
    fi
}

assert_exit_nonzero() {
    local desc="$1"
    shift
    if "$@" >/tmp/mysql-fail-closed-test-output 2>&1; then
        echo "FAIL: $desc (expected nonzero exit, got 0)"
        fail=$((fail + 1))
    else
        echo "PASS: $desc"
        pass=$((pass + 1))
    fi
}

# --- zena_mysql_resolve_with_precedence: picks first non-empty in order ---
unset FOO_A FOO_B FOO_C 2>/dev/null || true
export FOO_B="from-b"
result="$(zena_mysql_resolve_with_precedence "default-val" FOO_A FOO_B FOO_C)"
assert_eq "resolve_with_precedence picks first set var" "from-b" "$result"
unset FOO_B

result="$(zena_mysql_resolve_with_precedence "default-val" FOO_A FOO_B FOO_C)"
assert_eq "resolve_with_precedence falls back to default" "default-val" "$result"

# --- zena_mysql_resolve_host_with_fallback: passes through non-'mysql' hosts ---
result="$(zena_mysql_resolve_host_with_fallback "127.0.0.1")"
assert_eq "resolve_host_with_fallback passes through non-mysql host" "127.0.0.1" "$result"

# --- zena_mysql_ensure_connection: fails closed when default != mysql ---
cat > "$FAKE_PHP_DIR/php" << 'FAKEPHP'
#!/usr/bin/env bash
# Fake `php -r '...'` that always reports database.default=sqlite,
# simulating tests/bootstrap.php's override having fired.
echo "sqlite"
FAKEPHP
chmod +x "$FAKE_PHP_DIR/php"

PATH="$FAKE_PHP_DIR:$PATH" assert_exit_nonzero "ensure_connection fails closed when default is sqlite" \
    bash -c 'source scripts/ci/lib/mysql-fail-closed.sh && zena_mysql_ensure_connection'

# --- zena_mysql_ensure_connection: passes when default is mysql ---
cat > "$FAKE_PHP_DIR/php" << 'FAKEPHP'
#!/usr/bin/env bash
echo "mysql"
FAKEPHP
chmod +x "$FAKE_PHP_DIR/php"

if PATH="$FAKE_PHP_DIR:$PATH" bash -c 'source scripts/ci/lib/mysql-fail-closed.sh && zena_mysql_ensure_connection'; then
    echo "PASS: ensure_connection passes when default is mysql"
    pass=$((pass + 1))
else
    echo "FAIL: ensure_connection passes when default is mysql"
    fail=$((fail + 1))
fi

# --- zena_mysql_preflight_connection: fails closed on unreachable server ---
export DB_HOST="127.0.0.1"
export DB_PORT="1"  # port 1 is never a real MySQL server in CI or locally
export DB_DATABASE="zenamanage_test"
export DB_USERNAME="root"
export DB_PASSWORD=""
assert_exit_nonzero "preflight_connection fails closed on unreachable server" \
    zena_mysql_preflight_connection

echo ""
echo "mysql-fail-closed.test.sh: $pass passed, $fail failed"
[[ "$fail" -eq 0 ]]
TESTEOF
chmod +x scripts/ci/lib/mysql-fail-closed.test.sh
```

- [ ] **Step 3: Run the self-test and verify it passes**

Run: `bash scripts/ci/lib/mysql-fail-closed.test.sh`
Expected: `mysql-fail-closed.test.sh: 6 passed, 0 failed`, exit code 0.

If `zena_mysql_preflight_connection`'s test fails to fail (i.e. it unexpectedly succeeds), a real MySQL server happens to be listening on `127.0.0.1:1` in this environment — pick a different unused port (e.g. `2`) and rerun.

- [ ] **Step 4: Commit**

```bash
git add scripts/ci/lib/mysql-fail-closed.sh scripts/ci/lib/mysql-fail-closed.test.sh
git commit -m "feat(GAP-039): add shared fail-closed MySQL entrypoint library"
```

---

### Task 2: Refactor the 4 existing MySQL scripts to use the shared library

**Files:**
- Modify: `scripts/ci/zena-invariants-mysql`
- Modify: `scripts/ci/rfi-escalation-concurrency-mysql`
- Modify: `scripts/ci/document-workflow-concurrency-mysql`
- Modify: `scripts/ci/treasury-check-constraints-mysql`

**Interfaces:**
- Consumes: `scripts/ci/lib/mysql-fail-closed.sh`'s `zena_mysql_resolve_env`, `zena_mysql_print_config`, `zena_mysql_ensure_connection`, `zena_mysql_preflight_connection` (Task 1).

- [ ] **Step 1: Rewrite `scripts/ci/zena-invariants-mysql`**

Replace the entire file (behavior-preserving — same 5 steps: resolve env, print config, ensure connection, preflight, then migrate+test):

```bash
cat > scripts/ci/zena-invariants-mysql << 'EOF'
#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

export APP_ENV=testing
export PCOV_ENABLED=0

# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

zena_mysql_resolve_env
zena_mysql_print_config
zena_mysql_ensure_connection
zena_mysql_preflight_connection

mkdir -p resources/views storage/framework/views storage/framework/cache bootstrap/cache

php artisan optimize:clear
php artisan migrate:fresh --force
php artisan migrate:status

# Re-verify immediately before the substantive test run: migrate:fresh and
# this php artisan test invocation are separate OS processes, which is
# exactly why the disable-FK-constraints testing migration's session-scoped
# SET FOREIGN_KEY_CHECKS=0 doesn't leak into this connection (GAP-039 Gate 1
# evidence, §4) — but that correctness property was previously only true by
# accident of process boundaries, not asserted. Assert it here explicitly.
zena_mysql_ensure_connection

php artisan test --group=zena-invariants
EOF
chmod +x scripts/ci/zena-invariants-mysql
```

- [ ] **Step 2: Rewrite `scripts/ci/rfi-escalation-concurrency-mysql`**

```bash
cat > scripts/ci/rfi-escalation-concurrency-mysql << 'EOF'
#!/usr/bin/env bash
set -euo pipefail

# Runs tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php against a
# real MySQL connection in CI. This is the closer of the RFI lifecycle +
# escalation history plan's blocker #5 ("must use genuine two-database-
# connection tests against real MySQL, not sequential calls against
# sqlite") — the test itself already proves real row-locking with two OS
# subprocesses when MySQL is reachable, and skips cleanly (never a false
# pass) when it isn't. This script's only job is to make sure MySQL IS
# reachable whenever this runs in CI, so the test actually executes for
# real instead of perpetually skipping.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

export APP_ENV=testing
export PCOV_ENABLED=0

# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

zena_mysql_resolve_env
zena_mysql_preflight_connection

mkdir -p resources/views storage/framework/views storage/framework/cache bootstrap/cache

php artisan optimize:clear
php artisan migrate:fresh --force

zena_mysql_ensure_connection

JUNIT_OUT="${RUNNER_TEMP:-/tmp}/rfi-concurrency-junit.xml"
./vendor/bin/phpunit tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php --log-junit "$JUNIT_OUT"

# The two tests in that file call $this->markTestSkipped(...) whenever the
# named 'mysql' Laravel connection isn't reachable — which would make this
# whole job pass vacuously and silently stop proving anything. Since this
# script guarantees MySQL IS reachable (zena_mysql_preflight_connection
# above would already have failed otherwise), a SKIPPED result here means
# the test's own connection check disagrees with our preflight — fail
# loudly instead of reporting a false green.
if grep -q 'skipped="[1-9]' "$JUNIT_OUT" 2>/dev/null; then
    echo "ERROR: RfiEscalationConcurrencyTest reported a SKIP despite MySQL being reachable per preflight — this must never be a silent pass." >&2
    echo "Skip reason(s) from the junit report:" >&2
    grep -A2 '<skipped' "$JUNIT_OUT" >&2 || true
    exit 1
fi
EOF
chmod +x scripts/ci/rfi-escalation-concurrency-mysql
```

- [ ] **Step 3: Rewrite `scripts/ci/document-workflow-concurrency-mysql`**

```bash
cat > scripts/ci/document-workflow-concurrency-mysql << 'EOF'
#!/usr/bin/env bash
set -euo pipefail

# Runs tests/Feature/Services/DocumentWorkflowConcurrencyTest.php against a
# real MySQL connection in CI. Modeled 1:1 on
# scripts/ci/rfi-escalation-concurrency-mysql (GAP-031 concurrency
# verification must be a real merge gate, not an optionally-skipped local
# test — see docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
# Task 8). This script's only job is to make sure MySQL IS reachable
# whenever this runs in CI, so the test actually executes for real instead
# of perpetually skipping.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

export APP_ENV=testing
export PCOV_ENABLED=0

# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

zena_mysql_resolve_env
zena_mysql_preflight_connection

mkdir -p resources/views storage/framework/views storage/framework/cache bootstrap/cache

php artisan optimize:clear
php artisan migrate:fresh --force

zena_mysql_ensure_connection

JUNIT_OUT="${RUNNER_TEMP:-/tmp}/document-workflow-concurrency-junit.xml"
./vendor/bin/phpunit tests/Feature/Services/DocumentWorkflowConcurrencyTest.php --log-junit "$JUNIT_OUT"

# Same contract as scripts/ci/rfi-escalation-concurrency-mysql: this script
# guarantees MySQL IS reachable via the preflight check above, so a SKIPPED
# result here means the test's own connection check disagrees with our
# preflight — fail loudly instead of reporting a false green.
if grep -q 'skipped="[1-9]' "$JUNIT_OUT" 2>/dev/null; then
    echo "ERROR: DocumentWorkflowConcurrencyTest reported a SKIP despite MySQL being reachable per preflight — this must never be a silent pass." >&2
    echo "Skip reason(s) from the junit report:" >&2
    grep -A2 '<skipped' "$JUNIT_OUT" >&2 || true
    exit 1
fi
EOF
chmod +x scripts/ci/document-workflow-concurrency-mysql
```

- [ ] **Step 4: Rewrite `scripts/ci/treasury-check-constraints-mysql`**

Read the current file first to preserve its exact test target and any GAP-038-specific comments/logic beyond the common pattern:

```bash
cat scripts/ci/treasury-check-constraints-mysql
```

Replace its `resolve_with_precedence`/`resolve_host_with_fallback`/env-resolution/`mysql_preflight_connection` block (the part duplicated from the other 3 scripts) with:

```bash
# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

zena_mysql_resolve_env
zena_mysql_preflight_connection
```

placed where the original `resolve_with_precedence`/env-export block was, and replace any later `mysql_preflight_connection` call with `zena_mysql_preflight_connection`. Keep every GAP-038-specific line (the test file path it runs, its own comments, its own skip-detection block if present) unchanged — only the 4-way-duplicated infrastructure moves to the library.

- [ ] **Step 5: Verify no regressions in local syntax/logic**

Run: `bash -n scripts/ci/zena-invariants-mysql scripts/ci/rfi-escalation-concurrency-mysql scripts/ci/document-workflow-concurrency-mysql scripts/ci/treasury-check-constraints-mysql`
Expected: no output, exit 0 (bash `-n` is a syntax-only check).

Run: `bash scripts/ci/lib/mysql-fail-closed.test.sh` again — must still pass (Task 1 unaffected).

- [ ] **Step 6: Commit**

```bash
git add scripts/ci/zena-invariants-mysql scripts/ci/rfi-escalation-concurrency-mysql scripts/ci/document-workflow-concurrency-mysql scripts/ci/treasury-check-constraints-mysql
git commit -m "refactor(GAP-039): dedupe the 4 MySQL CI scripts onto the shared fail-closed library"
```

- [ ] **Step 7: Full-repo real verification (requires a pushed head + CI, not local)**

This task cannot be fully verified locally (no MySQL server available in most dev environments). Push this commit (as part of this plan's branch) and confirm via `gh run view` that `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`, and `treasury-check-constraints-mysql` all still pass in `automated-testing.yml`, with identical pass/fail outcome and comparable duration to their pre-refactor runs (per Gate 2 engineering spec §7's baseline: `zena-invariants-mysql` ≈ 862s, `rfi-escalation-concurrency-mysql` ≈ 89s, `document-workflow-concurrency-mysql` ≈ 85s). A large duration regression indicates the refactor broke something (e.g. re-resolving env twice, or losing the process-boundary property).

---

### Task 3: Split `QualityAssuranceTest::test_database_constraints` into independently-reachable tests

**Files:**
- Create: `tests/Feature/DatabaseConstraintsTest.php`
- Modify: `tests/Feature/QualityAssuranceTest.php:172-199` (delete the `test_database_constraints` method and its docblock)

**Interfaces:**
- Produces: `DatabaseConstraintsTest::test_unique_constraint_violation_throws()` (default tier, no group), `DatabaseConstraintsTest::test_foreign_key_constraint_violation_throws()` (tagged `@group mysql-parity`, consumed by Task 6's routes-guardrails-style MySQL-parity routing — see Task 8).

- [ ] **Step 1: Write the new test file**

```bash
cat > tests/Feature/DatabaseConstraintsTest.php << 'EOF'
<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-039: extracted from QualityAssuranceTest::test_database_constraints,
 * which combined a unique-constraint assertion and a foreign-key-constraint
 * assertion in one method with two sequential expectException() calls — the
 * first exception ended the method before the FK assertion ever executed,
 * making it permanently dead code (see docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md
 * §5/§6). Split into two independent methods so both are reachable.
 */
class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'role' => 'user',
            'tenant_id' => $tenant->id,
        ]);
        $this->user->assignRole('client');
    }

    public function test_unique_constraint_violation_throws(): void
    {
        $this->actingAs($this->user);

        Dashboard::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Unique Dashboard',
        ]);

        $this->expectException(QueryException::class);

        Dashboard::create([
            'user_id' => $this->user->id,
            'name' => 'Unique Dashboard', // duplicate name -> unique constraint violation
        ]);
    }

    /**
     * @group mysql-parity
     *
     * Requires real MySQL: the disable-foreign-keys-for-testing migration's
     * SQLite branch does not reliably survive to the test connection in
     * this repo (GAP-039 Gate 1 evidence §4), so this assertion is only
     * meaningful against a real MySQL connection where the widgets.dashboard_id
     * foreign key is genuinely enforced.
     */
    public function test_foreign_key_constraint_violation_throws(): void
    {
        $this->actingAs($this->user);

        $this->expectException(QueryException::class);

        Widget::create([
            'dashboard_id' => 999999, // non-existent dashboard -> FK constraint violation
            'type' => 'chart',
            'title' => 'Test Widget',
        ]);
    }
}
EOF
```

- [ ] **Step 2: Remove the now-duplicated method from `QualityAssuranceTest.php`**

Delete lines 172-199 of `tests/Feature/QualityAssuranceTest.php` (the `/** Test database constraints */` docblock through the closing `}` of `test_database_constraints`) — verify the exact range first:

```bash
sed -n '170,201p' tests/Feature/QualityAssuranceTest.php
```

Expected output starts with `        $response->assertJsonValidationErrors(['name']);` (line 170, kept) and ends with `    public function test_concurrent_access()` (line 201-ish, kept) — the deleted block is everything strictly between them. Use your editor to delete exactly that method; do not touch anything else in the file (it has 15 other test methods that must stay unchanged).

- [ ] **Step 3: Run the new test unique-constraint case (SQLite, default tier)**

Run: `./vendor/bin/phpunit tests/Feature/DatabaseConstraintsTest.php --filter test_unique_constraint_violation_throws`
Expected: `OK (1 test, 1 assertion)`. This confirms SQLite enforces `UNIQUE` correctly (already established in Gate 1 evidence — this is a regression check, not a new discovery).

- [ ] **Step 4: Run the FK-constraint case and confirm it is reachable (SQLite will not enforce it — that's expected and is the whole point of Gate 2's design)**

Run: `./vendor/bin/phpunit tests/Feature/DatabaseConstraintsTest.php --filter test_foreign_key_constraint_violation_throws`
Expected on SQLite (this local run): likely **FAILS** with "Failed asserting that exception of type QueryException is thrown" — this is expected and correct on SQLite; it is not a bug in the test. The point of tagging it `@group mysql-parity` (Task 8 wires the CI routing) is that it must run against real MySQL to be a meaningful assertion. Confirm the failure mode is exactly "no exception thrown" (i.e. the `Widget::create()` call silently succeeded), not a PHP fatal error or missing-class error — that would indicate an unrelated defect in `Widget`/`Dashboard` and must be recorded separately per this plan's Global Constraints, not silently patched here.

- [ ] **Step 5: Confirm `QualityAssuranceTest` still runs (with the method removed) and nothing else broke**

Run: `./vendor/bin/phpunit tests/Feature/QualityAssuranceTest.php --group=performance`
Expected: the remaining 15 tests run; none reference `test_database_constraints`.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/DatabaseConstraintsTest.php tests/Feature/QualityAssuranceTest.php
git commit -m "test(GAP-039): split QualityAssuranceTest FK/unique constraints into independently-reachable tests"
```

---

### Task 4: Regression guard — `lint-mysql-claim-truthfulness.php`

**Files:**
- Create: `scripts/ci/lint-mysql-claim-truthfulness.php`
- Create: `scripts/ci/lint-mysql-claim-truthfulness.test.php`

**Interfaces:**
- Consumes: nothing from earlier tasks (standalone static analyzer).
- Produces: a CLI script, `php scripts/ci/lint-mysql-claim-truthfulness.php [path-or-directory ...]` (defaults to `.github/workflows/`), exit 0 = clean, exit 1 = violations printed to stdout, one per line, format `<file>: <job-name>: <message>`.

- [ ] **Step 1: Write the fixture-based self-test first (TDD — write the test before the implementation)**

```bash
mkdir -p scripts/ci/__fixtures__/mysql-claim-truthfulness
cat > scripts/ci/__fixtures__/mysql-claim-truthfulness/bad-unguarded-mysql-claim.yml << 'EOF'
name: Fixture — bad
on: push
jobs:
  bad-job:
    runs-on: ubuntu-latest
    env:
      DB_CONNECTION: mysql
    services:
      mysql:
        image: mysql:8.0
    steps:
      - run: php artisan test
EOF

cat > scripts/ci/__fixtures__/mysql-claim-truthfulness/good-fail-closed.yml << 'EOF'
name: Fixture — good, fail-closed
on: push
jobs:
  good-job:
    runs-on: ubuntu-latest
    env:
      DB_CONNECTION: mysql
    services:
      mysql:
        image: mysql:8.0
    steps:
      - run: bash scripts/ci/zena-invariants-mysql
EOF

cat > scripts/ci/__fixtures__/mysql-claim-truthfulness/good-non-phpunit-mysql-use.yml << 'EOF'
name: Fixture — good, mysql provisioned for a non-PHPUnit purpose
on: push
jobs:
  lighthouse-style-job:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
    steps:
      - run: php artisan migrate --force
      - run: lhci autorun
EOF

cat > scripts/ci/__fixtures__/mysql-claim-truthfulness/good-no-mysql-service.yml << 'EOF'
name: Fixture — good, honest SQLite (no mysql service at all)
on: push
jobs:
  sqlite-job:
    runs-on: ubuntu-latest
    steps:
      - run: php artisan test
EOF
```

```bash
cat > scripts/ci/lint-mysql-claim-truthfulness.test.php << 'EOF'
<?php declare(strict_types=1);

/**
 * Self-test for scripts/ci/lint-mysql-claim-truthfulness.php, using the
 * fixture workflows in scripts/ci/__fixtures__/mysql-claim-truthfulness/.
 * Run: php scripts/ci/lint-mysql-claim-truthfulness.test.php
 */

$root = dirname(__DIR__, 2);
$fixturesDir = $root . '/scripts/ci/__fixtures__/mysql-claim-truthfulness';
$lintScript = $root . '/scripts/ci/lint-mysql-claim-truthfulness.php';

$pass = 0;
$fail = 0;

function run_lint(string $lintScript, string $target): array
{
    $output = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg($lintScript) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $exitCode);
    return [$exitCode, implode("\n", $output)];
}

function assert_exit(string $desc, int $expected, int $actual, string &$pass, string &$fail): void
{
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/bad-unguarded-mysql-claim.yml');
if ($exitCode !== 0) {
    echo "PASS: bad-unguarded-mysql-claim.yml is flagged (exit $exitCode)\n";
    $pass++;
} else {
    echo "FAIL: bad-unguarded-mysql-claim.yml was NOT flagged (exit 0)\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-fail-closed.yml');
if ($exitCode === 0) {
    echo "PASS: good-fail-closed.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-fail-closed.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-non-phpunit-mysql-use.yml');
if ($exitCode === 0) {
    echo "PASS: good-non-phpunit-mysql-use.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-non-phpunit-mysql-use.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

[$exitCode, $output] = run_lint($lintScript, $fixturesDir . '/good-no-mysql-service.yml');
if ($exitCode === 0) {
    echo "PASS: good-no-mysql-service.yml is clean\n";
    $pass++;
} else {
    echo "FAIL: good-no-mysql-service.yml was incorrectly flagged\nOutput:\n$output\n";
    $fail++;
}

echo "\nlint-mysql-claim-truthfulness.test.php: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
EOF
```

- [ ] **Step 2: Run the self-test and verify it fails (implementation doesn't exist yet)**

Run: `php scripts/ci/lint-mysql-claim-truthfulness.test.php`
Expected: FAIL — `lint-mysql-claim-truthfulness.php` does not exist yet, `exec()` returns a non-zero/error exit for every fixture including the "good" ones, so all 4 assertions fail.

- [ ] **Step 3: Write the lint script**

```bash
cat > scripts/ci/lint-mysql-claim-truthfulness.php << 'EOF'
<?php declare(strict_types=1);

/**
 * lint-mysql-claim-truthfulness — GAP-039 durable regression guard.
 *
 * Parses every targeted GitHub Actions workflow YAML file and flags any job
 * that provisions a `services:` entry with an `image: mysql:*` value UNLESS
 * that job satisfies one of:
 *   (a) at least one step's `run:` invokes a known fail-closed MySQL
 *       entrypoint (any script under scripts/ci/ matching *-mysql, or a
 *       step sourcing scripts/ci/lib/mysql-fail-closed.sh), or
 *   (b) the job never invokes PHPUnit at all (`php artisan test`,
 *       `vendor/bin/phpunit`, `php artisan dusk`) — i.e. MySQL is
 *       provisioned for some other real purpose (migrations for a
 *       Lighthouse run, etc.), which this guard is not concerned with.
 *
 * Usage: php scripts/ci/lint-mysql-claim-truthfulness.php [path-or-directory ...]
 * With no arguments, scans .github/workflows/*.yml.
 * Exit 0 = no violations. Exit 1 = at least one violation (printed to stdout).
 */

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/** @return string[] */
function mysql_lint_collect_targets(array $args): array
{
    if ($args === []) {
        $repoRoot = dirname(__DIR__, 2);
        return glob($repoRoot . '/.github/workflows/*.yml') ?: [];
    }

    $targets = [];
    foreach ($args as $arg) {
        if (is_dir($arg)) {
            $targets = array_merge($targets, glob(rtrim($arg, '/') . '/*.yml') ?: []);
        } elseif (is_file($arg)) {
            $targets[] = $arg;
        }
    }
    return $targets;
}

function mysql_lint_job_has_mysql_service(array $job): bool
{
    $services = $job['services'] ?? [];
    if (!is_array($services)) {
        return false;
    }
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $image = $service['image'] ?? '';
        if (is_string($image) && str_starts_with($image, 'mysql:')) {
            return true;
        }
    }
    return false;
}

/** @return string[] every `run:` string across every step, flattened */
function mysql_lint_job_run_commands(array $job): array
{
    $commands = [];
    $steps = $job['steps'] ?? [];
    if (!is_array($steps)) {
        return $commands;
    }
    foreach ($steps as $step) {
        if (is_array($step) && isset($step['run']) && is_string($step['run'])) {
            $commands[] = $step['run'];
        }
    }
    return $commands;
}

function mysql_lint_job_invokes_phpunit(array $job): bool
{
    foreach (mysql_lint_job_run_commands($job) as $run) {
        if (preg_match('/\bphp artisan test\b/', $run)
            || str_contains($run, 'vendor/bin/phpunit')
            || preg_match('/\bphp artisan dusk\b/', $run)) {
            return true;
        }
    }
    return false;
}

function mysql_lint_job_uses_fail_closed_entrypoint(array $job): bool
{
    foreach (mysql_lint_job_run_commands($job) as $run) {
        if (preg_match('#scripts/ci/[\w-]*-mysql\b#', $run)) {
            return true;
        }
        if (str_contains($run, 'scripts/ci/lib/mysql-fail-closed.sh')) {
            return true;
        }
    }
    return false;
}

$targets = mysql_lint_collect_targets(array_slice($argv, 1));
$violations = [];

foreach ($targets as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    try {
        $parsed = Yaml::parse($content);
    } catch (\Throwable $e) {
        $violations[] = basename($file) . ": (file): YAML parse error: " . $e->getMessage();
        continue;
    }
    if (!is_array($parsed) || !isset($parsed['jobs']) || !is_array($parsed['jobs'])) {
        continue;
    }

    foreach ($parsed['jobs'] as $jobName => $job) {
        if (!is_array($job)) {
            continue;
        }
        if (!mysql_lint_job_has_mysql_service($job)) {
            continue;
        }
        if (!mysql_lint_job_invokes_phpunit($job)) {
            continue; // MySQL provisioned for a non-PHPUnit purpose — not this guard's concern.
        }
        if (mysql_lint_job_uses_fail_closed_entrypoint($job)) {
            continue; // Routed through a known fail-closed entrypoint — trusted.
        }

        $violations[] = basename($file) . ": {$jobName}: provisions a mysql: service and invokes PHPUnit/Dusk, but no step routes through a fail-closed entrypoint (scripts/ci/*-mysql or scripts/ci/lib/mysql-fail-closed.sh) — this job will silently run SQLite instead of the MySQL it claims. See docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §5.";
    }
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        echo $violation . "\n";
        if (getenv('GITHUB_ACTIONS')) {
            echo "::error::" . $violation . "\n";
        }
    }
    echo "\n❌ lint-mysql-claim-truthfulness FAIL (" . count($violations) . " violation(s))\n";
    exit(1);
}

echo "✅ lint-mysql-claim-truthfulness PASS (" . count($targets) . " file(s) scanned)\n";
exit(0);
EOF
```

- [ ] **Step 4: Run the self-test and verify it now passes**

Run: `php scripts/ci/lint-mysql-claim-truthfulness.test.php`
Expected: `lint-mysql-claim-truthfulness.test.php: 4 passed, 0 failed`, exit 0.

- [ ] **Step 5: Run the real lint against the current (pre-Task-6-through-11) `.github/workflows/` to see today's baseline violation count**

Run: `php scripts/ci/lint-mysql-claim-truthfulness.php`
Expected: FAILS with multiple violations (this is expected right now — Tasks 6-11 haven't fixed the affected jobs yet). Note the violation count; it should shrink to 0 by the end of Task 11, and this is the acceptance check for the whole plan (see Task 12).

- [ ] **Step 6: Commit**

```bash
git add scripts/ci/lint-mysql-claim-truthfulness.php scripts/ci/lint-mysql-claim-truthfulness.test.php scripts/ci/__fixtures__/
git commit -m "feat(GAP-039): add lint-mysql-claim-truthfulness durable regression guard"
```

---

### Task 5: Wire the regression guard into CI

**Files:**
- Modify: `.github/workflows/owner-governance-lint.yml`

**Interfaces:**
- Consumes: `scripts/ci/lint-mysql-claim-truthfulness.php` (Task 4).

- [ ] **Step 1: Add a step to the existing `owner-governance-lint` job**

Read the current file to confirm the exact insertion point:

```bash
grep -n "Structural validation" .github/workflows/owner-governance-lint.yml
```

Insert a new step immediately after the `Structural validation (schema + enums + contradictions)` step (matching that step's existing style):

```yaml
      - name: MySQL claim truthfulness (GAP-039 regression guard)
        run: php scripts/ci/lint-mysql-claim-truthfulness.php
```

This workflow already triggers on `pull_request`/`push` paths including `docs/**`; it does **not** currently trigger on `.github/workflows/**` changes, which this guard needs to check. Add `'.github/workflows/**'` to both the `pull_request.paths` and `push.paths` lists at the top of the file (alongside the existing `docs/owner-decisions/**` etc. entries), so the guard actually runs when a workflow file changes.

- [ ] **Step 2: Verify locally (structure only — the workflow trigger itself can't run outside GitHub Actions)**

Run: `php scripts/ci/lint-mysql-claim-truthfulness.php` (already done in Task 4 Step 5 — confirm same result).

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/owner-governance-lint.yml
git commit -m "ci(GAP-039): wire lint-mysql-claim-truthfulness into owner-governance-lint"
```

- [ ] **Step 4: Real verification (requires push + CI)** — deferred to Task 12, once Tasks 6-11 have made the guard pass clean; pushing this commit alone (before Tasks 6-11) would make `owner-governance-lint` fail in CI, which is expected and should not be "fixed" by weakening the guard — it's evidence the guard works.

---

### Task 6: Reclassify `automated-testing.yml`'s 5 general-suite jobs as honest SQLite

**Files:**
- Modify: `.github/workflows/automated-testing.yml` (jobs `unit-tests`, `feature-tests`, `api-tests-fast`, `api-tests-slow`, `integration-tests`)

**Interfaces:** none (pure YAML edit).

- [ ] **Step 1: For each of the 5 jobs, remove the `mysql:` service block and the MySQL-specific job-level env vars**

For `unit-tests` (and identically-shaped for the other 4 — confirm each job's exact current line range with `grep -n "^  unit-tests:\|^  feature-tests:\|^  api-tests-fast:\|^  api-tests-slow:\|^  integration-tests:" .github/workflows/automated-testing.yml` before editing, since line numbers shift after each edit):

Before (job-level `env:`):
```yaml
    env:
      APP_ENV: testing
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: zenamanage_test
      DB_USERNAME: test_user
      DB_PASSWORD: test_password
      SUITE_NAME: Unit tests
```

After:
```yaml
    env:
      APP_ENV: testing
      SUITE_NAME: Unit tests
```

Before (`services:` block — remove the `mysql:` entry, keep `redis:` if present since it's genuinely used for cache/session tests):
```yaml
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: laravel
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=10
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

After (drop the `mysql:` entry entirely; keep `redis:` where the original job had it — `unit-tests`, `feature-tests`, and `performance-tests` have `redis:`, `api-tests-fast`/`api-tests-slow`/`integration-tests` need to be checked individually with `grep -n "services:" -A20 .github/workflows/automated-testing.yml` since not all 5 jobs necessarily have redis):
```yaml
    services:
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

(If a given job has no `redis:` entry, remove the entire `services:` key — an empty `services: {}` is not needed.)

- [ ] **Step 2: Replace each job's "🧱 DB setup & migrations" step**

This step currently does a real `mysqladmin ping` wait loop, `CREATE DATABASE`/`CREATE USER`/`GRANT`, and `php artisan migrate --env=testing --force` against the (now-removed) MySQL service. None of that is needed for SQLite — `tests/TestCase::ensureTestingSchema()` already auto-migrates a fresh SQLite database inside the PHPUnit bootstrap the first time a test class boots. Replace the entire step body with a no-op placeholder removal: delete the step entirely (search for `- name: 🧱 DB setup & migrations` in each of the 5 jobs and remove the whole step, including its `run: |` block, up to the next `- name:` line).

- [ ] **Step 3: Add an honesty comment to each job's test-execution step**

Before (`unit-tests`):
```yaml
    - name: 🧪 Unit tests
      env:
        APP_ENV: testing
      run: |
        echo "::group::Unit tests"
        php artisan test tests/Unit --coverage --coverage-clover=coverage-unit.xml
        echo "::endgroup::"
```

After:
```yaml
    - name: 🧪 Unit tests (SQLite — business logic, no MySQL-engine-specific behavior under test; see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
      env:
        APP_ENV: testing
      run: |
        echo "::group::Unit tests"
        php artisan test tests/Unit --coverage --coverage-clover=coverage-unit.xml
        echo "::endgroup::"
```

Apply the same step-name-comment pattern (renaming to note "SQLite — ...") to `feature-tests`, `api-tests-fast`, `api-tests-slow`, `integration-tests`'s equivalent test-execution steps — do not change their `run:` commands, only the step `name:`.

- [ ] **Step 4: Update each job's "🧾 Job summary" step to stop implying MySQL**

Each job's summary step has a line `echo "- DB connection: ${DB_CONNECTION:-n/a}"` — since `DB_CONNECTION` is no longer set at job level, this will now correctly print `n/a`. Leave it as-is (the `:-n/a` fallback already makes this honest automatically — no edit needed here, just confirm by inspection).

- [ ] **Step 5: Verify YAML is still syntactically valid**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/automated-testing.yml'); echo \"valid\n\";"`
Expected: `valid`

Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/automated-testing.yml`
Expected: these 5 jobs no longer appear in the violation list (other not-yet-fixed jobs in this same file — `performance-tests` until Task 11 — will still be flagged; that's expected at this point in the plan).

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/automated-testing.yml
git commit -m "ci(GAP-039): reclassify unit/feature/api-fast/api-slow/integration tests as honest SQLite"
```

- [ ] **Step 7: Real verification (requires push + CI)** — push and confirm all 5 jobs still pass in `automated-testing.yml`, with materially similar or faster duration (removing the real MySQL service+migrate should make them faster, not slower — a slowdown indicates something else broke).

---

### Task 7: Reclassify `ci-cd.yml`'s "Execute tests" step as honest SQLite

**Files:**
- Modify: `.github/workflows/ci-cd.yml:85-93` (exact line numbers — confirm with `grep -n "Execute tests" .github/workflows/ci-cd.yml` before editing)

**Interfaces:** none.

- [ ] **Step 1: Remove the misleading MySQL env from the "Execute tests" step only — leave the "Prove GAP-032 migrations on MySQL 8.0" step and the job's `mysql:` service block untouched (that step is already genuinely correct and still needs the service)**

Before:
```yaml
    - name: Execute tests (Unit and Feature tests) via PHPUnit
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: zenamanage_test
        DB_USERNAME: root
        DB_PASSWORD: password
      run: php artisan test --coverage --coverage-clover=coverage.xml
```

After:
```yaml
    - name: Execute tests (Unit and Feature tests) via PHPUnit — SQLite (business logic; see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
      run: php artisan test --coverage --coverage-clover=coverage.xml
```

- [ ] **Step 2: Verify YAML validity and lint**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/ci-cd.yml'); echo \"valid\n\";"`
Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/ci-cd.yml`
Expected: the `test` job no longer flagged (its `mysql:` service is legitimately used by the untouched "Prove GAP-032 migrations" step, which already routes through `phpunit.mysql.xml` + `ZENA_INVARIANTS_DB: mysql` — recognized by the lint's fail-closed-entrypoint check only if that step's `run:` matches the guard's detection patterns; if it does NOT match — since it doesn't call a `scripts/ci/*-mysql` script — this job may still be flagged. If so, this is a real, narrow gap the guard's pattern-matching needs to cover: add `phpunit.mysql.xml` + `ZENA_INVARIANTS_DB` to the fail-closed-entrypoint detection in Task 4's `mysql_lint_job_uses_fail_closed_entrypoint()` — specifically, treat a step whose `run:` contains both `ZENA_INVARIANTS_DB` and `--configuration phpunit.mysql.xml` (or `-c phpunit.mysql.xml`) as a recognized fail-closed pattern too, since `phpunit.mysql.xml` has no `<env name="DB_CONNECTION" value="sqlite"/>` override and is only safe when paired with `ZENA_INVARIANTS_DB=mysql`, exactly like the shell entrypoint. Make this addition to Task 4's implementation before continuing — do not leave a known-correct job permanently flagged.)

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci-cd.yml
git commit -m "ci(GAP-039): reclassify ci-cd.yml's general test step as honest SQLite"
```

(If Step 2 required extending Task 4's lint, amend that earlier commit or add a follow-up commit to `scripts/ci/lint-mysql-claim-truthfulness.php` in the same push — do not silently under-scope the guard just to make this task's diff smaller.)

- [ ] **Step 4: Real verification (requires push + CI)** — confirm `ci-cd.yml`'s `test` job still passes end to end (both the GAP-032 MySQL step and the now-honestly-SQLite general test step).

---

### Task 8: Reclassify `button-tests.yml`'s `feature-tests`/`security-tests`; make `browser-tests`' MySQL usage deliberate

**Files:**
- Modify: `.github/workflows/button-tests.yml` (`feature-tests`, `security-tests`, `browser-tests`)

**Interfaces:**
- Consumes: `scripts/ci/lib/mysql-fail-closed.sh` (Task 1) for the `browser-tests` preflight.

- [ ] **Step 1: `feature-tests` and `security-tests` — remove the `mysql:` service and the `.env.testing` MySQL lines**

Before (`feature-tests`' `Setup environment` step, and identically for `security-tests`):
```yaml
    - name: Setup environment
      run: |
        cp .env.example .env.testing
        echo "DB_CONNECTION=mysql" >> .env.testing
        echo "DB_HOST=127.0.0.1" >> .env.testing
        echo "DB_PORT=3306" >> .env.testing
        echo "DB_DATABASE=zenamanage_test" >> .env.testing
        echo "DB_USERNAME=root" >> .env.testing
        echo "DB_PASSWORD=password" >> .env.testing
```

After:
```yaml
    - name: Setup environment (SQLite — see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
      run: cp .env.example .env.testing
```

Remove the `services:` `mysql:` block from both jobs entirely (neither has any other service to preserve — confirm with `grep -n "services:" -A15 .github/workflows/button-tests.yml` before editing, scoped to each job's line range).

Remove the now-pointless `Run migrations`/`Run seeders` steps' explicit MySQL targeting is unnecessary to change (they already just call `php artisan migrate --env=testing` / `php artisan db:seed --env=testing`, which will now correctly target SQLite via the same auto-migration path) — no edit needed there, but note: since `.env.testing` no longer sets `DB_CONNECTION=mysql`, and `.env.example`'s default IS `mysql`, these two steps' `--env=testing` migrate/seed calls will still try to hit a real (now-absent) MySQL server and fail. Also change `.env.testing`'s `DB_CONNECTION` explicitly to `sqlite` to avoid this:

```yaml
    - name: Setup environment (SQLite — see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
      run: |
        cp .env.example .env.testing
        echo "DB_CONNECTION=sqlite" >> .env.testing
        echo "DB_DATABASE=${{ github.workspace }}/database/database.sqlite" >> .env.testing
```

Add a step before `Run migrations` to ensure the sqlite file/directory exists (mirroring `ci-cd.yml`'s `Create Database` step):
```yaml
    - name: Create SQLite database file
      run: |
        mkdir -p database
        touch database/database.sqlite
```

- [ ] **Step 2: `browser-tests` — add an explicit fail-closed MySQL preflight before "Start Laravel server"**

Per the Gate 2 engineering spec §1/§5: `browser-tests` already genuinely uses MySQL (Dusk bypasses `tests/bootstrap.php` via its own auto-generated `phpunit.dusk.xml`), but this was never deliberately verified. Add a preflight step right after the existing `Run seeders` step and before `Start Laravel server`:

```bash
grep -n "Run seeders\|Start Laravel server" .github/workflows/button-tests.yml
```

Insert:
```yaml
    - name: Verify MySQL is genuinely reachable before starting the server (GAP-039 — Dusk bypasses tests/bootstrap.php, so this must be checked explicitly)
      run: |
        source scripts/ci/lib/mysql-fail-closed.sh
        zena_mysql_ensure_connection
        zena_mysql_preflight_connection
```

This step relies on `.env` already having `DB_CONNECTION=mysql` (set by the earlier `cp .env.testing .env` step in this job, unchanged) — `zena_mysql_ensure_connection` boots Laravel via `bootstrap/app.php` directly (not through Dusk's own config resolution), so it independently confirms the same MySQL config Dusk will use, without depending on Dusk's own bypass mechanism to self-report correctly.

- [ ] **Step 3: Verify YAML validity and lint**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/button-tests.yml'); echo \"valid\n\";"`
Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/button-tests.yml`
Expected: `feature-tests`/`security-tests` no longer flagged (no `mysql:` service). `browser-tests` uses `php artisan dusk`, which the lint's `mysql_lint_job_invokes_phpunit()` pattern (`/\bphp artisan dusk\b/`) already recognizes — confirm it is not flagged now that its `run:` commands include the new preflight step referencing `scripts/ci/lib/mysql-fail-closed.sh` (already matched by `mysql_lint_job_uses_fail_closed_entrypoint()`'s `str_contains($run, 'scripts/ci/lib/mysql-fail-closed.sh')` check from Task 4).

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/button-tests.yml
git commit -m "ci(GAP-039): reclassify button-tests feature/security tests as SQLite, make browser-tests MySQL usage deliberate"
```

- [ ] **Step 5: Real verification (requires push + CI)** — confirm `feature-tests`, `security-tests`, and `browser-tests` all still pass. `browser-tests` passing with the new preflight step confirms the previously-accidental MySQL usage is now a verified, guarded fact rather than an implementation-detail accident.

---

### Task 9: Reclassify `a11y-perf-testing.yml`'s `accessibility-tests`; promote `performance-budget`/`performance-heavy`/`e2e-tests` to MySQL parity

**Files:**
- Modify: `.github/workflows/a11y-perf-testing.yml` (`accessibility-tests`, `performance-budget`, `performance-heavy`, `e2e-tests`)

**Interfaces:** none.

- [ ] **Step 1: `accessibility-tests` — remove the unused `mysql:` service (keep `redis:`)**

Before (`services:` block):
```yaml
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root_password
          MYSQL_DATABASE: zenamanage_test
          MYSQL_USER: test_user
          MYSQL_PASSWORD: test_password
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

After:
```yaml
    services:
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

`accessibility-tests`' `Setup environment` step only does `cp .env.example .env` (no explicit `DB_CONNECTION` override), which defaults to `mysql` per `.env.example` — since `tests/bootstrap.php` already silently overrides to SQLite (no `ZENA_INVARIANTS_DB` set), removing the service doesn't change test behavior, only removes the now-provably-unused container. Also fix the `Run database migrations` step, which currently does `php artisan migrate --env=testing --force` against a `.env.testing` file this job never creates (a pre-existing latent bug, out of GAP-039 scope to silently fix per Global Constraints — but since GAP-039 IS touching this exact step for the MySQL-truthfulness reason, record this separately rather than leaving it un-mentioned): change `--env=testing` to no flag (use `.env`, which does exist) so the step targets the file that's actually present:

```yaml
    - name: Run database migrations (SQLite — see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
      run: php artisan migrate --force
```

Note in the task completion report: "`accessibility-tests`' migration step previously targeted `.env.testing` via `--env=testing`, a file this job never creates — likely silently falling through to `.env` already (Laravel's `--env` flag failure mode) or to another latent default; changed to explicitly target `.env` to remove the ambiguity. This is a pre-existing config inconsistency unrelated to MySQL truthfulness, recorded here per Global Constraints, not a new GAP-039 defect."

- [ ] **Step 2: `performance-budget` and `performance-heavy` — add `ZENA_INVARIANTS_DB: mysql` to each job's existing `env:` block**

Before (`performance-budget`, and identically for `performance-heavy`):
```yaml
    env:
      APP_ENV: testing
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: zenamanage_test
      DB_USERNAME: test_user
      DB_PASSWORD: test_password
      DB_SOCKET: ''
      REDIS_HOST: 127.0.0.1
      REDIS_PORT: 6379
```

After:
```yaml
    env:
      APP_ENV: testing
      ZENA_INVARIANTS_DB: mysql
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: zenamanage_test
      DB_USERNAME: test_user
      DB_PASSWORD: test_password
      DB_SOCKET: ''
      REDIS_HOST: 127.0.0.1
      REDIS_PORT: 6379
```

This is the entire fix for these two jobs — `DB_CONNECTION`/`DB_HOST`/etc. were already correctly set to match the provisioned `mysql:` service; only `ZENA_INVARIANTS_DB` was missing, which is exactly why `tests/bootstrap.php` was silently overriding them (Gate 1 evidence §2/§3). No other step needs to change.

- [ ] **Step 3: `e2e-tests` — add a job-level `env:` block (it currently has none) with matching MySQL credentials plus `ZENA_INVARIANTS_DB`**

Before (`e2e-tests`, no `env:` key at job level — confirm with `sed -n '330,335p' .github/workflows/a11y-perf-testing.yml`):
```yaml
  e2e-tests:
    name: E2E Tests
    runs-on: ubuntu-latest
    
    services:
      mysql:
```

After:
```yaml
  e2e-tests:
    name: E2E Tests
    runs-on: ubuntu-latest
    env:
      APP_ENV: testing
      ZENA_INVARIANTS_DB: mysql
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: zenamanage_test
      DB_USERNAME: test_user
      DB_PASSWORD: test_password
    
    services:
      mysql:
```

(credentials match the job's own existing `mysql:` service block: `MYSQL_DATABASE: zenamanage_test`, `MYSQL_USER: test_user`, `MYSQL_PASSWORD: test_password` — unchanged, just now also declared at job-env level so the PHPUnit process inherits them).

- [ ] **Step 4: Verify YAML validity and lint**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/a11y-perf-testing.yml'); echo \"valid\n\";"`
Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/a11y-perf-testing.yml`
Expected: `accessibility-tests` no longer flagged (no `mysql:` service). `performance-budget`/`performance-heavy`/`e2e-tests` are still flagged by this static lint — they now genuinely reach MySQL via `ZENA_INVARIANTS_DB`, but they don't route through a recognized fail-closed *entrypoint script*, they just set env vars directly and run `vendor/bin/phpunit`/`php artisan test` inline. This is a real, deliberate design choice (these 3 jobs don't need the full migrate-fresh+preflight ceremony of the dedicated scripts, since their `Run database migrations`/`Prepare testing environment` steps already handle setup) — extend Task 4's `mysql_lint_job_uses_fail_closed_entrypoint()` to also recognize a job whose `env:` block sets `ZENA_INVARIANTS_DB: mysql` directly (a job-level fail-closed declaration, distinct from the script-based one) AS LONG AS it also has a preceding step whose `run:` contains a real connectivity check — since these 3 jobs don't currently have one, add a minimal one:

```yaml
    - name: Verify MySQL is genuinely reachable (GAP-039)
      run: |
        source scripts/ci/lib/mysql-fail-closed.sh
        zena_mysql_ensure_connection
        zena_mysql_preflight_connection
```

placed immediately after each job's `Run database migrations` step (all 3 jobs) — this makes them consistent with `browser-tests`' Task 8 pattern and gives the lint a real signal (`scripts/ci/lib/mysql-fail-closed.sh` in a `run:` string) to key off, rather than requiring a job-level-env special case in the guard. Re-run the lint after adding these 3 steps; all three jobs should now be clean.

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/a11y-perf-testing.yml
git commit -m "ci(GAP-039): reclassify accessibility-tests as SQLite, verify performance-budget/heavy/e2e-tests as genuine MySQL parity"
```

- [ ] **Step 6: Real verification (requires push + CI)** — confirm all 4 jobs pass. `performance-budget`/`performance-heavy`/`e2e-tests` should now take measurably longer than before (real MySQL round trips) — compare against the Gate 2 spec §7 baseline expectations and flag a large unexpected regression rather than assuming it's fine.

---

### Task 10: Reclassify `production.yml`'s `test` job as honest SQLite

**Files:**
- Modify: `.github/workflows/production.yml`

**Interfaces:** none.

- [ ] **Step 1: Remove the unused `mysql:` service from the `test` job**

Before:
```yaml
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: zena_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

After: delete the `services:` key entirely from this job (it has no other service).

- [ ] **Step 2: Verify YAML validity and lint**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/production.yml'); echo \"valid\n\";"`
Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/production.yml`
Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/production.yml
git commit -m "ci(GAP-039): remove unused mysql service from production.yml test job"
```

- [ ] **Step 4: Real verification (requires push + CI)** — confirm `test` job still passes.

---

### Task 11: `routes-guardrails.yml` — split RouteHygieneTest (SQLite) from TenantIsolationProjectsTest (MySQL parity)

**Files:**
- Modify: `.github/workflows/routes-guardrails.yml`

**Interfaces:** none.

- [ ] **Step 1: `RouteHygieneTest` step — remove its MySQL env, since it's pure route-table introspection with no MySQL-specific need**

Before:
```yaml
      - name: Run RouteHygieneTest
        env:
          APP_ENV: testing
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: zenamanage_test
          DB_USERNAME: root
          DB_PASSWORD: ""
          TERM: dumb
        # PHPUnit 11 removed --no-interaction and -v pass-through options
        run: php artisan test --filter RouteHygieneTest
```

After:
```yaml
      - name: Run RouteHygieneTest (SQLite — pure route-table introspection, no MySQL-specific behavior; see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
        env:
          APP_ENV: testing
          TERM: dumb
        # PHPUnit 11 removed --no-interaction and -v pass-through options
        run: php artisan test --filter RouteHygieneTest
```

- [ ] **Step 2: `TenantIsolationProjectsTest` step — add `ZENA_INVARIANTS_DB: mysql` (its `DB_CONNECTION`/host/port/etc. are already correct)**

Before:
```yaml
      - name: Run TenantIsolationProjectsTest
        env:
          APP_ENV: testing
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: zenamanage_test
          DB_USERNAME: root
          DB_PASSWORD: ""
          TERM: dumb
        run: php artisan test --filter TenantIsolationProjectsTest
```

After:
```yaml
      - name: Run TenantIsolationProjectsTest (MySQL parity — real tenant-isolation queries against real schema; see docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md §3)
        env:
          APP_ENV: testing
          ZENA_INVARIANTS_DB: mysql
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: zenamanage_test
          DB_USERNAME: root
          DB_PASSWORD: ""
          TERM: dumb
        run: |
          source scripts/ci/lib/mysql-fail-closed.sh
          zena_mysql_ensure_connection
          zena_mysql_preflight_connection
          php artisan test --filter TenantIsolationProjectsTest
```

(The job's earlier `Migrate test DB` step already runs `php artisan migrate:fresh --env=testing --force` against real MySQL via `.env.testing`, which is a separate artisan-CLI process unaffected by `tests/bootstrap.php` — unchanged, still correct.)

- [ ] **Step 3: Verify YAML validity and lint**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/routes-guardrails.yml'); echo \"valid\n\";"`
Run: `php scripts/ci/lint-mysql-claim-truthfulness.php .github/workflows/routes-guardrails.yml`
Expected: clean (this job's single `mysql:` service is legitimately used by the now-fail-closed `TenantIsolationProjectsTest` step, and `RouteHygieneTest`'s step no longer claims MySQL it doesn't use).

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/routes-guardrails.yml
git commit -m "ci(GAP-039): routes-guardrails — RouteHygieneTest honest SQLite, TenantIsolationProjectsTest genuine MySQL parity"
```

- [ ] **Step 5: Real verification (requires push + CI)** — confirm both steps still pass.

---

### Task 12: Full-repo acceptance check

**Files:** none (verification only).

**Interfaces:**
- Consumes: every prior task's output.

- [ ] **Step 1: Run the regression guard against the entire `.github/workflows/` directory**

Run: `php scripts/ci/lint-mysql-claim-truthfulness.php`
Expected: `✅ lint-mysql-claim-truthfulness PASS (<N> file(s) scanned)`, exit 0. If any violation remains, it means a job was missed by Tasks 6-11 — go back and fix it; do not consider this task done with a known violation outstanding.

- [ ] **Step 2: Run the full local test suite (SQLite) to confirm nothing broke**

Run: `./vendor/bin/phpunit`
Expected: same pass/fail profile as before this plan's changes, modulo `DatabaseConstraintsTest::test_foreign_key_constraint_violation_throws` (Task 3) which is expected to fail on SQLite by design — confirm it's the *only* new failure, and that its failure mode is still "no exception thrown" (not a fatal error).

- [ ] **Step 3: Run `owner_governance_lint.php` and `docs-lint.sh` (this plan touches no `docs/owner-decisions/**` content, but confirm nothing regressed)**

Run: `php scripts/ssot/owner_governance_lint.php && bash scripts/ci/docs-lint.sh`
Expected: both PASS.

- [ ] **Step 4: Push and verify every CI job in every touched workflow is green on the exact final head**

```bash
git push
gh pr checks <PR number for this implementation branch>
```

Cross-reference against the Gate 2 engineering spec's §7 CI-cost estimate: report the measured before/after durations for every job whose tier changed (Tasks 6-11), and for the 4 refactored MySQL scripts (Task 2) — this is the "measured, not estimated" evidence Gate 3 will require per the approved Gate 2 contract's acceptance scenario "Chi phí CI tăng thêm cho các nhóm MySQL parity phải được đo bằng số liệu thật."

- [ ] **Step 5: Do not proceed further**

This plan's scope ends here. No release, merge, or deploy. Gate 3 packet preparation (technical evidence, technical readiness) is a separate, later step — not part of this implementation plan's tasks.

---

## Self-Review Notes

- **Spec coverage:** Task 1/2 → Gate 2 spec §4 (fail-closed entrypoint, reuse existing pattern). Task 3 → §6 (FK/unique split). Task 4/5 → §5 (durable regression guard). Tasks 6-11 → §3 (classification: SQLite default, MySQL parity for constraints/tenant-isolation/concurrency-already-done/E2E-browser/performance). Task 8 also resolves §1 (Dusk deliberate verification). Task 12 → §7 (measured CI cost) and the Owner-approved Gate 2 packet's "Kịch bản chấp nhận" acceptance scenarios (all 5 covered: fail-closed proof, no SQLite masquerading as MySQL, independent FK/unique, automated drift guard, measured cost).
- **Placeholder scan:** no TBD/TODO/"add appropriate" language; every YAML before/after and every script/test file is complete, copy-pasteable content.
- **Type/name consistency:** `zena_mysql_resolve_env`/`zena_mysql_print_config`/`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection` (Task 1) are the exact names used by every caller in Tasks 2, 8, 9, 11. `@group mysql-parity` (Task 3) is the exact tag name referenced in the Gate 2 spec §3's tagging-mechanism note.
- **Known follow-up embedded in the plan, not deferred silently:** Task 7 Step 2 and Task 9 Step 4 both note that Task 4's lint needs a small extension (recognizing `phpunit.mysql.xml`+`ZENA_INVARIANTS_DB` and job-level-env-plus-connectivity-check patterns respectively) discovered while wiring later tasks — this is normal plan-execution feedback, not a plan defect; the steps say explicitly what to add and where.
