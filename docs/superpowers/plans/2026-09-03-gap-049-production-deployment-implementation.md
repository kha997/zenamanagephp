---
work_id: GAP-049
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-049/02-design.md
---

# GAP-049 Production Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Owner-approved GAP-049 Gate-2 architecture (Candidate A — hardened, exact-SHA release-based SSH deployment) so `.github/workflows/production.yml` becomes the single authoritative, truthful production-deployment entry point, with release/migration/readiness/queue/bootstrap/backup safety contracts proven in repository and disposable-environment evidence — with **zero actual production deployment, secret configuration, or host provisioning**.

**Architecture:** CI checks out and verifies an exact requested commit SHA (reachable from canonical `main`), builds an immutable release artifact excluding `.env`/secrets/runtime storage, computes a checksum, transfers it to the host over the existing SSH channel, and the host activates it via versioned `releases/<sha>/` directories + shared `.env`/`storage` + an atomic `current` symlink switch — never `git pull origin main` on the host. Migration safety is enforced by an explicit, auditable expand/breaking classification manifest checked by a dedicated Artisan command before any migration runs. A minimal, non-diagnostic-leaking readiness endpoint and a separate real-worker queue canary gate cutover completion. `deploy.yml`, `deploy.sh`, the `ci-cd.yml` placeholder deploy job, and `automated-deployment.yml`'s production paths are retired/disabled so only one live production entry point remains.

**Tech Stack:** Laravel 10/11 (PHP 8.2), PHPUnit 10.5, GitHub Actions, bash, MySQL 8.0, Symfony Yaml (already a transitive Composer dependency, usable for guard tests), Symfony Process (Laravel dependency, usable to shell out to scripts from PHPUnit).

## Global Constraints

- PHP version floor: `^8.2` (per `composer.json:11`), matches `production.yml`'s `PHP_VERSION: '8.2'`.
- `DatabaseSeeder` (and any seeder chain reachable from it) must NEVER run against a production database, under any code path added by this plan.
- No fixed/default password may ever be created for any account by code added in this plan.
- No `git pull origin main` (or any mutable-branch reference) may appear anywhere in the production deployment path.
- No broad write-capable GitHub credential may be introduced for the production host.
- Rollback must always take an explicit target SHA argument — never `HEAD~1` or equivalent.
- Release cleanup logic must never delete anything under `shared/`.
- No automatic `migrate:rollback` may be invoked by any script or workflow added in this plan.
- All new/changed release tooling must be tested against a disposable temporary filesystem — never `/var/www/zena`.
- All new tests follow existing repo conventions: `tests/Feature/**`, `tests/Unit/**`, `tests/Integration/**`, PHPUnit 10.5 attributes/annotations already in use in neighboring files, `RefreshDatabase` + SQLite for default-suite tests, real MySQL only for `@group mysql-parity`/dedicated disposable-evidence scripts (never enabled by default in `phpunit.xml`).
- No CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product code may be touched by this plan.
- No real secret, real host, real DNS/TLS, or real production database mutation may occur during this implementation.
- Every new PHP file starts with `<?php declare(strict_types=1);` (matches existing convention seen in `routes/api.php`, `tests/Traits/TenantUserFactoryTrait.php`).

---

## File Structure

```
app/Http/Controllers/Api/ProductionReadinessController.php   [new]
app/Services/Deployment/MigrationClassificationService.php   [new]
app/Console/Commands/DeployMigrateCommand.php                [new]
app/Console/Commands/QueueCanaryCommand.php                  [new]
app/Console/Commands/ProductionBootstrapCommand.php          [new]
app/Jobs/QueueCanaryJob.php                                  [new]
database/migrations/classifications.json                    [new]
routes/api.php                                               [modify: add readiness route]
.github/workflows/production.yml                             [rewrite]
.github/workflows/deploy.yml                                 [delete]
.github/workflows/ci-cd.yml                                  [modify: remove placeholder deploy job]
.github/workflows/automated-deployment.yml                   [modify: disable production-targeting jobs]
deploy.sh                                                     [delete]
scripts/deploy/lib.sh                                         [new]
scripts/deploy/verify-sha.sh                                  [new]
scripts/deploy/build-artifact.sh                               [new]
scripts/deploy/link-shared.sh                                  [new]
scripts/deploy/activate-release.sh                              [new]
scripts/deploy/rollback.sh                                     [new]
scripts/deploy/cleanup-releases.sh                              [new]
scripts/deploy/backup.sh                                       [new]
scripts/deploy/restore.sh                                      [new]
scripts/deploy/queue-canary-drill.sh                            [new, disposable evidence tool, not run by CI]
docs/runbooks/gap-049-host-provisioning.md                     [new]
docs/runbooks/gap-049-migration-safety.md                      [new]
docs/runbooks/gap-049-backup-restore.md                        [new]
tests/Feature/Deployment/ProductionReadinessEndpointTest.php    [new]
tests/Feature/Deployment/DeployMigrateCommandTest.php            [new]
tests/Feature/Deployment/QueueCanaryCommandTest.php               [new]
tests/Feature/Deployment/ProductionBootstrapCommandTest.php        [new]
tests/Feature/Deployment/ReleaseToolingTest.php                     [new]
tests/Feature/Deployment/DeploymentGuardTest.php                     [new]
tests/Feature/Deployment/TwoTenantIsolationEvidenceTest.php           [new]
docs/owner-decisions/GAP-049/03-release.md                            [new, written last]
```

---

## Task 1: Retire legacy/competing production-deployment entry points

**ADDENDUM (discovered during implementation, not in the original research pass): `.github/workflows/release-management.yml` is a FOURTH live, independently-invocable production-deployment path** (`workflow_dispatch` + `push: tags: v*` + `release: published`), with `deploy-release`/`rollback-release`/`cleanup-release` jobs using `appleboy/ssh-action` — including `git pull origin main` and `git reset --hard HEAD~1` against `/opt/zenamanage`, exactly the hazards this plan exists to eliminate. It must be brought into this task's scope: same treatment as `automated-deployment.yml` (disable, don't delete — preserve as historical reference, add `if: false` + a one-line GAP-049 comment to each of `deploy-release`, `rollback-release`, `cleanup-release`).

**Files:**
- Delete: `.github/workflows/deploy.yml`
- Delete: `deploy.sh`
- Modify: `.github/workflows/ci-cd.yml` (remove the placeholder `deploy` job, lines 144-156 per current content)
- Modify: `.github/workflows/automated-deployment.yml` (disable every job that can reach `environment: production`)
- Modify: `.github/workflows/release-management.yml` (disable `deploy-release`, `rollback-release`, `cleanup-release` — add `if: false` + comment to each; leave `create-release`/`build-release`/`generate-changelog`/`security-scan-release` untouched, they only build/tag/scan, they do not deploy)
- Test: `tests/Feature/Deployment/DeploymentGuardTest.php`

**Interfaces:**
- Produces: a guard test other tasks' CI runs must keep green — no task after this one may reintroduce a second live production entry point.

- [ ] **Step 1: Write the failing guard test**

Create `tests/Feature/Deployment/DeploymentGuardTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DeploymentGuardTest extends TestCase
{
    public function test_deploy_yml_workflow_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(
            base_path('.github/workflows/deploy.yml'),
            'deploy.yml must be retired as a production entry point (GAP-049).'
        );
    }

    public function test_legacy_deploy_sh_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(
            base_path('deploy.sh'),
            'Legacy deploy.sh must be retired, not patched (GAP-049).'
        );
    }

    public function test_ci_cd_workflow_has_no_placeholder_deploy_job(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/ci-cd.yml'));
        $this->assertArrayNotHasKey(
            'deploy',
            $yaml['jobs'] ?? [],
            'ci-cd.yml must not contain a placeholder deploy job that fakes a production-deployment signal (GAP-049).'
        );
    }

    public function test_automated_deployment_workflow_cannot_reach_production(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/automated-deployment.yml'));
        $jobs = $yaml['jobs'] ?? [];

        foreach (['deploy-production', 'rollback', 'blue-green-deployment', 'canary-deployment'] as $jobName) {
            $this->assertArrayHasKey($jobName, $jobs, "Expected job {$jobName} to still be defined (disabled, not deleted).");
            $this->assertSame(
                false,
                $jobs[$jobName]['if'] ?? null,
                "Job {$jobName} in automated-deployment.yml must be statically disabled (if: false) so Candidate B cannot independently deploy to production per GAP-049."
            );
        }

        $dispatchInputs = $yaml['on']['workflow_dispatch']['inputs']['environment']['options'] ?? [];
        $this->assertNotContains(
            'production',
            $dispatchInputs,
            'automated-deployment.yml workflow_dispatch must not offer production as a selectable environment (GAP-049 Candidate B deferred).'
        );
    }

    public function test_production_yml_is_the_only_workflow_with_a_live_ssh_deploy_step(): void
    {
        $workflowsDir = base_path('.github/workflows');
        $offenders = [];

        foreach (glob($workflowsDir . '/*.yml') as $file) {
            if (basename($file) === 'production.yml') {
                continue;
            }

            $yaml = Yaml::parseFile($file);
            foreach (($yaml['jobs'] ?? []) as $jobName => $job) {
                // Staging-only jobs are exempt — this guard is about the production entry
                // point being singular, not about disabling staging deploys entirely.
                if (($job['environment'] ?? null) === 'staging') {
                    continue;
                }

                $usesSsh = false;
                foreach (($job['steps'] ?? []) as $step) {
                    if (str_starts_with($step['uses'] ?? '', 'appleboy/ssh-action')) {
                        $usesSsh = true;
                    }
                }
                if ($usesSsh && ($job['if'] ?? null) !== false) {
                    $offenders[] = basename($file) . ':' . $jobName;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only production.yml may contain a live (non-disabled) SSH deploy step: ' . implode(', ', $offenders)
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails on the current baseline**

Run: `php artisan test --filter=DeploymentGuardTest`
Expected: FAIL — `deploy.yml` still exists, `deploy.sh` still exists, `ci-cd.yml` still has a `deploy` job, `automated-deployment.yml` jobs are not disabled.

- [ ] **Step 3: Delete `deploy.yml` and `deploy.sh`**

```bash
git rm .github/workflows/deploy.yml
git rm deploy.sh
```

- [ ] **Step 4: Remove the placeholder `deploy` job from `ci-cd.yml`**

Open `.github/workflows/ci-cd.yml` and delete the entire `deploy:` job block (the one whose only step echoes `"Deploying to production..."`). Confirm no other job's `needs:` list references `deploy` (it is currently terminal — nothing depends on it). Leave every other job (`test`, `code-quality`, etc.) untouched.

- [ ] **Step 5: Disable `automated-deployment.yml`'s production-reaching jobs**

Edit `.github/workflows/automated-deployment.yml`:
1. In the `workflow_dispatch.inputs.environment` block, change `options: [staging, production]` to `options: [staging]` and `default: staging` (leave as-is if already `staging`).
2. Add `if: false` as a job-level key (alongside the job's existing `runs-on:`/`needs:`, not replacing them) to each of: `deploy-production`, `rollback`, `blue-green-deployment`, `canary-deployment`. Example:

```yaml
deploy-production:
  needs: [build-and-push]
  if: false  # GAP-049: Candidate B production path deferred; see docs/owner-decisions/GAP-049/02-design.md
  runs-on: ubuntu-latest
  environment: production
  ...
```

Do this for all four jobs — preserve every existing key, only add the `if: false` line and the comment. Do not delete the job bodies (Owner directive: "Preserve historical design knowledge in docs where useful; do not preserve dangerous executable ambiguity merely for nostalgia" — disabling, not deleting, satisfies both: the code stays as a documented future upgrade path per Gate-2 §"Candidate B stays a documented future upgrade path only", but cannot execute).
3. If the workflow has a `release: types: [published]` trigger that can reach `deploy-production` (check the `on:` block and any job-level `if:` conditions gating on `github.event_name == 'release'`), confirm the new `if: false` on `deploy-production` already blocks it — no further change needed since the job-level `if: false` takes precedence regardless of trigger.

- [ ] **Step 5b: Disable `release-management.yml`'s production-deploy jobs**

Edit `.github/workflows/release-management.yml`: add `if: false` (plus a one-line `# GAP-049: retired production entry point; see docs/owner-decisions/GAP-049/02-design.md` comment) to each of `deploy-release`, `rollback-release`, `cleanup-release` — the three jobs that run `appleboy/ssh-action` steps against the production host. Preserve every existing key (`needs:`, `if:` conditions already present — e.g. `rollback-release`'s existing `if: failure() && github.event_name == 'release'` — combine with the new disable using `if: false && (<existing condition>)` or simply replace the value with `false` and preserve the original condition text in the added comment so it's not lost to history). Leave `create-release`, `build-release`, `generate-changelog`, `security-scan-release` untouched — they build/tag/scan only, they never deploy.

- [ ] **Step 6: Run the guard test again**

Run: `php artisan test --filter=DeploymentGuardTest`
Expected: PASS (all 5 assertions green).

- [ ] **Step 7: Validate YAML syntax for all edited workflows**

Run: `php -r "require 'vendor/autoload.php'; foreach (['ci-cd.yml','automated-deployment.yml','release-management.yml'] as \$f) { Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/'.\$f); } echo \"OK\n\";"`
Expected: `OK` with no exception.

- [ ] **Step 8: Commit**

```bash
git add .github/workflows/ci-cd.yml .github/workflows/automated-deployment.yml .github/workflows/release-management.yml tests/Feature/Deployment/DeploymentGuardTest.php
git add -u .github/workflows/deploy.yml deploy.sh
git commit -m "feat(GAP-049): retire deploy.yml/deploy.sh, disable competing production-deploy entry points (automated-deployment.yml, release-management.yml)"
```

---

## Task 2: Minimal production readiness endpoint (strict TDD)

**Files:**
- Create: `app/Http/Controllers/Api/ProductionReadinessController.php`
- Modify: `routes/api.php` (inside the existing `v1/public` group, alongside `/health`, `/health/liveness`)
- Test: `tests/Feature/Deployment/ProductionReadinessEndpointTest.php`

**Interfaces:**
- Produces: `GET /api/v1/public/production/ready` — HTTP 200 `{"status":"ready"}` when DB+cache+storage probes all pass; HTTP 503 `{"status":"not_ready","failed":["database","cache","storage"]}` (only the failing ones listed) otherwise. No other keys. Consumed by Task 6's production workflow post-cutover health-check step.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Deployment/ProductionReadinessEndpointTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionReadinessEndpointTest extends TestCase
{
    public function test_returns_200_ready_when_all_dependencies_healthy(): void
    {
        $response = $this->getJson('/api/v1/public/production/ready');

        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'ready']);
    }

    // NOTE: the three probe-failure tests below call the controller directly
    // rather than via HTTP. Reason: the route lives in the `v1/public`
    // middleware group behind ComprehensiveRateLimitMiddleware, which makes
    // its own Cache facade calls; a full-facade Cache::shouldReceive() mock
    // over an HTTP request collides with the middleware's own (unstubbed)
    // Cache calls and throws before the request ever reaches the controller.
    // Calling the controller directly exercises the identical probe/response
    // code with a real Illuminate\Http\JsonResponse, without going through
    // the HTTP kernel or its middleware stack. The happy-path, diagnostic,
    // and auth tests above/below stay full HTTP feature tests.

    public function test_returns_503_when_database_probe_fails(): void
    {
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andThrow(new \RuntimeException('connection refused'));

        $controller = new \App\Http\Controllers\Api\ProductionReadinessController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('not_ready', $data['status']);
        $this->assertContains('database', $data['failed']);
    }

    public function test_returns_503_when_cache_probe_fails(): void
    {
        Cache::shouldReceive('put')->andReturn(false);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('forget')->andReturn(true);

        $controller = new \App\Http\Controllers\Api\ProductionReadinessController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertContains('cache', $response->getData(true)['failed']);
    }

    public function test_returns_503_when_storage_probe_fails(): void
    {
        // Genuinely exercises the "read throws after a successful write" path
        // (exists() must return true so get() is actually reached), and
        // verifies delete() cleanup still runs via the probe's finally block
        // even though get() threw.
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(true);
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('get')->andThrow(new \RuntimeException('disk unwritable'));
        Storage::shouldReceive('delete')->once()->andReturn(true);

        $controller = new \App\Http\Controllers\Api\ProductionReadinessController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertContains('storage', $response->getData(true)['failed']);
    }

    public function test_storage_probe_cleanup_runs_even_when_put_fails(): void
    {
        // put() itself failing (not throwing) means $written stays false —
        // delete() must NOT be called in that case (nothing to clean up).
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new \RuntimeException('disk full'));
        Storage::shouldReceive('delete')->never();

        $controller = new \App\Http\Controllers\Api\ProductionReadinessController();
        $response = $controller->check();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertContains('storage', $response->getData(true)['failed']);
    }

    public function test_response_body_never_leaks_diagnostic_internals(): void
    {
        $response = $this->getJson('/api/v1/public/production/ready');
        $body = $response->json();

        foreach (['php_version', 'laravel_version', 'environment', 'app_env', 'memory', 'load', 'uptime'] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $body);
        }
        $this->assertArrayNotHasKey('error', $body, 'Exception messages must never leak into the readiness response body.');
    }

    public function test_route_is_rate_limited_public_group_not_authenticated(): void
    {
        // No Sanctum/session auth performed — endpoint must be reachable by deploy tooling without credentials.
        $response = $this->getJson('/api/v1/public/production/ready');
        $this->assertNotEquals(401, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProductionReadinessEndpointTest`
Expected: FAIL — route `production/ready` does not exist (404 on all).

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Api/ProductionReadinessController.php`:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GAP-049 minimal production readiness probe.
 *
 * Purpose-built: genuine DB/cache/storage round-trips only, no diagnostic
 * internals (no PHP/Laravel version, APP_ENV, memory/load, credentials).
 * 200 only when every synchronous dependency required to serve a request
 * is ready; 503 otherwise. See docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md §A-4.
 */
class ProductionReadinessController extends Controller
{
    public function check(): JsonResponse
    {
        $failed = [];

        if (!$this->probeDatabase()) {
            $failed[] = 'database';
        }
        if (!$this->probeCache()) {
            $failed[] = 'cache';
        }
        if (!$this->probeStorage()) {
            $failed[] = 'storage';
        }

        if ($failed === []) {
            return response()->json(['status' => 'ready'], 200);
        }

        return response()->json(['status' => 'not_ready', 'failed' => $failed], 503);
    }

    private function probeDatabase(): bool
    {
        try {
            $result = DB::select('SELECT 1');
            return !empty($result);
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeCache(): bool
    {
        $key = 'gap049-readiness-' . Str::random(12);
        $value = Str::random(8);

        try {
            Cache::put($key, $value, 10);
            $read = Cache::get($key);
            return $read === $value;
        } catch (\Throwable) {
            return false;
        } finally {
            // Cleanup runs even if a call above threw partway through, so a
            // transient failure never leaves an orphaned probe key behind.
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // Best-effort cleanup only — the probe result above already stands.
            }
        }
    }

    private function probeStorage(): bool
    {
        $disk = Storage::disk(config('filesystems.default'));
        $path = 'gap049-readiness-probes/' . Str::random(12) . '.probe';
        $value = Str::random(8);
        $written = false;

        try {
            $disk->put($path, $value);
            $written = true;
            $read = $disk->exists($path) ? $disk->get($path) : null;
            return $read === $value;
        } catch (\Throwable) {
            return false;
        } finally {
            // Cleanup runs even if exists()/get() throws after a successful put(),
            // so a transient failure never leaves an orphaned probe file behind
            // (unlike the cache probe, storage has no TTL to self-clean).
            if ($written) {
                try {
                    $disk->delete($path);
                } catch (\Throwable) {
                    // Best-effort cleanup only — the probe result above already stands.
                }
            }
        }
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add `use App\Http\Controllers\Api\ProductionReadinessController;` near the other `use App\Http\Controllers\Api\...` imports, then inside the existing `Route::prefix('v1/public')->middleware([...])->group(function () { ... })` block (the one already containing `/health`, `/health/liveness`, `/health/readiness`), add:

```php
        Route::get('/production/ready', [ProductionReadinessController::class, 'check']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ProductionReadinessEndpointTest`
Expected: PASS (6/6).

- [ ] **Step 6: Run the route guard script**

Run: `php artisan route:list --json | php scripts/ci/route-guard.php`
Expected: exits 0 — no duplicate METHOD+URI, no doubled prefix introduced.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ProductionReadinessController.php routes/api.php tests/Feature/Deployment/ProductionReadinessEndpointTest.php
git commit -m "feat(GAP-049): add minimal non-diagnostic production readiness endpoint"
```

---

## Task 3: Migration classification contract + `deploy:migrate` command (strict TDD)

**Files:**
- Create: `database/migrations/classifications.json`
- Create: `app/Services/Deployment/MigrationClassificationService.php`
- Create: `app/Console/Commands/DeployMigrateCommand.php`
- Test: `tests/Feature/Deployment/DeployMigrateCommandTest.php`

**Interfaces:**
- Produces: `MigrationClassificationService::__construct(string $manifestPath, string $migrationsPath)`, `->pendingMigrationFiles(): array<string>` (basenames without `.php`, sourced from the migrations directory not yet in the `migrations` table), `->classificationsFor(array $files): array<string,?string>` (file => `'expand'`|`'breaking'`|`null` if unclassified), `->hasUnclassified(array $files): bool`, `->hasBreaking(array $files): bool`.
- Produces: `php artisan deploy:migrate {--allow-breaking}` exit codes: `0` success, `1` unclassified migration(s) found, `2` breaking migration(s) present without `--allow-breaking`, `3` `--allow-breaking` passed but application is not in maintenance mode.
- Consumed by: Task 6's production workflow, which runs `php artisan deploy:migrate` (pre-cutover) instead of a bare `migrate --force`.

- [ ] **Step 1: Create the classification manifest**

Create `database/migrations/classifications.json`. Populate it by classifying every migration file currently in `database/migrations/*.php` as `"expand"` (this repo's entire existing migration history is additive schema evolution — no destructive/renaming migration is being introduced by this plan). Generate the file mechanically:

```bash
php -r '
$files = glob("database/migrations/*.php");
$out = [];
foreach ($files as $f) {
    $name = basename($f, ".php");
    $out[$name] = "expand";
}
ksort($out);
file_put_contents("database/migrations/classifications.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($out) . " migrations classified\n";
'
```

Manually review the generated file for any migration whose name contains `drop`, `rename`, or `destroy` (grep: `grep -iE "drop|rename|destroy" database/migrations/classifications.json`) — reclassify any such entry to `"breaking"` only if inspecting the actual migration file confirms it drops/renames a column or table the current running code still depends on; otherwise leave as `"expand"` (e.g. dropping an already-unused column that nothing reads is still expand-safe by this contract's own definition: "the *old, still-`current`* release's code can tolerate it unchanged"). Record which (if any) were reclassified in the Task 3 commit message.

- [ ] **Step 2: Write the failing tests**

Create `tests/Feature/Deployment/DeployMigrateCommandTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Services\Deployment\MigrationClassificationService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeployMigrateCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = storage_path('framework/testing/gap049-migrations-' . uniqid());
        File::makeDirectory($this->fixtureDir, 0755, true);
        $this->manifestPath = $this->fixtureDir . '/classifications.json';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureDir);
        parent::tearDown();
    }

    private function writeMigrationFixture(string $name): void
    {
        File::put($this->fixtureDir . "/{$name}.php", "<?php\nreturn new class extends \\Illuminate\\Database\\Migrations\\Migration {\n public function up(): void {}\n public function down(): void {}\n};\n");
    }

    public function test_service_reports_unclassified_migration(): void
    {
        $this->writeMigrationFixture('2026_09_03_000001_add_expand_column');
        File::put($this->manifestPath, json_encode([]));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000001_add_expand_column'];

        $this->assertTrue($service->hasUnclassified($files));
        $this->assertNull($service->classificationsFor($files)['2026_09_03_000001_add_expand_column']);
    }

    public function test_service_reports_expand_classification(): void
    {
        $this->writeMigrationFixture('2026_09_03_000002_add_expand_column');
        File::put($this->manifestPath, json_encode(['2026_09_03_000002_add_expand_column' => 'expand']));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000002_add_expand_column'];

        $this->assertFalse($service->hasUnclassified($files));
        $this->assertFalse($service->hasBreaking($files));
    }

    public function test_service_reports_breaking_classification(): void
    {
        $this->writeMigrationFixture('2026_09_03_000003_drop_legacy_column');
        File::put($this->manifestPath, json_encode(['2026_09_03_000003_drop_legacy_column' => 'breaking']));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000003_drop_legacy_column'];

        $this->assertTrue($service->hasBreaking($files));
    }

    public function test_command_fails_closed_on_unclassified_migration(): void
    {
        $this->writeMigrationFixture('2026_09_03_000004_unclassified');
        File::put($this->manifestPath, json_encode([]));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(1, $exitCode);
    }

    public function test_command_accepts_expand_only_migrations(): void
    {
        $this->writeMigrationFixture('2026_09_03_000005_expand_only');
        File::put($this->manifestPath, json_encode(['2026_09_03_000005_expand_only' => 'expand']));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(0, $exitCode);
    }

    public function test_command_rejects_breaking_migration_without_allow_breaking_flag(): void
    {
        $this->writeMigrationFixture('2026_09_03_000006_breaking');
        File::put($this->manifestPath, json_encode(['2026_09_03_000006_breaking' => 'breaking']));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(2, $exitCode);
    }

    public function test_command_rejects_breaking_migration_with_flag_but_no_maintenance_mode(): void
    {
        $this->writeMigrationFixture('2026_09_03_000007_breaking_flagged');
        File::put($this->manifestPath, json_encode(['2026_09_03_000007_breaking_flagged' => 'breaking']));

        $this->assertFalse(app()->isDownForMaintenance());

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
            '--allow-breaking' => true,
        ])->run();

        $this->assertSame(3, $exitCode);
    }

    public function test_command_never_invokes_migrate_rollback(): void
    {
        $source = File::get(app_path('Console/Commands/DeployMigrateCommand.php'));
        $this->assertStringNotContainsString('migrate:rollback', $source);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter=DeployMigrateCommandTest`
Expected: FAIL — class `App\Services\Deployment\MigrationClassificationService` and command `deploy:migrate` do not exist.

- [ ] **Step 4: Implement the service**

Create `app/Services/Deployment/MigrationClassificationService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services\Deployment;

class MigrationClassificationService
{
    private array $manifest;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $migrationsPath,
    ) {
        $raw = is_file($this->manifestPath) ? file_get_contents($this->manifestPath) : '{}';
        $decoded = json_decode($raw ?: '{}', true);
        $this->manifest = is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, string> migration file basenames (no .php) present on disk but not yet recorded as ran.
     */
    public function pendingMigrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/') . '/*.php') ?: [];
        $names = array_map(static fn (string $f): string => basename($f, '.php'), $files);
        sort($names);

        $ran = \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();

        return array_values(array_diff($names, $ran));
    }

    /**
     * @param array<int, string> $files
     * @return array<string, string|null>
     */
    public function classificationsFor(array $files): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[$file] = $this->manifest[$file] ?? null;
        }
        return $result;
    }

    public function hasUnclassified(array $files): bool
    {
        return in_array(null, $this->classificationsFor($files), true);
    }

    public function hasBreaking(array $files): bool
    {
        return in_array('breaking', $this->classificationsFor($files), true);
    }
}
```

- [ ] **Step 5: Implement the command**

Create `app/Console/Commands/DeployMigrateCommand.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Deployment\MigrationClassificationService;
use Illuminate\Console\Command;

/**
 * GAP-049: enforces the expand-vs-breaking migration classification contract
 * before any migration runs in a production deployment. Never invokes
 * migrate:rollback. See docs/runbooks/gap-049-migration-safety.md.
 */
class DeployMigrateCommand extends Command
{
    protected $signature = 'deploy:migrate
        {--allow-breaking : Permit classified breaking migrations to run (requires maintenance mode already active)}
        {--manifest= : Override path to classifications.json (testing only)}
        {--migrations-path= : Override migrations directory (testing only)}';

    protected $description = 'Run pending migrations only after verifying every migration is classified (GAP-049 migration safety contract)';

    public function handle(): int
    {
        $manifestPath = $this->option('manifest') ?: database_path('migrations/classifications.json');
        $migrationsPath = $this->option('migrations-path') ?: database_path('migrations');

        $service = new MigrationClassificationService($manifestPath, $migrationsPath);
        $pending = $service->pendingMigrationFiles();

        if ($pending === []) {
            $this->info('No pending migrations.');
            return 0;
        }

        if ($service->hasUnclassified($pending)) {
            $unclassified = array_keys(array_filter(
                $service->classificationsFor($pending),
                static fn ($v) => $v === null
            ));
            $this->error('Unclassified pending migration(s), refusing to run: ' . implode(', ', $unclassified));
            $this->error('Add an entry to ' . $manifestPath . ' ("expand" or "breaking") before deploying.');
            return 1;
        }

        $hasBreaking = $service->hasBreaking($pending);

        if ($hasBreaking && !$this->option('allow-breaking')) {
            $this->error('Breaking migration(s) present in this deploy — refusing to run as an ordinary pre-cutover migration.');
            $this->error('Follow docs/runbooks/gap-049-migration-safety.md: classify, backup, enter maintenance mode, then re-run with --allow-breaking.');
            return 2;
        }

        if ($hasBreaking && $this->option('allow-breaking') && !$this->laravel->isDownForMaintenance()) {
            $this->error('Breaking migration(s) require the application to be in maintenance mode before running. Run `php artisan down` first.');
            return 3;
        }

        $this->call('migrate', ['--force' => true, '--isolated' => true]);

        return 0;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=DeployMigrateCommandTest`
Expected: PASS (8/8).

- [ ] **Step 7: Write the migration-safety runbook referenced by the error messages**

Create `docs/runbooks/gap-049-migration-safety.md` (content specified in Task 9, Step 2 — write a placeholder-free stub now referencing the classification manifest so the command's error message resolves to a real file; full runbook content is completed in Task 9):

```markdown
# GAP-049 Migration Safety Runbook

See Task 9 (`docs/runbooks/gap-049-migration-safety.md`) for the complete breaking-migration procedure. This file is created here so `deploy:migrate`'s error message points at a real path from the first commit that references it; full content lands in the Task 9 commit.
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/classifications.json app/Services/Deployment/MigrationClassificationService.php app/Console/Commands/DeployMigrateCommand.php tests/Feature/Deployment/DeployMigrateCommandTest.php docs/runbooks/gap-049-migration-safety.md
git commit -m "feat(GAP-049): enforce expand-vs-breaking migration classification contract via deploy:migrate"
```

---

## Task 4: Release filesystem tooling (immutable releases, atomic switch, rollback, cleanup)

**Files:**
- Create: `scripts/deploy/lib.sh`
- Create: `scripts/deploy/verify-sha.sh`
- Create: `scripts/deploy/build-artifact.sh`
- Create: `scripts/deploy/link-shared.sh`
- Create: `scripts/deploy/activate-release.sh`
- Create: `scripts/deploy/rollback.sh`
- Create: `scripts/deploy/cleanup-releases.sh`
- Test: `tests/Feature/Deployment/ReleaseToolingTest.php`

**Interfaces:**
- Produces (all scripts take an explicit `ROOT` directory as `$1` so tests never touch `/var/www/zena`):
  - `link-shared.sh <root> <release_sha>` — symlinks `<root>/releases/<sha>/.env` → `<root>/shared/.env` and `<root>/releases/<sha>/storage` → `<root>/shared/storage`. Fails (exit 1) if `<root>/shared/.env` or `<root>/shared/storage` doesn't exist, or if `<root>/releases/<sha>` doesn't exist.
  - `activate-release.sh <root> <sha>` — atomically repoints `<root>/current` at `<root>/releases/<sha>`. Fails (exit 1) without touching `current` if `<root>/releases/<sha>` doesn't exist or isn't linked to shared state (checks the symlinks Task 4 created exist).
  - `rollback.sh <root> <target_sha>` — requires an explicit `target_sha`; fails (exit 1, no `current` change) if `<root>/releases/<target_sha>` doesn't exist; otherwise calls `activate-release.sh` with that sha.
  - `cleanup-releases.sh <root> [keep_count=3]` — deletes releases under `<root>/releases/` that are neither the `current` target nor among the `keep_count` most-recently-modified release directories; never touches anything under `<root>/shared/`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Deployment/ReleaseToolingTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReleaseToolingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/gap049-release-' . uniqid());
        File::makeDirectory($this->root . '/shared/storage', 0755, true);
        File::put($this->root . '/shared/.env', "APP_ENV=production\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function run(string $script, array $args): Process
    {
        $process = new Process(array_merge(['bash', base_path("scripts/deploy/{$script}")], $args));
        $process->run();
        return $process;
    }

    private function makeRelease(string $sha): void
    {
        File::makeDirectory($this->root . "/releases/{$sha}", 0755, true);
        File::put($this->root . "/releases/{$sha}/marker.txt", $sha);
    }

    public function test_link_shared_symlinks_env_and_storage_into_release(): void
    {
        $this->makeRelease('sha-aaa');

        $process = $this->run('link-shared.sh', [$this->root, 'sha-aaa']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/releases/sha-aaa/.env'));
        $this->assertTrue(is_link($this->root . '/releases/sha-aaa/storage'));
        $this->assertSame(
            realpath($this->root . '/shared/.env'),
            realpath($this->root . '/releases/sha-aaa/.env')
        );
    }

    public function test_link_shared_fails_when_shared_storage_missing(): void
    {
        File::deleteDirectory($this->root . '/shared/storage');
        $this->makeRelease('sha-bbb');

        $process = $this->run('link-shared.sh', [$this->root, 'sha-bbb']);

        $this->assertNotSame(0, $process->getExitCode());
    }

    public function test_activate_release_creates_atomic_current_symlink(): void
    {
        $this->makeRelease('sha-ccc');
        $this->run('link-shared.sh', [$this->root, 'sha-ccc']);

        $process = $this->run('activate-release.sh', [$this->root, 'sha-ccc']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/current'));
        $this->assertSame(
            realpath($this->root . '/releases/sha-ccc'),
            realpath($this->root . '/current')
        );
    }

    public function test_activate_release_fails_and_leaves_current_unchanged_when_release_missing(): void
    {
        $this->makeRelease('sha-ddd');
        $this->run('link-shared.sh', [$this->root, 'sha-ddd']);
        $this->run('activate-release.sh', [$this->root, 'sha-ddd']);

        $process = $this->run('activate-release.sh', [$this->root, 'sha-does-not-exist']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame(
            realpath($this->root . '/releases/sha-ddd'),
            realpath($this->root . '/current'),
            'current must remain pointed at the last good release after a failed activation attempt.'
        );
    }

    public function test_first_deployment_without_existing_current_link_succeeds(): void
    {
        $this->makeRelease('sha-first');
        $this->run('link-shared.sh', [$this->root, 'sha-first']);

        $this->assertFalse(file_exists($this->root . '/current'));

        $process = $this->run('activate-release.sh', [$this->root, 'sha-first']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/current'));
    }

    public function test_rollback_requires_explicit_target_sha_not_head_minus_one(): void
    {
        $this->makeRelease('sha-old');
        $this->makeRelease('sha-new');
        $this->run('link-shared.sh', [$this->root, 'sha-old']);
        $this->run('link-shared.sh', [$this->root, 'sha-new']);
        $this->run('activate-release.sh', [$this->root, 'sha-old']);
        $this->run('activate-release.sh', [$this->root, 'sha-new']);

        $process = $this->run('rollback.sh', [$this->root]); // no target sha argument

        $this->assertNotSame(0, $process->getExitCode(), 'rollback.sh must require an explicit target sha, not infer HEAD~1.');
    }

    public function test_rollback_selects_explicit_known_previous_release(): void
    {
        $this->makeRelease('sha-old2');
        $this->makeRelease('sha-new2');
        $this->run('link-shared.sh', [$this->root, 'sha-old2']);
        $this->run('link-shared.sh', [$this->root, 'sha-new2']);
        $this->run('activate-release.sh', [$this->root, 'sha-old2']);
        $this->run('activate-release.sh', [$this->root, 'sha-new2']);

        $process = $this->run('rollback.sh', [$this->root, 'sha-old2']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(
            realpath($this->root . '/releases/sha-old2'),
            realpath($this->root . '/current')
        );
    }

    public function test_rollback_fails_for_unknown_target_sha(): void
    {
        $this->makeRelease('sha-known');
        $this->run('link-shared.sh', [$this->root, 'sha-known']);
        $this->run('activate-release.sh', [$this->root, 'sha-known']);

        $process = $this->run('rollback.sh', [$this->root, 'sha-never-deployed']);

        $this->assertNotSame(0, $process->getExitCode());
    }

    public function test_cleanup_keeps_current_and_rollback_target_never_touches_shared(): void
    {
        foreach (['sha-1', 'sha-2', 'sha-3', 'sha-4', 'sha-5'] as $sha) {
            $this->makeRelease($sha);
            $this->run('link-shared.sh', [$this->root, $sha]);
            sleep(0); // ensure distinct mtimes ordering is source-controlled via directory creation order
        }
        $this->run('activate-release.sh', [$this->root, 'sha-5']);

        $process = $this->run('cleanup-releases.sh', [$this->root, '2']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_dir($this->root . '/releases/sha-5'), 'current release must survive cleanup');
        $this->assertTrue(is_dir($this->root . '/shared/storage'), 'shared/ must never be touched by cleanup');
        $this->assertTrue(is_file($this->root . '/shared/.env'), 'shared/.env must never be touched by cleanup');
    }

    public function test_cleanup_never_deletes_shared_even_if_root_passed_carelessly(): void
    {
        $this->makeRelease('sha-only');
        $this->run('link-shared.sh', [$this->root, 'sha-only']);
        $this->run('activate-release.sh', [$this->root, 'sha-only']);

        $this->run('cleanup-releases.sh', [$this->root, '0']);

        $this->assertTrue(is_dir($this->root . '/shared'), 'shared/ directory itself must never be removed by cleanup.');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ReleaseToolingTest`
Expected: FAIL — scripts don't exist yet (Process exit code will be 127 / command not found, or nonzero).

- [ ] **Step 3: Implement `scripts/deploy/lib.sh`**

```bash
#!/usr/bin/env bash
# Shared helpers for GAP-049 release scripts. Sourced, not executed directly.
set -euo pipefail

log() {
  echo "[deploy] $*" >&2
}

fail() {
  echo "[deploy][ERROR] $*" >&2
  exit 1
}

require_dir() {
  local dir="$1"
  local label="$2"
  [ -d "$dir" ] || fail "${label} not found: ${dir}"
}
```

- [ ] **Step 4: Implement `scripts/deploy/link-shared.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: link-shared.sh <root> <release_sha>}"
SHA="${2:?Usage: link-shared.sh <root> <release_sha>}"

RELEASE_DIR="${ROOT}/releases/${SHA}"
SHARED_ENV="${ROOT}/shared/.env"
SHARED_STORAGE="${ROOT}/shared/storage"

require_dir "$RELEASE_DIR" "release directory"
[ -f "$SHARED_ENV" ] || fail "shared .env not found: ${SHARED_ENV}"
require_dir "$SHARED_STORAGE" "shared storage directory"

ln -sfn "$SHARED_ENV" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -sfn "$SHARED_STORAGE" "${RELEASE_DIR}/storage"

log "Linked shared .env and storage into ${RELEASE_DIR}"
```

- [ ] **Step 5: Implement `scripts/deploy/activate-release.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: activate-release.sh <root> <sha>}"
SHA="${2:?Usage: activate-release.sh <root> <sha>}"

RELEASE_DIR="${ROOT}/releases/${SHA}"
require_dir "$RELEASE_DIR" "release directory"
[ -L "${RELEASE_DIR}/.env" ] || fail "release ${SHA} is not linked to shared .env — run link-shared.sh first"
[ -L "${RELEASE_DIR}/storage" ] || fail "release ${SHA} is not linked to shared storage — run link-shared.sh first"

TMP_LINK="${ROOT}/current.tmp.$$"
ln -sfn "$RELEASE_DIR" "$TMP_LINK"

# True atomic switch: rename() is a single, atomic syscall — there is never a
# moment where `current` does not exist or points at a half-written state.
# GNU mv's -T ("no target directory") disables mv's POSIX directory-following
# special case (which would otherwise move TMP_LINK *into* the directory
# `current` resolves to, rather than replacing `current` itself) and performs
# a plain rename(). Production hosts are Linux/GNU coreutils (see
# docs/runbooks/gap-049-host-provisioning.md), so this atomic path is what
# actually runs in production.
if mv --help 2>&1 | grep -q -- '-T'; then
  mv -T "$TMP_LINK" "${ROOT}/current"
else
  # Non-GNU mv (e.g. BSD/macOS — local dev/test runs of this script only,
  # never production): `ln -sfn` correctly replaces an existing
  # symlink-to-directory rather than following it, matching GNU `mv -T`'s
  # target-replacement semantics, but internally performs an unlink+symlink
  # rather than a single atomic rename, so there is a brief window (absent
  # on the production/GNU path above) where `current` does not exist.
  # Acceptable only because this branch never executes on a real deploy host.
  ln -sfn "$RELEASE_DIR" "${ROOT}/current"
  rm -f "$TMP_LINK"
fi

log "current -> releases/${SHA}"
```

- [ ] **Step 6: Implement `scripts/deploy/rollback.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: rollback.sh <root> <target_sha>}"
TARGET_SHA="${2:?Usage: rollback.sh <root> <target_sha> — an explicit target release is required, HEAD~1 inference is forbidden}"

require_dir "${ROOT}/releases/${TARGET_SHA}" "rollback target release"

log "Rolling back to explicit target release ${TARGET_SHA}"
"${DIR}/activate-release.sh" "$ROOT" "$TARGET_SHA"
```

- [ ] **Step 7: Implement `scripts/deploy/cleanup-releases.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: cleanup-releases.sh <root> [keep_count]}"
KEEP_COUNT="${2:-3}"

RELEASES_DIR="${ROOT}/releases"
require_dir "$RELEASES_DIR" "releases directory"

CURRENT_TARGET=""
if [ -L "${ROOT}/current" ]; then
  CURRENT_TARGET="$(basename "$(readlink -f "${ROOT}/current")")"
fi

mapfile -t SORTED < <(cd "$RELEASES_DIR" && ls -1t)

KEEP=()
[ -n "$CURRENT_TARGET" ] && KEEP+=("$CURRENT_TARGET")

count=0
for name in "${SORTED[@]}"; do
  if [ "$count" -lt "$KEEP_COUNT" ]; then
    KEEP+=("$name")
  fi
  count=$((count + 1))
done

for name in "${SORTED[@]}"; do
  keep=false
  for k in "${KEEP[@]}"; do
    [ "$name" = "$k" ] && keep=true && break
  done
  if [ "$keep" = false ]; then
    log "Removing eligible old release: ${name}"
    rm -rf "${RELEASES_DIR:?}/${name}"
  fi
done

log "Cleanup complete. shared/ untouched by design (never referenced above)."
```

- [ ] **Step 8: Make all scripts executable**

```bash
chmod +x scripts/deploy/lib.sh scripts/deploy/link-shared.sh scripts/deploy/activate-release.sh scripts/deploy/rollback.sh scripts/deploy/cleanup-releases.sh
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --filter=ReleaseToolingTest`
Expected: PASS (10/10).

- [ ] **Step 10: Commit**

```bash
git add scripts/deploy/lib.sh scripts/deploy/link-shared.sh scripts/deploy/activate-release.sh scripts/deploy/rollback.sh scripts/deploy/cleanup-releases.sh tests/Feature/Deployment/ReleaseToolingTest.php
git commit -m "feat(GAP-049): immutable release filesystem tooling — shared links, atomic switch, explicit-target rollback, safe cleanup"
```

---

## Task 5: Queue canary (job, command, disposable real-worker drill)

**Files:**
- Create: `app/Jobs/QueueCanaryJob.php`
- Create: `app/Console/Commands/QueueCanaryCommand.php`
- Create: `scripts/deploy/queue-canary-drill.sh`
- Test: `tests/Feature/Deployment/QueueCanaryCommandTest.php`

**Interfaces:**
- Produces: `php artisan deploy:queue-canary {--timeout=30}` — dispatches `QueueCanaryJob` with a unique probe id, polls `Cache` for a completion marker keyed by that id, exits `0` on completion within timeout, exits `1` on timeout, exits `2` immediately (no dispatch) if `config('queue.default') === 'sync'`.
- `QueueCanaryJob::__construct(string $probeId)` — on `handle()`, writes `Cache::put("gap049-queue-canary-{$probeId}", true, 60)`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Deployment/QueueCanaryCommandTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Jobs\QueueCanaryJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueCanaryCommandTest extends TestCase
{
    public function test_rejects_sync_queue_connection_without_dispatching(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake();

        $exitCode = $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        $this->assertSame(2, $exitCode);
        Queue::assertNothingPushed();
    }

    public function test_dispatches_probe_job_on_non_sync_connection(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        Queue::assertPushed(QueueCanaryJob::class);
    }

    public function test_times_out_when_no_worker_processes_the_job(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake(); // fakes dispatch so nothing ever actually processes it — simulates "no worker running"

        $exitCode = $this->artisan('deploy:queue-canary', ['--timeout' => 1])->run();

        $this->assertSame(1, $exitCode);
    }

    public function test_succeeds_when_job_handle_writes_completion_marker(): void
    {
        $probeId = 'test-probe-marker-check';
        $job = new QueueCanaryJob($probeId);
        $job->handle();

        $this->assertTrue(Cache::get("gap049-queue-canary-{$probeId}") === true);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=QueueCanaryCommandTest`
Expected: FAIL — `App\Jobs\QueueCanaryJob` and command `deploy:queue-canary` do not exist.

- [ ] **Step 3: Implement the job**

Create `app/Jobs/QueueCanaryJob.php`:

```php
<?php declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * GAP-049 queue-worker liveness canary. Proves a real worker process
 * executed a real queued job — not claimed by the HTTP readiness endpoint.
 */
class QueueCanaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $probeId)
    {
    }

    public function handle(): void
    {
        Cache::put($this->markerKey(), true, 60);
    }

    public function markerKey(): string
    {
        return "gap049-queue-canary-{$this->probeId}";
    }
}
```

- [ ] **Step 4: Implement the command**

Create `app/Console/Commands/QueueCanaryCommand.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\QueueCanaryJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GAP-049: proves a real async queue worker is alive and processing jobs.
 * Deliberately fails when QUEUE_CONNECTION=sync so synchronous execution
 * can never falsely satisfy this canary.
 */
class QueueCanaryCommand extends Command
{
    protected $signature = 'deploy:queue-canary {--timeout=30 : Seconds to wait for the probe job to complete}';

    protected $description = 'Dispatch a unique probe job and poll for a real worker to complete it (GAP-049 queue canary)';

    public function handle(): int
    {
        if (config('queue.default') === 'sync') {
            $this->error('QUEUE_CONNECTION=sync cannot produce a valid queue canary result — refusing to run.');
            return 2;
        }

        $probeId = (string) Str::uuid();
        $job = new QueueCanaryJob($probeId);
        QueueCanaryJob::dispatch($probeId);

        $timeout = (int) $this->option('timeout');
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            if (Cache::get($job->markerKey()) === true) {
                $this->info("Queue canary completed by a real worker (probe {$probeId}).");
                Cache::forget($job->markerKey());
                return 0;
            }
            usleep(200_000);
        }

        $this->error("Queue canary timed out after {$timeout}s — no worker processed probe {$probeId}.");
        return 1;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=QueueCanaryCommandTest`
Expected: PASS (4/4).

- [ ] **Step 6: Create the disposable real-worker drill script**

Create `scripts/deploy/queue-canary-drill.sh` (not invoked by any CI workflow — a manual disposable-evidence tool per Gate-2 A-4/§6, run against a real non-sync queue connection with a real `queue:work` process):

```bash
#!/usr/bin/env bash
# GAP-049 disposable queue-canary drill: proves a REAL async worker process
# completes a REAL queued job. Not run by CI. Run manually against a
# disposable environment with a real database/redis queue connection.
set -euo pipefail

QUEUE_CONNECTION="${1:-database}"

# NOTE: this deliberately does NOT use `queue:work --once`. `--once` checks
# the queue exactly one time and exits immediately if it's empty at that
# instant — which races against deploy:queue-canary's own dispatch (started
# a moment later, below): if the worker's single check lands before the
# probe job is inserted, it exits having seen nothing, and the canary then
# times out even though the queue infrastructure itself is perfectly healthy
# (reproduced directly during GAP-049 Task 11 verification). `--max-jobs=1`
# gives the same "process exactly one job then stop" termination guarantee,
# but by looping/sleeping between empty checks instead of exiting on the
# first one, so the worker is still there when the job actually lands.
echo "[drill] Starting queue:work worker in the background (connection=${QUEUE_CONNECTION})..."
php artisan queue:work "$QUEUE_CONNECTION" --max-jobs=1 --timeout=15 &
WORKER_PID=$!

echo "[drill] Running deploy:queue-canary..."
set +e
php artisan deploy:queue-canary --timeout=15
RESULT=$?
set -e

# The worker exits on its own once it has processed exactly one job
# (--max-jobs=1). Wait briefly for that natural exit; if it's still running
# after the canary already finished (e.g. the canary timed out with no
# worker ever picking up the job), kill it explicitly so this script never
# leaves a background process running past its own exit.
wait "$WORKER_PID" 2>/dev/null || true
if kill -0 "$WORKER_PID" 2>/dev/null; then
  kill "$WORKER_PID" 2>/dev/null || true
fi

if [ "$RESULT" -eq 0 ]; then
  echo "[drill] PASS — a real worker processed the probe job."
else
  echo "[drill] FAIL — exit code ${RESULT}. If QUEUE_CONNECTION=sync was used, this is expected (by design) and does not count as evidence."
fi

exit "$RESULT"
```

```bash
chmod +x scripts/deploy/queue-canary-drill.sh
```

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/QueueCanaryJob.php app/Console/Commands/QueueCanaryCommand.php scripts/deploy/queue-canary-drill.sh tests/Feature/Deployment/QueueCanaryCommandTest.php
git commit -m "feat(GAP-049): queue-worker liveness canary job/command + disposable real-worker drill script"
```

---

## Task 6: Production-safe bootstrap command (strict TDD)

**Files:**
- Create: `app/Console/Commands/ProductionBootstrapCommand.php`
- Test: `tests/Feature/Deployment/ProductionBootstrapCommandTest.php`

**Interfaces:**
- Produces: `php artisan production:bootstrap {--tenant-name=} {--tenant-slug=} {--admin-email=}` — on an empty DB (`Tenant::count() === 0`), creates exactly one `Tenant` and one `User` (`is_active=true`, tenant admin role) with a securely random password (never printed to a normal log — written via `$this->line()` to the command's own stdout only, which the workflow must not echo into a persisted CI log per §12; the command itself has no way to control caller logging, so this plan's workflow task (Task 7) is responsible for not persisting that output — this command's obligation is only to generate the credential securely and never hardcode one). Exit `0` on success. Exit `1` if DB is non-empty (existing-data path — does not seed, does not error loudly, just declines cleanly for the caller to run a verification path instead). Exit `2` if a second bootstrap is attempted after a prior successful bootstrap (idempotent-fail-closed, detected via `Tenant::count() > 0` — same check as the non-empty case, which is intentional: bootstrap is a one-time-only operation regardless of *why* the DB is non-empty).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Deployment/ProductionBootstrapCommandTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstraps_real_tenant_and_admin_on_empty_database(): void
    {
        $this->assertSame(0, Tenant::count());

        $exitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Acme Construction',
            '--tenant-slug' => 'acme-construction',
            '--admin-email' => 'owner@acme-construction.example',
        ])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, Tenant::count());
        $this->assertSame('Acme Construction', Tenant::first()->name);

        $admin = User::where('email', 'owner@acme-construction.example')->first();
        $this->assertNotNull($admin);
        $this->assertSame(Tenant::first()->id, $admin->tenant_id);
        $this->assertTrue($admin->is_active);
    }

    public function test_never_creates_fixed_or_default_password(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Beta Co',
            '--tenant-slug' => 'beta-co',
            '--admin-email' => 'owner@beta-co.example',
        ])->run();

        $admin = User::where('email', 'owner@beta-co.example')->first();

        foreach (['password', 'zena1234', 'Password123', 'changeme', ''] as $forbidden) {
            $this->assertFalse(
                Hash::check($forbidden, $admin->password),
                "Bootstrap admin password must never equal a known fixed/default value ({$forbidden})."
            );
        }
    }

    public function test_never_invokes_database_seeder(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ProductionBootstrapCommand.php'));
        $this->assertStringNotContainsString('DatabaseSeeder', $source);
        $this->assertStringNotContainsString('db:seed', $source);
        $this->assertStringNotContainsString("Artisan::call('migrate:fresh --seed')", $source);
    }

    public function test_no_demo_tenant_or_user_created(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Gamma LLC',
            '--tenant-slug' => 'gamma-llc',
            '--admin-email' => 'owner@gamma-llc.example',
        ])->run();

        $this->assertSame(0, User::where('email', 'like', '%demo%')->count());
        $this->assertSame(0, Tenant::where('slug', 'like', '%demo%')->count());
        $this->assertSame(1, User::count(), 'Bootstrap must create exactly one user — no extra demo accounts.');
    }

    public function test_second_bootstrap_fails_closed_idempotent(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Delta Inc',
            '--tenant-slug' => 'delta-inc',
            '--admin-email' => 'owner@delta-inc.example',
        ])->run();

        $this->assertSame(1, Tenant::count());

        $secondExitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Delta Inc Again',
            '--tenant-slug' => 'delta-inc-again',
            '--admin-email' => 'other@delta-inc.example',
        ])->run();

        $this->assertSame(2, $secondExitCode);
        $this->assertSame(1, Tenant::count(), 'A second bootstrap attempt must never create a second tenant.');
        $this->assertSame(1, User::count());
    }

    public function test_existing_data_path_declines_without_seeding(): void
    {
        Tenant::factory()->create(['name' => 'Pre-existing Real Co']);
        $preExistingCount = Tenant::count();

        $exitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Should Not Be Created',
            '--tenant-slug' => 'should-not-be-created',
            '--admin-email' => 'nope@example.com',
        ])->run();

        $this->assertSame(1, $exitCode);
        $this->assertSame($preExistingCount, Tenant::count());
        $this->assertSame(0, Tenant::where('name', 'Should Not Be Created')->count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProductionBootstrapCommandTest`
Expected: FAIL — command `production:bootstrap` does not exist.

- [ ] **Step 3: Implement the command**

First inspect `app/Models/Tenant.php` and `app/Models/User.php` fillable fields and `app/Models/Role.php`/permission-assignment conventions already used by `tests/Traits/TenantUserFactoryTrait.php` (read that trait's `assignApiRoles` method in full before writing this command, to reuse the exact same `Role::firstOrCreate` + pivot-assignment pattern rather than inventing a new one). Create `app/Console/Commands/ProductionBootstrapCommand.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * GAP-049 production-safe first-database bootstrap. NEVER invokes
 * DatabaseSeeder. Creates only the real initial tenant and one real
 * initial administrator. No demo data, no fixed/default password.
 * Idempotent/fail-closed: refuses to run if the database is not empty.
 * See docs/owner-decisions/GAP-049/02-design.md §3a.
 */
class ProductionBootstrapCommand extends Command
{
    protected $signature = 'production:bootstrap
        {--tenant-name= : Real organization name}
        {--tenant-slug= : Real organization slug}
        {--admin-email= : Real initial administrator email}';

    protected $description = 'Bootstrap the first real production tenant and administrator (GAP-049) — never runs DatabaseSeeder, never creates demo data';

    public function handle(): int
    {
        if (Tenant::count() > 0) {
            $this->warn('Database already initialized (Tenant::count() > 0) — bootstrap refuses to run again.');
            $this->line('If this is the existing-data case, verify the real tenant/admin/RBAC state instead of bootstrapping.');
            return Tenant::query()->exists() && $this->hasEverBeenBootstrapped() ? 2 : 1;
        }

        $tenantName = $this->option('tenant-name');
        $tenantSlug = $this->option('tenant-slug');
        $adminEmail = $this->option('admin-email');

        if (!$tenantName || !$tenantSlug || !$adminEmail) {
            $this->error('--tenant-name, --tenant-slug, and --admin-email are all required.');
            return 1;
        }

        $password = Str::password(20, symbols: true);

        DB::transaction(function () use ($tenantName, $tenantSlug, $adminEmail, $password) {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'status' => 'active',
                'is_active' => true,
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $tenantName . ' Administrator',
                'email' => $adminEmail,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $role = Role::firstOrCreate(
                ['name' => 'super_admin'],
                ['scope' => 'system']
            );
            $admin->roles()->syncWithoutDetaching([$role->id]);
        });

        $this->info('Bootstrap complete.');
        $this->line("Tenant: {$tenantName} ({$tenantSlug})");
        $this->line("Administrator: {$adminEmail}");
        $this->line("Generated password (record this now, it will not be shown again): {$password}");

        return 0;
    }

    private function hasEverBeenBootstrapped(): bool
    {
        // Distinguishing "second bootstrap attempt after this command ran" from "arbitrary
        // pre-existing data" is not reliably determinable from Tenant::count() alone; both
        // cases must fail closed without seeding. Exit code 2 (idempotent no-op) vs 1
        // (existing-data verification path) both refuse to write — callers treat >0 as
        // "already initialized" and branch their own runbook accordingly. Default to the
        // stricter idempotent signal (2) since it's always safe to re-check state manually.
        return true;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass, fix the exit-code branch if the tests disagree**

Run: `php artisan test --filter=ProductionBootstrapCommandTest`

The plan's test suite expects exit code `1` specifically for "pre-existing data created by something else before bootstrap ever ran" (`test_existing_data_path_declines_without_seeding`) and exit code `2` specifically for "bootstrap ran once successfully, then ran again" (`test_second_bootstrap_fails_closed_idempotent`). The command as drafted in Step 3 cannot distinguish these from `Tenant::count()` alone. Resolve this by tracking bootstrap completion explicitly rather than inferring it:

Replace the `handle()` guard and helper with an explicit marker check using Laravel's cache (durable enough for this purpose since it only gates a one-time operation, and is queried, not trusted as the sole source of truth — `Tenant::count() > 0` remains the fail-closed backstop either way):

```php
    private const BOOTSTRAP_MARKER_KEY = 'gap049_production_bootstrap_completed';

    public function handle(): int
    {
        $alreadyBootstrapped = (bool) \Illuminate\Support\Facades\Cache::get(self::BOOTSTRAP_MARKER_KEY, false);

        if (Tenant::count() > 0) {
            if ($alreadyBootstrapped) {
                $this->warn('Bootstrap already completed previously — refusing to run again (idempotent fail-closed).');
                return 2;
            }
            $this->warn('Database already contains tenant data not created by this command — verify existing state instead of bootstrapping.');
            return 1;
        }

        // ... (tenant-name/slug/admin-email validation and creation unchanged from Step 3) ...

        \Illuminate\Support\Facades\Cache::forever(self::BOOTSTRAP_MARKER_KEY, true);

        return 0;
    }
```

Remove the now-unused `hasEverBeenBootstrapped()` method. Re-run: `php artisan test --filter=ProductionBootstrapCommandTest`
Expected: PASS (6/6).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ProductionBootstrapCommand.php tests/Feature/Deployment/ProductionBootstrapCommandTest.php
git commit -m "feat(GAP-049): production-safe first-database bootstrap command — no DatabaseSeeder, no demo data, no fixed password, idempotent fail-closed"
```

---

## Task 7: Backup/restore scripts

**Files:**
- Create: `scripts/deploy/backup.sh`
- Create: `scripts/deploy/restore.sh`

**Interfaces:**
- Produces: `backup.sh <db_name> <db_user> <backup_dir>` — writes `<backup_dir>/<db_name>-<timestamp>.sql.gz` (via `mysqldump` scoped to a single named database only, never `--all-databases`) plus `<backup_dir>/<db_name>-<timestamp>-storage.tar.gz` (tar of `shared/storage`, path passed as `$4`) plus a `.sha256` checksum file for each artifact.
- Produces: `restore.sh <sql_backup_file> <target_db_name> <db_user> [<storage_tar_file> <target_storage_dir>]` — restores the DB dump into `target_db_name` and, if storage args given, extracts the storage tarball into `target_storage_dir`.

- [ ] **Step 1: Implement `scripts/deploy/backup.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

DB_NAME="${1:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
DB_USER="${2:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
BACKUP_DIR="${3:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"
STORAGE_DIR="${4:?Usage: backup.sh <db_name> <db_user> <backup_dir> <storage_dir>}"

require_dir "$STORAGE_DIR" "storage directory"
mkdir -p "$BACKUP_DIR"
TS="$(date -u +%Y%m%dT%H%M%SZ)"

SQL_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}.sql.gz"
STORAGE_FILE="${BACKUP_DIR}/${DB_NAME}-${TS}-storage.tar.gz"

log "Backing up database '${DB_NAME}' only (no --all-databases) to ${SQL_FILE}"
mysqldump --single-transaction --quick --no-tablespaces -u "$DB_USER" "$DB_NAME" | gzip > "$SQL_FILE"

log "Backing up shared storage from ${STORAGE_DIR} to ${STORAGE_FILE}"
tar -czf "$STORAGE_FILE" -C "$(dirname "$STORAGE_DIR")" "$(basename "$STORAGE_DIR")"

# Checksums are recorded with a basename-only path (via `cd` into BACKUP_DIR
# first), not the full $SQL_FILE/$STORAGE_FILE path — sha256sum embeds the
# filename it was given into the checksum file, and restore.sh verifies by
# `cd`-ing into the artifact's directory and checking the basename. If the
# checksum recorded the full backup-time path instead, verification would
# fail the moment the artifact is copied to a different directory or host
# for restore (exactly what a real restore drill does), even though the
# artifact itself is intact.
(cd "$BACKUP_DIR" && sha256sum "$(basename "$SQL_FILE")") > "${SQL_FILE}.sha256"
(cd "$BACKUP_DIR" && sha256sum "$(basename "$STORAGE_FILE")") > "${STORAGE_FILE}.sha256"

log "Backup complete:"
log "  ${SQL_FILE} ($(sha256sum "$SQL_FILE" | cut -d' ' -f1))"
log "  ${STORAGE_FILE} ($(sha256sum "$STORAGE_FILE" | cut -d' ' -f1))"
echo "$SQL_FILE"
echo "$STORAGE_FILE"
```

- [ ] **Step 2: Implement `scripts/deploy/restore.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

SQL_FILE="${1:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
TARGET_DB="${2:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
DB_USER="${3:?Usage: restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]}"
STORAGE_FILE="${4:-}"
TARGET_STORAGE_DIR="${5:-}"

[ -f "$SQL_FILE" ] || fail "backup file not found: ${SQL_FILE}"
if [ -f "${SQL_FILE}.sha256" ]; then
  log "Verifying checksum for ${SQL_FILE}"
  (cd "$(dirname "$SQL_FILE")" && sha256sum -c "$(basename "${SQL_FILE}.sha256")")
fi

log "Restoring ${SQL_FILE} into database '${TARGET_DB}' (must already exist and be empty/disposable)"
gunzip -c "$SQL_FILE" | mysql -u "$DB_USER" "$TARGET_DB"

if [ -n "$STORAGE_FILE" ] && [ -n "$TARGET_STORAGE_DIR" ]; then
  [ -f "$STORAGE_FILE" ] || fail "storage backup file not found: ${STORAGE_FILE}"
  if [ -f "${STORAGE_FILE}.sha256" ]; then
    log "Verifying checksum for ${STORAGE_FILE}"
    (cd "$(dirname "$STORAGE_FILE")" && sha256sum -c "$(basename "${STORAGE_FILE}.sha256")")
  fi
  mkdir -p "$TARGET_STORAGE_DIR"
  log "Restoring storage into ${TARGET_STORAGE_DIR}"
  tar -xzf "$STORAGE_FILE" -C "$TARGET_STORAGE_DIR" --strip-components=1
fi

log "Restore complete."
```

```bash
chmod +x scripts/deploy/backup.sh scripts/deploy/restore.sh
```

- [ ] **Step 3: Commit**

```bash
git add scripts/deploy/backup.sh scripts/deploy/restore.sh
git commit -m "feat(GAP-049): least-privilege backup/restore scripts scoped to app DB + shared storage only, with checksums"
```

*(The restore-drill evidence proving these scripts actually work end-to-end against real MySQL in a disposable environment is executed in Task 10 as part of Gate-3 evidence collection, not as a PHPUnit test — restoring a database is an infrastructure operation, not a unit of application behavior.)*

---

## Task 8: Two-tenant isolation pre-release evidence (disposable — the test DB itself)

**Files:**
- Test: `tests/Feature/Deployment/TwoTenantIsolationEvidenceTest.php`

**Interfaces:**
- Consumes: `Tests\Traits\TenantUserFactoryTrait::createTenantUser()` (existing, read its full signature before use), `App\Models\Tenant::factory()` (existing), existing tenant-scoped models already covered by `tests/Feature/MultiTenantIsolationTest.php` (read that file in full first to match its exact model/route usage rather than inventing new ones).

- [ ] **Step 1: Read the existing isolation test conventions before writing new assertions**

Read `tests/Feature/MultiTenantIsolationTest.php` in full and `tests/Traits/TenantUserFactoryTrait.php` in full. Identify one concrete tenant-scoped resource this repo already exposes over an authenticated API route (e.g. a Project index/show endpoint) that the existing test suite already exercises for read isolation, to reuse the identical authenticated-request pattern (headers, route names) rather than guessing at route names.

- [ ] **Step 2: Write the evidence test**

Create `tests/Feature/Deployment/TwoTenantIsolationEvidenceTest.php`, using the exact route/model names discovered in Step 1 (the sketch below uses `Project`/`projects` as a placeholder name to illustrate structure — the actual implementer MUST replace it with the real authenticated project-listing/show route confirmed to exist in `routes/api.php` in Step 1, and must not invent a nonexistent route):

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-049 Gate-2 Round-2 Clarification 2, pre-release evidence half:
 * proves real negative cross-tenant isolation using at least two controlled
 * tenants exercising the real, live authorization/tenant-boundary code
 * paths — executed in this disposable test-database environment, never
 * against production, and never by manufacturing a fake production tenant.
 */
class TwoTenantIsolationEvidenceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_tenant_a_cannot_read_tenant_b_project_data(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B']);

        $userA = $this->createTenantUser($tenantA);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($userA);
        $response = $this->getJson("/api/projects/{$projectB->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_tenant_a_cannot_mutate_tenant_b_project_data(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A2']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B2']);

        $userA = $this->createTenantUser($tenantA);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Original Name']);

        Sanctum::actingAs($userA);
        $response = $this->putJson("/api/projects/{$projectB->id}", ['name' => 'Hijacked By Tenant A']);

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertSame('Original Name', $projectB->fresh()->name);
    }

    public function test_tenant_a_listing_never_includes_tenant_b_records(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A3']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B3']);

        $userA = $this->createTenantUser($tenantA);
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/projects');

        $response->assertOk();
        $ids = collect($response->json('data') ?? $response->json())->pluck('id')->all();
        $this->assertContains($projectA->id, $ids);
        $this->assertNotContains($projectB->id, $ids);
    }
}
```

- [ ] **Step 3: Run tests, adjust route/response-shape assumptions to match reality**

Run: `php artisan test --filter=TwoTenantIsolationEvidenceTest`

If the actual route names, auth guard (Sanctum vs session), or list-response envelope shape (`data` key vs bare array) differ from the sketch, fix the test to match what `tests/Feature/MultiTenantIsolationTest.php` actually uses (read it again if any assertion is wrong) — do not weaken the assertions themselves (403/404 for cross-tenant read/write, tenant B never appearing in tenant A's list) to make the test pass.

Expected: PASS (3/3) once route/shape details match the real API.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Deployment/TwoTenantIsolationEvidenceTest.php
git commit -m "test(GAP-049): pre-release two-tenant negative isolation evidence (read+write+list) per Gate-2 Clarification 2"
```

---

## Task 9: Runbooks (host provisioning, migration safety, backup/restore)

**Files:**
- Create: `docs/runbooks/gap-049-host-provisioning.md`
- Modify (complete the stub from Task 3): `docs/runbooks/gap-049-migration-safety.md`
- Create: `docs/runbooks/gap-049-backup-restore.md`

**Interfaces:** none (documentation only) — but content must be exact, not placeholder, per Global Constraints.

- [ ] **Step 1: Write `docs/runbooks/gap-049-host-provisioning.md`**

```markdown
# GAP-049 Host Provisioning Runbook

One-time checklist for provisioning a production host for ZENA's Candidate A
(hardened release-based SSH) deployment architecture. This document
describes the checklist; it does not provision any real host — provisioning
a real host is an external Owner-supplied action outside this repository's
automation, per GAP-049 Gate-2 §5.

## Assumptions

- Linux host (any distribution capable of running the packages below), one
  instance, single-host (multi-host/HA explicitly deferred per Gate-2 §4).
- PHP 8.2-fpm with extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql,
  dom, filter, gd, json, redis (matches `composer.json:11` and
  `.github/workflows/production.yml`'s `PHP_VERSION: '8.2'` / extension list).
- Composer 2.x, Node matching the version pinned in `Dockerfile.prod`.
- nginx, terminating TLS via an ACME-based certificate mechanism (e.g.
  Let's Encrypt/certbot) provisioned once during setup, not per-deploy.
- MySQL 8.0 (matches CI service images across `automated-testing.yml`,
  `ci-cd.yml`, `routes-guardrails.yml`).
- Redis, if `QUEUE_CONNECTION`/`CACHE_DRIVER`/`SESSION_DRIVER` are set to
  `redis` in `shared/.env` (recommended — `sync` queue is explicitly
  rejected by the GAP-049 queue canary, see `app/Console/Commands/QueueCanaryCommand.php`).

## Directory layout (fixed contract, see Gate-2 design §A-3)

```
/var/www/zena/
  current -> releases/<exact-sha>/
  releases/<exact-sha>/
  shared/.env
  shared/storage/
```

Create `shared/.env` and `shared/storage/` once, before the first release is
ever deployed:

```bash
sudo mkdir -p /var/www/zena/releases /var/www/zena/shared/storage
sudo touch /var/www/zena/shared/.env   # populate with real production values out-of-band, never commit
sudo chmod 600 /var/www/zena/shared/.env
```

## Services

- **queue-worker systemd unit** (currently absent per Gate-1 finding) — one
  unit running `php artisan queue:work <connection> --sleep=3 --tries=3`
  against `current/artisan`, `Restart=always`.
- **websocket systemd unit** (currently assumed but never provisioned per
  Gate-1 finding) — one unit running whatever websocket server command this
  application uses in production, `Restart=always`.
- nginx vhost pointing its document root at `current/public`.

## Deployment user and sudo scope

Create a dedicated, non-root `deploy` user. Grant it, via `visudo -f
/etc/sudoers.d/zena-deploy`, **exactly**:

```
deploy ALL=(root) NOPASSWD: /bin/systemctl reload nginx
deploy ALL=(root) NOPASSWD: /bin/systemctl restart zena-queue-worker
deploy ALL=(root) NOPASSWD: /bin/systemctl restart zena-websocket
```

Nothing broader (per Gate-2 design A-7/A-10). The `deploy` user owns
`releases/*` and `shared/storage` (read/write); it needs only read access to
`shared/.env`. The web server user (e.g. `www-data`) needs read access to
`current` and read/write access to `shared/storage`.

## SSH / host-key verification

Pin `appleboy/ssh-action`'s host fingerprint explicitly (do not rely on
default `StrictHostKeyChecking` behavior) — obtain it once via
`ssh-keyscan <host>` from a trusted channel and store it as the
`PRODUCTION_HOST_KEY_FINGERPRINT` GitHub secret, referenced by
`.github/workflows/production.yml`'s `fingerprint:` input to
`appleboy/ssh-action`. Host-key verification must fail closed — never
`StrictHostKeyChecking=no`.

## Exact-SHA delivery credential contract

Per Gate-2 Round-2 Clarification 1, this architecture uses mechanism (a):
CI checks out, verifies, and builds the exact requested SHA, then transfers
the already-built release artifact to the host over the same SSH channel
already used for the deploy step. **The production host requires no git
credential of any kind** — it never independently fetches from GitHub. This
closes the Gate-1 `git pull origin main` hazard structurally, not by policy.

## Backup destination

An off-host location (separate volume, remote object store, or
provider-native snapshot mechanism) — the specific provider is an external
Owner input (Gate-2 §5) not fixed here. Whatever is chosen, it must not
share a single-disk failure domain with `/var/www/zena`.

## Log rotation

`shared/storage/logs/laravel.log` (shared per A-3, so it survives every
deploy) — configure standard `logrotate` on the host; full APM/Sentry
adoption is deferred (Gate-2 A-9), not a blocker for first deployment.

## First bootstrap

Run `php artisan production:bootstrap --tenant-name=<real> --tenant-slug=<real> --admin-email=<real>`
exactly once against an empty database — see
`app/Console/Commands/ProductionBootstrapCommand.php` and Gate-2 §3a. Never
run `php artisan db:seed`.

## Deployment procedure

See `.github/workflows/production.yml` (the sole authoritative production
entry point after GAP-049) — `workflow_dispatch` with an exact release SHA
input; see that workflow file for the exact step sequence.

## Migration classification

See `docs/runbooks/gap-049-migration-safety.md`.

## Rollback/recovery and restore drill

See `docs/runbooks/gap-049-backup-restore.md` for backup/restore. For code
rollback: `scripts/deploy/rollback.sh /var/www/zena <explicit-target-sha>`
— an explicit target SHA is always required (see
`scripts/deploy/rollback.sh`, which fails closed without one).

## Production smoke

See Gate-2 design §6 for the acceptance sequence (auth, tenant scoping,
RBAC, DB write persistence, queue canary, storage round-trip, critical-page
200s) — implemented as the production workflow's post-cutover smoke step.
```

- [ ] **Step 2: Replace the Task-3 stub with the full `docs/runbooks/gap-049-migration-safety.md`**

```markdown
# GAP-049 Migration Safety Runbook

Enforced in code by `app/Services/Deployment/MigrationClassificationService.php`
and `app/Console/Commands/DeployMigrateCommand.php` — this document is the
human-readable procedure that command's error messages point to.

## Classification (required before every deploy containing migrations)

Every migration file must have an entry in `database/migrations/classifications.json`:
`"expand"` or `"breaking"`. `deploy:migrate` fails closed (exit 1) if any
pending migration lacks an entry — classification is never informal or
assumed.

- **expand**: the *old, still-`current`* release's code can tolerate the
  change unchanged (new nullable column, new table, new compatible index).
  Runs pre-cutover, from the new release, against the shared database — the
  old release keeps serving correctly during that window.
- **breaking**: the current code would break against the migrated schema
  (dropping/renaming a column still read/written by current code,
  destructive data conversion, incompatible constraint). Requires the full
  procedure below — `deploy:migrate` refuses to run it as an ordinary
  pre-cutover step (exit 2 without `--allow-breaking`, exit 3 with
  `--allow-breaking` but no active maintenance mode).

## Breaking-migration procedure (manual, in this order)

1. Classify the migration `"breaking"` in `database/migrations/classifications.json`
   *before* triggering the deploy — this is a code review decision, not a
   deploy-time judgment call.
2. Take a fresh backup: `scripts/deploy/backup.sh <db_name> <db_user> <backup_dir> <storage_dir>`
   (see `docs/runbooks/gap-049-backup-restore.md`). Record the resulting
   `.sql.gz`/`.sha256` paths — they are the recovery point for this specific
   migration.
3. Enter maintenance mode on the **currently-serving** release (this affects
   real traffic immediately): `php artisan down` — writes the maintenance
   flag into `shared/storage/framework/down` (shared per A-3, so it is
   visible to whichever release is `current`, not orphaned in a
   not-yet-active new release directory).
4. Write (or open, if pre-written for a known migration) a
   migration-specific forward/rollback/data-fix runbook describing exactly
   what this migration does and how to reverse or repair it if it fails
   partway — do this *before* running the migration, not improvised after a
   failure.
5. Run `php artisan deploy:migrate --allow-breaking` (this only proceeds
   because `app()->isDownForMaintenance()` is now true, per Step 3).
6. Complete the code cutover (`scripts/deploy/activate-release.sh`).
7. Run the readiness check (`GET /api/v1/public/production/ready`) — it
   must return 200 before maintenance mode is lifted.
8. Lift maintenance mode: `php artisan up`.

## Rollback semantics (never automatic `migrate:rollback`)

- **After an expand migration**: `scripts/deploy/rollback.sh` (code-only,
  re-pointing `current` to the explicit prior release SHA) is sufficient —
  the prior code already tolerates the expanded schema; nothing further is
  needed.
- **After a breaking migration**: "rollback" is not simply switching
  `current`. It requires maintenance mode (already active per Step 3 above)
  plus the migration-specific data/schema recovery procedure written in
  Step 4. No automatic schema-rollback mechanism exists or is invented by
  this repository — `grep -rn "migrate:rollback" app/ scripts/` must return
  no matches in production code paths (verified by
  `tests/Feature/Deployment/DeployMigrateCommandTest::test_command_never_invokes_migrate_rollback`).

## Concurrency

Deployment-level serialization (no two `workflow_dispatch` production
deploys running concurrently) is enforced by
`.github/workflows/production.yml`'s `concurrency: { group: production-deploy, cancel-in-progress: false }`
block. `migrate --isolated` (used inside `deploy:migrate`) is a *separate*
mechanism — a migration-command mutex only, not a substitute for workflow
serialization.
```

- [ ] **Step 3: Write `docs/runbooks/gap-049-backup-restore.md`**

```markdown
# GAP-049 Backup/Restore Runbook

## Scope

- The ZENA production application database only — never
  `mysqldump --all-databases` (see `scripts/deploy/backup.sh`, which takes
  an explicit single `<db_name>` argument).
- Shared persistent application storage (`shared/storage`) — uploads,
  documents, logs.
- Any other mutable production state identified as necessary for recovery
  is a Gate-3/host-provisioning-time decision, not invented here.

## Mechanism

`scripts/deploy/backup.sh <db_name> <db_user> <backup_dir> <storage_dir>` —
produces a gzip'd `mysqldump` (`--single-transaction --quick --no-tablespaces`,
scoped to `<db_name>` only) and a gzip'd tar of `<storage_dir>`, each with a
`sha256sum` checksum file alongside it.

Least-privilege credential: the `<db_user>` passed to `backup.sh` should be
a dedicated backup credential scoped to `SELECT`/`LOCK TABLES`/`SHOW VIEW`
on the ZENA application database only — not a broad administrative MySQL
account. Provisioning that credential is a host-provisioning-time (external)
step, not something this repository can create for a real host.

## Durability (off-host)

`backup.sh` writes to whatever `<backup_dir>` argument it's given — it is
the caller's (production workflow's / host cron's) responsibility to pass a
path that is **not** on the same disk as the running application (a
separate volume, a remote object store synced after the local write, or a
provider-native snapshot target). The exact off-host destination is an
external Owner input (Gate-2 §5) — this repository does not fabricate one.

## Restore

`scripts/deploy/restore.sh <sql_backup.sql.gz> <target_db> <db_user> [<storage_tar.tar.gz> <target_storage_dir>]`
— verifies the `.sha256` checksum (if present) before restoring, restores
the SQL dump into `<target_db>` (which must already exist and be a
disposable/empty target, never the live production database used to
"prove" restore works), and optionally extracts the storage tarball.

## Mandatory restore-drill evidence (required before this architecture is accepted as complete)

Executed in a disposable, non-production environment — see the GAP-049
Gate-3 evidence record (`docs/owner-decisions/GAP-049/03-release.md`) for
the actual drill run: representative DB rows created, a representative
uploaded file created, `backup.sh` run, both artifacts restored into a
clean disposable MySQL database and a clean disposable storage directory
via `restore.sh`, then both the representative DB row and the representative
file are read back and their content verified byte-for-byte / value-for-value
identical to the originals. Production data is never destroyed or mutated
to produce this evidence.

## Retention / encryption / access

- Retention: a rolling N most-recent backups (exact N is a Gate-3/host
  cron-schedule decision, not fixed by this repository).
- Encryption at rest: wherever the chosen off-host storage mechanism
  supports it (e.g. server-side encryption on an object store, or an
  encrypted volume) — not implemented by `backup.sh` itself, which is
  storage-mechanism-agnostic.
- Access: restricted to the same credential-holder set as production SSH
  access (see `docs/runbooks/gap-049-host-provisioning.md`), not broadened
  separately.
```

- [ ] **Step 4: Commit**

```bash
git add docs/runbooks/gap-049-host-provisioning.md docs/runbooks/gap-049-migration-safety.md docs/runbooks/gap-049-backup-restore.md
git commit -m "docs(GAP-049): host provisioning, migration safety, and backup/restore runbooks"
```

---

## Task 10: Hardened `production.yml` — the single authoritative production entry point

**Files:**
- Rewrite: `.github/workflows/production.yml`

**Interfaces:**
- Produces: the workflow described exhaustively below. This is the task that actually wires Tasks 2–7's PHP/bash artifacts into a live (but never-executed-by-this-plan) deployment entry point.

- [ ] **Step 1: Rewrite `.github/workflows/production.yml`**

Replace the entire file content with:

```yaml
name: Production Deployment

on:
  workflow_dispatch:
    inputs:
      sha:
        description: 'Exact commit SHA to deploy (must be reachable from main)'
        required: true
        type: string
      allow_breaking_migrations:
        description: 'Set true only if this SHA includes migrations classified "breaking" AND maintenance mode has been manually enabled on the host first (see docs/runbooks/gap-049-migration-safety.md)'
        required: false
        type: boolean
        default: false

concurrency:
  group: production-deploy
  cancel-in-progress: false

env:
  PHP_VERSION: '8.2'

jobs:
  verify-sha:
    runs-on: ubuntu-latest
    outputs:
      verified_sha: ${{ steps.verify.outputs.sha }}
    steps:
      - uses: actions/checkout@v5
        with:
          fetch-depth: 0
          ref: ${{ github.event.inputs.sha }}

      - name: Verify checked-out SHA matches requested input exactly
        id: verify
        run: |
          ACTUAL_SHA="$(git rev-parse HEAD)"
          REQUESTED_SHA="${{ github.event.inputs.sha }}"
          if [ "$ACTUAL_SHA" != "$REQUESTED_SHA" ]; then
            echo "::error::Checked-out SHA ($ACTUAL_SHA) does not match requested input ($REQUESTED_SHA)"
            exit 1
          fi
          echo "sha=${ACTUAL_SHA}" >> "$GITHUB_OUTPUT"

      - name: Verify SHA is reachable from canonical main (fail closed on unmerged/arbitrary commits)
        run: |
          git fetch origin main --depth=1000
          if ! git merge-base --is-ancestor "${{ steps.verify.outputs.sha }}" origin/main; then
            echo "::error::${{ steps.verify.outputs.sha }} is not reachable from origin/main — refusing to deploy an unapproved/unmerged commit."
            exit 1
          fi

  build:
    needs: verify-sha
    runs-on: ubuntu-latest
    outputs:
      artifact_sha256: ${{ steps.checksum.outputs.sha256 }}
    steps:
      - uses: actions/checkout@v5
        with:
          ref: ${{ needs.verify-sha.outputs.verified_sha }}

      - name: Setup PHP ${{ env.PHP_VERSION }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, dom, filter, gd, json, redis

      - name: Install PHP dependencies (production, no dev)
        run: composer install --no-dev --optimize-autoloader --prefer-dist --no-progress

      - name: Install and build frontend assets
        run: |
          npm ci
          npm run build

      - name: Exclude secrets/runtime state from the release artifact
        run: |
          rm -f .env .env.testing .env.example
          rm -rf storage/framework/cache/* storage/framework/sessions/* storage/framework/views/* storage/logs/*

      - name: Package immutable release artifact bound to exact SHA
        id: package
        run: |
          ARTIFACT="release-${{ needs.verify-sha.outputs.verified_sha }}.tar.gz"
          tar -czf "$ARTIFACT" \
            --exclude='.git' \
            --exclude='node_modules' \
            --exclude='tests' \
            .
          echo "artifact=${ARTIFACT}" >> "$GITHUB_OUTPUT"

      - name: Compute and verify artifact checksum
        id: checksum
        run: |
          sha256sum "${{ steps.package.outputs.artifact }}" > "${{ steps.package.outputs.artifact }}.sha256"
          sha256sum -c "${{ steps.package.outputs.artifact }}.sha256"
          echo "sha256=$(cut -d' ' -f1 "${{ steps.package.outputs.artifact }}.sha256")" >> "$GITHUB_OUTPUT"

      - name: Upload release artifact
        uses: actions/upload-artifact@v4
        with:
          name: release-${{ needs.verify-sha.outputs.verified_sha }}
          path: |
            release-${{ needs.verify-sha.outputs.verified_sha }}.tar.gz
            release-${{ needs.verify-sha.outputs.verified_sha }}.tar.gz.sha256
          retention-days: 7

  deploy:
    needs: [verify-sha, build]
    runs-on: ubuntu-latest
    environment: production
    outputs:
      deploy_state: ${{ steps.set-state.outputs.state }}
    steps:
      - name: Gate production secrets — report not_configured truthfully, never a bare skip
        id: gate
        run: |
          missing=""
          for name in PRODUCTION_HOST PRODUCTION_USER PRODUCTION_SSH_KEY PRODUCTION_URL PRODUCTION_HOST_KEY_FINGERPRINT; do
            val="${!name}"
            if [ -z "$val" ]; then missing="${missing} ${name}"; fi
          done
          if [ -n "$missing" ]; then
            echo "::warning::Production deployment not_configured — missing secrets:${missing}. This run reports not_configured, never a false 'success'."
            echo "ready=false" >> "$GITHUB_OUTPUT"
          else
            echo "ready=true" >> "$GITHUB_OUTPUT"
          fi
        env:
          PRODUCTION_HOST: ${{ secrets.PRODUCTION_HOST }}
          PRODUCTION_USER: ${{ secrets.PRODUCTION_USER }}
          PRODUCTION_SSH_KEY: ${{ secrets.PRODUCTION_SSH_KEY }}
          PRODUCTION_URL: ${{ secrets.PRODUCTION_URL }}
          PRODUCTION_HOST_KEY_FINGERPRINT: ${{ secrets.PRODUCTION_HOST_KEY_FINGERPRINT }}

      - name: Report not_configured and stop
        if: steps.gate.outputs.ready != 'true'
        run: |
          echo "::error::Production deployment state: not_configured (required secrets absent). No deployment was attempted."
          echo "state=not_configured" >> "$GITHUB_OUTPUT"
          exit 1
        id: not-configured

      - uses: actions/download-artifact@v4
        if: steps.gate.outputs.ready == 'true'
        with:
          name: release-${{ needs.verify-sha.outputs.verified_sha }}

      - name: Record attempted state
        if: steps.gate.outputs.ready == 'true'
        run: echo "Production deployment state: attempted (sha=${{ needs.verify-sha.outputs.verified_sha }})"

      - name: Transfer verified release artifact to host over authenticated SSH channel
        if: steps.gate.outputs.ready == 'true'
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          fingerprint: ${{ secrets.PRODUCTION_HOST_KEY_FINGERPRINT }}
          source: "release-${{ needs.verify-sha.outputs.verified_sha }}.tar.gz,release-${{ needs.verify-sha.outputs.verified_sha }}.tar.gz.sha256,scripts/deploy/*.sh"
          target: "/tmp/zena-deploy-${{ needs.verify-sha.outputs.verified_sha }}"
          strip_components: 0

      - name: Activate release on host (checksum verify, extract, link shared, migrate, atomic switch, readiness, queue canary)
        if: steps.gate.outputs.ready == 'true'
        id: activate
        uses: appleboy/ssh-action@v1.2.4
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          fingerprint: ${{ secrets.PRODUCTION_HOST_KEY_FINGERPRINT }}
          script: |
            set -euo pipefail
            SHA="${{ needs.verify-sha.outputs.verified_sha }}"
            STAGE="/tmp/zena-deploy-${SHA}"
            ROOT="/var/www/zena"

            cd "$STAGE"
            sha256sum -c "release-${SHA}.tar.gz.sha256"

            mkdir -p "${ROOT}/releases/${SHA}"
            tar -xzf "release-${SHA}.tar.gz" -C "${ROOT}/releases/${SHA}"

            chmod +x "${STAGE}/scripts/deploy/"*.sh
            "${STAGE}/scripts/deploy/link-shared.sh" "$ROOT" "$SHA"

            cd "${ROOT}/releases/${SHA}"
            DEPLOY_MIGRATE_ARGS=""
            if [ "${{ github.event.inputs.allow_breaking_migrations }}" = "true" ]; then
              DEPLOY_MIGRATE_ARGS="--allow-breaking"
            fi
            php artisan deploy:migrate $DEPLOY_MIGRATE_ARGS

            "${STAGE}/scripts/deploy/activate-release.sh" "$ROOT" "$SHA"

            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            sudo systemctl reload nginx
            sudo systemctl restart zena-queue-worker
            sudo systemctl restart zena-websocket

            echo "cutover complete — state: deployed_unverified"

            "${STAGE}/scripts/deploy/cleanup-releases.sh" "$ROOT" 3
            rm -rf "$STAGE"

      - name: Set deployed_unverified state (cutover occurred, not yet health-checked)
        if: steps.gate.outputs.ready == 'true' && steps.activate.outcome == 'success'
        run: echo "Production deployment state: deployed_unverified (sha=${{ needs.verify-sha.outputs.verified_sha }})"

      - name: Post-cutover readiness check (only state that may be called a successful deployment)
        if: steps.gate.outputs.ready == 'true' && steps.activate.outcome == 'success'
        id: readiness
        run: |
          sleep 10
          if curl -fsS "${{ secrets.PRODUCTION_URL }}/api/v1/public/production/ready"; then
            echo "state=health_verified" >> "$GITHUB_OUTPUT"
          else
            echo "state=failed" >> "$GITHUB_OUTPUT"
            exit 1
          fi

      - name: Post-cutover queue canary (worker liveness, separate from HTTP readiness)
        if: steps.gate.outputs.ready == 'true' && steps.readiness.outputs.state == 'health_verified'
        uses: appleboy/ssh-action@v1.2.4
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          fingerprint: ${{ secrets.PRODUCTION_HOST_KEY_FINGERPRINT }}
          script: |
            cd /var/www/zena/current
            php artisan deploy:queue-canary --timeout=30

      - name: Set final deployment state output
        id: set-state
        if: always()
        run: |
          if [ "${{ steps.gate.outputs.ready }}" != "true" ]; then
            echo "state=not_configured" >> "$GITHUB_OUTPUT"
          elif [ "${{ steps.readiness.outputs.state }}" = "health_verified" ]; then
            echo "state=health_verified" >> "$GITHUB_OUTPUT"
          elif [ "${{ steps.activate.outcome }}" = "success" ]; then
            echo "state=deployed_unverified" >> "$GITHUB_OUTPUT"
          else
            echo "state=failed" >> "$GITHUB_OUTPUT"
          fi

  notify:
    needs: deploy
    runs-on: ubuntu-latest
    if: always()
    steps:
      - name: Notify truthful deployment state (never a generic "success" for a skipped/unverified deploy)
        if: ${{ always() && env.SLACK_WEBHOOK_URL != '' }}
        continue-on-error: true
        uses: slackapi/slack-github-action@v2.1.1
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
        with:
          webhook: ${{ secrets.SLACK_WEBHOOK_URL }}
          webhook-type: incoming-webhook
          payload: |
            text: ":information_source: Production deployment state: ${{ needs.deploy.outputs.deploy_state }}"
            blocks:
              - type: section
                text:
                  type: mrkdwn
                  text: "*Production deployment state*: `${{ needs.deploy.outputs.deploy_state }}`\n(Only `health_verified` means a successful, usable deployment. `not_configured`/`attempted`/`failed`/`deployed_unverified` are never described as success.)"
              - type: section
                fields:
                  - type: mrkdwn
                    text: "*Repository:*\n${{ github.repository }}"
                  - type: mrkdwn
                    text: "*Requested SHA:*\n${{ github.event.inputs.sha }}"
                  - type: mrkdwn
                    text: "*Actor:*\n${{ github.actor }}"
```

- [ ] **Step 2: Validate YAML syntax**

Run: `php -r "require 'vendor/autoload.php'; Symfony\Component\Yaml\Yaml::parseFile('.github/workflows/production.yml'); echo \"OK\n\";"`
Expected: `OK`.

- [ ] **Step 3: Re-run the full guard test suite (it asserts `production.yml` is the only workflow with a live SSH step — confirm this new content still satisfies that, and that it now genuinely has one)**

Run: `php artisan test --filter=DeploymentGuardTest`
Expected: PASS (5/5) — `production.yml` now contains `appleboy/ssh-action` steps with no `if: false`, and it is the only workflow file for which that's true.

- [ ] **Step 4: Add an explicit test asserting `production.yml`'s trigger is `workflow_dispatch` only (no automatic push-to-main deploy) and requires a `sha` input**

Add this test method to `tests/Feature/Deployment/DeploymentGuardTest.php`:

```php
    public function test_production_workflow_triggers_only_on_manual_dispatch_with_required_sha_input(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/production.yml'));

        $this->assertArrayHasKey('workflow_dispatch', $yaml['on']);
        $this->assertArrayNotHasKey('push', $yaml['on'], 'production.yml must not auto-deploy on push to main during the pilot phase (GAP-049 A-1).');

        $shaInput = $yaml['on']['workflow_dispatch']['inputs']['sha'] ?? null;
        $this->assertNotNull($shaInput, 'production.yml workflow_dispatch must accept an exact sha input.');
        $this->assertTrue($shaInput['required'] ?? false, 'The sha input must be required, not optional.');
    }

    public function test_production_workflow_never_uses_git_pull_origin_main(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));
        $this->assertStringNotContainsString('git pull origin main', $content);
        $this->assertStringNotContainsString('migrate:rollback', $content);
    }

    public function test_production_workflow_has_serialization_concurrency_guard(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/production.yml'));
        $this->assertSame('production-deploy', $yaml['concurrency']['group'] ?? null);
        $this->assertFalse($yaml['concurrency']['cancel-in-progress'] ?? true);
    }
```

Run: `php artisan test --filter=DeploymentGuardTest`
Expected: PASS (8/8).

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/production.yml tests/Feature/Deployment/DeploymentGuardTest.php
git commit -m "feat(GAP-049): harden production.yml as the sole authoritative exact-SHA, human-approval-gated production deployment entry point"
```

---

## Task 11: Full verification pass

**Files:** none new — verification only.

- [ ] **Step 1: Run the full default-suite test run**

Run: `php artisan test`
Record: total tests, failures, exact failure messages if any. If any pre-existing failure unrelated to GAP-049 is found, record it explicitly as pre-existing (do not fix unrelated pre-existing failures as part of this plan; do not misattribute them to GAP-049).

- [ ] **Step 2: Run the GAP-049-specific suite in isolation**

Run: `php artisan test --filter=Deployment`
Expected: all `tests/Feature/Deployment/*` tests PASS.

- [ ] **Step 3: Run the route guard**

Run: `php artisan route:list --json | php scripts/ci/route-guard.php`
Expected: exit 0.

- [ ] **Step 4: Run Owner Governance Lint**

Run: `php scripts/ssot/owner_governance_lint.php` (use whatever exact invocation `.github/workflows/owner-governance-lint.yml` uses — read that workflow file first to get the exact command/args before running).
Expected: no violations introduced by this branch (GAP-049 Gate 2 is already `approved`; Gate 3 packet does not exist yet at this point in the plan — run again after Task 12).

- [ ] **Step 5: Run PHPStan/static analysis exactly as CI does**

Read `.github/workflows/automated-testing.yml` or the relevant CI workflow to find the exact PHPStan invocation and run it locally.
Expected: no new errors introduced by files created/modified in this plan (pre-existing baseline errors are out of scope).

- [ ] **Step 6: Validate every changed/added workflow YAML file**

Run:
```bash
php -r "
require 'vendor/autoload.php';
foreach (glob('.github/workflows/*.yml') as \$f) {
  Symfony\Component\Yaml\Yaml::parseFile(\$f);
  echo \$f . \": OK\n\";
}
"
```
Expected: `OK` for every workflow file, no exceptions.

- [ ] **Step 7: Run frontend build (release packaging depends on it per Task 10's `npm run build` step)**

Run: `npm ci && npm run build`
Expected: exits 0.

- [ ] **Step 8: Execute the disposable restore-drill evidence (requires a local disposable MySQL instance — use Docker solely as a disposable verification harness if available, per Gate-2/Owner instructions; this does NOT make Candidate A a Docker architecture)**

If Docker is available locally:
```bash
docker run --rm -d --name gap049-restore-drill -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=zena_drill -p 3307:3306 mysql:8.0
# wait for MySQL to accept connections, then:
mysql -h127.0.0.1 -P3307 -uroot -proot zena_drill -e "CREATE TABLE gap049_evidence (id INT PRIMARY KEY, note VARCHAR(255)); INSERT INTO gap049_evidence VALUES (1, 'gap-049-restore-drill-representative-row');"
mkdir -p /tmp/gap049-drill-storage && echo "gap-049-restore-drill-representative-file-content" > /tmp/gap049-drill-storage/evidence.txt
bash scripts/deploy/backup.sh zena_drill root /tmp/gap049-drill-backup /tmp/gap049-drill-storage
# (backup.sh's mysqldump invocation needs -h127.0.0.1 -P3307 -proot for this local drill — pass via MYSQL_PWD/--host env override or a drill-specific wrapper invocation; adapt the exact mysqldump/mysql flags used by backup.sh/restore.sh for this local port, do not change the scripts themselves, only the drill's connection parameters)
mysql -h127.0.0.1 -P3307 -uroot -proot -e "CREATE DATABASE zena_drill_restored;"
bash scripts/deploy/restore.sh /tmp/gap049-drill-backup/zena_drill-*.sql.gz zena_drill_restored root /tmp/gap049-drill-backup/zena_drill-*-storage.tar.gz /tmp/gap049-drill-storage-restored
mysql -h127.0.0.1 -P3307 -uroot -proot zena_drill_restored -e "SELECT * FROM gap049_evidence;"
cat /tmp/gap049-drill-storage-restored/evidence.txt
docker stop gap049-restore-drill
```
Record: the exact commands run, the checksum(s) produced, and the verified restored row/file content, verbatim, for the Gate-3 packet. If Docker (or any local MySQL) is genuinely unavailable in this environment, do not fabricate this evidence — record explicitly in the Gate-3 packet that the restore drill is an unproven blocker requiring an environment with MySQL available.

- [ ] **Step 9: Execute the disposable queue-canary drill (requires a local non-sync queue backend — `database` queue connection works with just MySQL/SQLite, no Redis required)**

```bash
cp .env.example .env.testing.canary
php artisan key:generate --env=testing.canary --force
APP_ENV=testing.canary QUEUE_CONNECTION=database php artisan queue:table 2>/dev/null || true
APP_ENV=testing.canary QUEUE_CONNECTION=database php artisan migrate --force --env=testing.canary 2>&1 | tail -5
APP_ENV=testing.canary QUEUE_CONNECTION=database bash scripts/deploy/queue-canary-drill.sh database
```
Record the exit code and output verbatim. If this genuinely cannot be run in the available environment, record it explicitly as an unproven blocker — do not fabricate a pass.

---

## Task 12: Gate-3 packet and Draft implementation PR

**Files:**
- Create: `docs/owner-decisions/GAP-049/03-release.md`

- [ ] **Step 1: Read the exact owner-governance-lint schema requirements for Gate 3**

Read `scripts/ssot/owner_governance_lint.php` in full (already partially inspected during research) to confirm the exact required frontmatter keys for `gate: 3`, especially `technical_readiness`, `owner_decision_binding`, and `required_owner_decision_binding_fields` for this repo version, and confirm the digest function name/signature (`owner_governance_compute_implementation_tree_digest`) has not changed shape since the research pass.

- [ ] **Step 2: Compute the implementation subject SHA and tree digest**

```bash
git add -A
git status --short   # confirm everything intended is staged; nothing production-secret is staged
git commit -m "feat(GAP-049): [final task commit message per whatever remains uncommitted]"   # only if anything remains uncommitted after Tasks 1-11's own commits
SUBJECT_SHA="$(git rev-parse HEAD)"
echo "$SUBJECT_SHA"
php -r "
require 'vendor/autoload.php';
require 'scripts/ssot/owner_governance_lint.php';
echo owner_governance_compute_implementation_tree_digest('$SUBJECT_SHA', 'GAP-049', getcwd()) . \"\n\";
"
```
Record both values exactly — they go into the Gate-3 packet's `technical_evidence.subject_sha` / `technical_evidence.implementation_tree_digest`, matched by `owner_decision_binding.implementation_tree_digest`.

- [ ] **Step 3: Write `docs/owner-decisions/GAP-049/03-release.md`**

Follow the exact frontmatter schema confirmed in Step 1 (matching the shape already observed in `docs/owner-decisions/GAP-042/03-release.md`), with:
- `work_id: GAP-049`, `gate: 3`
- `gate_status: awaiting_owner`
- `technical_readiness.value:` `ready` if Task 11's verification passed cleanly (including the restore drill and queue-canary drill, or an explicit honest statement of which could not be run in this environment), otherwise `blocked_technical` with the exact reason
- `owner_decision.value: none`, `owner_decision.authority: human_owner`
- `decision_requested:` a clear, specific decision-request string (non-null, since `gate_status: awaiting_owner`)
- `references.spec`, `references.plan` (this file's path), `references.branch: impl/GAP-049-production-deployment`, `references.pr` (filled in after Step 5 below)
- `decision_provenance.recorded_by: agent`, honest `trust_level`, no fabricated `owner_response_reference`
- `technical_evidence.subject_sha` / `implementation_tree_digest` from Step 2
- `owner_decision_binding.implementation_tree_digest` identical to the above (per the lint's stale-digest-mismatch rule)

Body content (write in full, no placeholders): summarize exactly what was implemented per Task 1-10 (file-by-file), exactly what evidence Task 11 produced (counts, pass/fail, restore-drill and queue-canary-drill results verbatim or an honest statement they were not runnable here), a `## What this packet authorizes now` section stating explicitly: repository-side implementation is proposed for Owner technical review; no production deployment, secret configuration, host provisioning, or production database mutation has occurred; and a `## NOT yet proven in production` section listing verbatim: real VPS provisioning, real domain/DNS/TLS, real GitHub Environment approval config, real production secret installation, real off-host production backup destination, real production database contents, real public URL, real production deploy.

- [ ] **Step 4: Run Owner Governance Lint against the new packet**

Run the exact command found in Step 1/Task 11 Step 4.
Expected: no violations (Gate 2 for GAP-049 is `approved`, satisfying gate-ordering; this packet's own schema must be internally consistent per Step 3's requirements).

- [ ] **Step 5: Push branch and open Draft PR**

```bash
git push -u origin impl/GAP-049-production-deployment
gh pr create --draft --base main --title "GAP-049 Implementation: hardened exact-SHA production deployment" --body "$(cat <<'EOF'
Work ID: GAP-049

[full PR body summarizing Tasks 1-12, evidence, and explicit NOT-yet-proven-in-production boundary — mirror the content of docs/owner-decisions/GAP-049/03-release.md's summary and NOT-proven sections]
EOF
)"
```
Record the PR number and head SHA, then update `references.pr` in `docs/owner-decisions/GAP-049/03-release.md` to the real URL and commit that one-line update.

- [ ] **Step 6: Final check — confirm CI on the PR head is green or, if not, report exactly which check failed**

Run: `gh pr checks <PR_NUMBER>` (poll if still running) and record the exact result per check name.

- [ ] **Step 7: STOP**

Do not merge, approve, deploy, or provision anything further. This is the Owner review handoff point.

---

## Self-Review Notes (completed before execution)

- **Spec coverage:** §6 (retirement) → Task 1; §7 (authoritative workflow) → Task 10; §8 (release filesystem) → Task 4; §9 (migration safety) → Task 3; §10 (readiness) → Task 2; §11 (queue canary) → Task 5; §12 (bootstrap) → Task 6; §13 (tenant isolation) → Task 8; §14 (backup/restore) → Task 7 + Task 11 Step 8; §15 (runbooks) → Task 9; §17 (test strategy) → covered across Tasks 1-10's own test steps; §18 (verification) → Task 11; §19-21 (Gate 3 / stop boundary) → Task 12.
- **Placeholder scan:** every task has literal file content, not descriptions of content, except the two explicitly-flagged spots (Task 8 Step 2's route-name placeholder, which is deliberately flagged for the implementer to resolve against real code discovered in Step 1 of that task — not a TODO, a controlled unknown; and Task 12 Step 3's body content, which cannot be written until Task 11's real results exist).
- **Type consistency:** `MigrationClassificationService` constructor signature (`string $manifestPath, string $migrationsPath`) is identical between Task 3's Step 4 implementation and Step 2's test usage. `QueueCanaryJob::__construct(string $probeId)` and `->markerKey()` are used identically in Task 5's job, command, and tests. All release scripts consistently take `<root>` as `$1`.
