# GAP-049 Gate 1 Evidence — Production Readiness & First Controlled Deployment

**Date:** 2026-09-03 (Round 2 — corrected per Owner Gate-1 Round-1 CHANGES REQUESTED decision on pre-correction PR head `456fc625ee90b425046ac01755efee20bffcc354`; see `docs/owner-decisions/GAP-049/01-request.md` for the full decision record).
**Canonical main SHA used:** `0872ac856932193a037ce30f00050179374811af` (verified live: `git rev-parse origin/main` at time of writing equals the handoff SHA reported by the prior session — no drift, no intervening commits to inspect).
**Method:** Static repository evidence (full re-read of every referenced workflow file, not excerpts) plus exhaustive GitHub Actions run-history review via `gh api repos/.../actions/runs/<id>/jobs` for all three candidate production-deployment workflows (see §2). No deployment attempted, no secrets configured, no production database touched, no infrastructure provisioned or destroyed. GAP-042 scope (RBAC semantics) was not touched.

**Correction note (Round 2):** this revision corrects 8 factual defects identified by the Owner in Round 1: an incorrect secret-contract generalization for `deploy.yml`; a materially under-described `automated-deployment.yml` (its full staging/production/backup/rollback/blue-green/canary surface); an inconsistent deployment-surface count across the document; a wrong "no backup exists" claim; an over-broad "no rollback exists" claim; an unproven "never deployed" absolute claim; an unproven migration-locking claim; and Gate-2 architecture pre-approval framing. The underlying problem statement (CI reports false-green deploy status; no proven real production deployment) is **unchanged and Owner-accepted as directionally correct** — only the evidentiary precision below was corrected.

---

## 0. GAP-042 worktree cleanup outcome (housekeeping, not part of the technical evidence — unchanged from Round 1)

Two local worktrees left over from the GAP-042 effort were independently verified and removed:

| Worktree | Branch | HEAD | Finding | Disposition |
|---|---|---|---|---|
| `.claude/worktrees/agent-a068f9441ec6f8f40` | `worktree-agent-a068f9441ec6f8f40` | `673855f6` | `git merge-base --is-ancestor <branch> origin/main` = true — branch tip is the exact GAP-042 squash-merge commit, fully contained in `origin/main`. Clean working tree. | Backed up (`git bundle`) then `git worktree remove -f -f` + `git branch -D`. |
| `.claude/worktrees/agent-aba038bab4a22bde1` | `gap042-impl` | `2b1a05f3` | `git ls-remote --heads origin gap042-impl` → empty (zero remote trace). `git merge-base gap042-impl origin/main` = `673855f6` (the squash commit itself), and a content diff of e.g. `src/RBAC/Services/RBACManager.php` between the branch and `origin/main` shows the branch is an **earlier, less-hardened draft** of the same RBAC fix (missing the fail-closed helpers `isGenuineSystemRole()`/`revokeRoleIdentitiesValid()` that exist in the released main) — a superseded pre-squash WIP, not unmerged unique work. | Backed up (`git bundle` + patch + commit log) then `git worktree remove -f -f` + `git branch -D`. |

Both worktrees showed a `locked` flag in `git worktree list --porcelain` attributed to `pid 33562`, but that same pid was the lock-holder for essentially every worktree in the tree (including this session's own and several others clearly idle for weeks), which is consistent with stale harness lock bookkeeping rather than a live session. `git worktree remove -f -f` (git's own documented lock-override) succeeded cleanly for both; no harness sandbox block was encountered for this specific operation.

Backups: `/tmp/zena-stale-worktree-backups/20260903-gap049/{worktree-agent-a068f9441ec6f8f40,gap042-impl}.{bundle,patch,commits.txt}`.

Final state: `git worktree prune` removed 9 additional unrelated stale entries whose gitdir files pointed to non-existent locations (pre-existing debt, unrelated to GAP-042, left as-is — out of scope for this cleanup). Neither `agent-a068f9441ec6f8f40` nor `agent-aba038bab4a22bde1` / `gap042-impl` remain in `git worktree list --porcelain` or `git branch -a`.

This section is local git housekeeping only; it produced no repository changes and is not part of the PR diff.

---

## 1. Authoritative deployment-surface inventory (corrected — fixed categories, consistent counts)

To stop switching between inconsistent path-counts, every deployment-related repository artifact is classified into exactly one of four fixed categories:

- **A) Executable GitHub deployment workflows** — a workflow whose job(s) could, if secrets were populated, reach a real host or registry.
- **B) Placeholder/no-op workflow jobs** — a workflow job that runs unconditionally but performs no real deployment action regardless of secrets.
- **C) Underlying deployment scripts/topologies invoked by a Category-A workflow** — scripts/compose files that a Category-A workflow's SSH/registry steps actually call.
- **D) Infrastructure definitions not independently invoked by any workflow** — exist in the repo, describe a topology, but no workflow (Category A or B) ever executes them.

### Category A — executable GitHub deployment workflows (3 total)

| Workflow | Trigger | Jobs with real (non-placeholder) deploy logic |
|---|---|---|
| `.github/workflows/production.yml` | `push` to `main`, tags `v*` | `deploy` (1 job) |
| `.github/workflows/deploy.yml` (`Deploy Z.E.N.A to Production`) | `workflow_dispatch` only | `deploy` (1 job) |
| `.github/workflows/automated-deployment.yml` (`Automated Deployment`) | `release: published`, `workflow_dispatch` | `deploy-staging`, `deploy-production`, `rollback`, `blue-green-deployment`, `canary-deployment` (5 jobs) |

### Category B — placeholder/no-op workflow jobs (1 total)

| Workflow | Job | Evidence |
|---|---|---|
| `.github/workflows/ci-cd.yml` | `deploy` | Verified exact content: the only step after checkout is `run: | echo "Deploying to production..."` — a comment-only no-op. Runs unconditionally on every push to `main` and always reports success. |

### Category C — underlying scripts/topologies invoked by a Category-A workflow

| Invoked by | Script/topology | Role |
|---|---|---|
| `production.yml`'s `deploy` job | inline shell (no separate script file) | `git pull` + host-side Composer/npm build + `artisan migrate`/cache commands + `systemctl` calls, run directly in the SSH session. |
| `deploy.yml`'s `deploy` job | `./deploy.sh production` (executed **on the host**, at `/var/www/zenamanage`, post-`git pull` — i.e. whatever copy of `deploy.sh` exists in that checkout, not necessarily identical to `scripts/deploy.sh` in this repo) | Delegates the entire deploy body to a host-side script this repo cannot fully audit at rest (it audits the repo's own `deploy.sh`, but the SSH step runs the *host's* checked-out copy, which is the same content only if that host is in fact running this exact `main`). |
| `automated-deployment.yml`'s `deploy-staging`/`deploy-production`/`rollback`/`blue-green-deployment`/`canary-deployment` jobs | `docker-compose.prod.yml` (via `docker-compose -f docker-compose.prod.yml ...`) + `./docker-manage.sh backup` (production job only, pre-deploy) | Docker Compose pull/up, `artisan migrate --force` + cache rebuilds run **inside the `app` container** via `docker-compose exec`, plus a pre-deploy backup invocation and (on failure) a rollback job. |

### Category D — infrastructure definitions not independently invoked by any workflow

| Artifact | Note |
|---|---|
| `docker-compose.prod.yml` used *standalone* (i.e. `docker compose -f docker-compose.prod.yml up` run directly, outside any workflow) | The file itself **is** invoked by `automated-deployment.yml` (Category C above) — it is not orphaned infrastructure. What is Category D is the assumption that someone might bring the stack up by hand outside CI; no workflow does this, and no runbook documents doing it manually either. |
| `deploy.sh` (repo root) | Referenced only conceptually by `deploy.yml`'s host-side invocation (Category C caveat above); no workflow directly executes the repo's own copy of this file. |
| `scripts/deploy.sh` | Not referenced by any workflow at all. |
| `scripts/backup-{database,files,system}.sh` | Not referenced by any workflow. Separate from `docker-manage.sh backup`, which *is* wired into `automated-deployment.yml` (see §9). |

**Corrected surface count:** **3 executable workflows (A)**, **1 placeholder job (B)**, **3 underlying script/topology invocations (C)**, plus a handful of genuinely-orphaned scripts (D). This document uses these counts consistently from here on; any earlier draft's "3 paths" / "4 paths" language is superseded by this table.

### Per-workflow secret-name table (corrected — do not generalize "the 4 production secrets" across workflows)

| Workflow / job | Exact secrets read | Health check after deploy? | Target path | Invocation mechanism |
|---|---|---|---|---|
| `production.yml` → `deploy` | `PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL` | Yes — `curl -f $PRODUCTION_URL/api/health` | `/var/www/zena` | Inline shell via `appleboy/ssh-action@v1.2.4` |
| `deploy.yml` → `deploy` | `PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY` **only** — no `PRODUCTION_URL`, **no post-deploy health check at all** | **No** | `/var/www/zenamanage` | `./deploy.sh production` via `appleboy/ssh-action@v1.2.4` |
| `automated-deployment.yml` → `deploy-staging` | `STAGING_HOST`, `STAGING_USERNAME`, `STAGING_SSH_KEY` | Yes — `curl -f https://staging.zenamanage.com/health` + 2 more smoke curls | `/opt/zenamanage` | `docker-compose.prod.yml` pull/up + `exec` via `appleboy/ssh-action@v1.2.4` |
| `automated-deployment.yml` → `deploy-production` | `PRODUCTION_HOST`, **`PRODUCTION_USERNAME`** (not `PRODUCTION_USER` — see below), `PRODUCTION_SSH_KEY` | Yes — health + 2 smoke curls + 2 performance curls against `https://dashboard.zenamanage.com`, `https://api.zenamanage.com`, `https://ws.zenamanage.com` | `/opt/zenamanage` | `./docker-manage.sh backup` (pre-deploy) then `docker-compose.prod.yml` pull/up + `exec` |
| `automated-deployment.yml` → `rollback` | `PRODUCTION_HOST`, `PRODUCTION_USERNAME`, `PRODUCTION_SSH_KEY` | Yes — 1 health curl after rollback | `/opt/zenamanage` | `git reset --hard HEAD~1` + `docker-compose up -d` (see §10 for safety analysis) |
| `automated-deployment.yml` → `blue-green-deployment` | `PRODUCTION_HOST`, `PRODUCTION_USERNAME`, `PRODUCTION_SSH_KEY` | Yes — health checks against `green.*`/`green-api.*`/`green-ws.*` and final `dashboard.zenamanage.com` | `/opt/zenamanage` (Compose project `zenamanage-green`/`zenamanage-blue`) | Compose `-p zenamanage-green`/`-p zenamanage-blue` project isolation |
| `automated-deployment.yml` → `canary-deployment` | `PRODUCTION_HOST`, `PRODUCTION_USERNAME`, `PRODUCTION_SSH_KEY` | Yes — health/perf curls against `canary.zenamanage.com` at each traffic-percentage step | `/opt/zenamanage` (Compose project `zenamanage-canary`) | Staged 10%→50%→100% traffic promotion (see §9 — the traffic-shifting steps themselves are `echo`-only placeholders, not real load-balancer calls) |

**Real naming inconsistency (not normalized, called out as-is per Owner instruction):** `production.yml` and `deploy.yml` read `PRODUCTION_USER`; every job in `automated-deployment.yml` reads `PRODUCTION_USERNAME`. These are two different GitHub Secret names. If only one of them is ever actually populated, the other workflow's secret-gate will report it missing and skip — this is a genuine repo defect, not a documentation typo, and Gate 2 must decide which name (or both) to standardize on.

### Contradictions explicitly surfaced

1. **Target path:** `/var/www/zena` (`production.yml`) vs `/var/www/zenamanage` (`deploy.yml`) vs `/opt/zenamanage` (`automated-deployment.yml`) — three different filesystem locations for "the" production checkout.
2. **Secret naming:** `PRODUCTION_USER` vs `PRODUCTION_USERNAME` (see table above).
3. **Health-check contract:** present and URL-based in `production.yml`; **entirely absent** in `deploy.yml`; present and multi-endpoint (health + smoke + performance, across `dashboard`/`api`/`ws` subdomains) in `automated-deployment.yml`.
4. **Deployment model:** bare-metal `git pull` + host-installed toolchain (`production.yml`, `deploy.yml`) vs CI-built Docker image + GHCR + Docker Compose on the host (`automated-deployment.yml`) — two structurally incompatible deployment philosophies coexist with no recorded decision about which is authoritative.
5. **Domain names:** `automated-deployment.yml` references concrete production domains (`dashboard.zenamanage.com`, `api.zenamanage.com`, `ws.zenamanage.com`, `green.zenamanage.com`, `canary.zenamanage.com`) that do not appear anywhere else in the repo (not in `.env.example`, not in `production.yml`). Whether these domains are real, aspirational, or leftover placeholder text from when this workflow was authored is **unproven** — this audit did not attempt any external DNS/HTTP lookup (out of scope, and would itself be an external network action against a possibly-real domain).

---

## 2. Actions run-history review (near-exhaustive, corrected — replaces the prior "never deployed" absolute claim)

This round performed a **systematic review of all available GitHub Actions run history** for all three Category-A workflows, using `gh run list --workflow=<file> --limit 500` (returns the complete history the API will provide) followed by `gh api repos/.../actions/runs/<id>/jobs` for **every single run**, checking whether any step outside a fixed "trivial" set (job setup, checkout, the secret-gate step itself) ever reported a conclusion other than `skipped`/`null`.

| Workflow | Total runs reviewed | Earliest run in history | Real (non-skipped) deploy-action step executions found |
|---|---|---|---|
| `production.yml` | 269 (75 `success` + 194 `failure`, all reviewed) | 2025-09-16 | **0** |
| `deploy.yml` | 249 (all reviewed) | 2025-09-16 | **0** |
| `automated-deployment.yml` | 151 (all reviewed, across all 5 jobs) | 2025-12-16 | **0** |

Notable details from this review:
- For `production.yml`, the single longest-duration run (3326s, `conclusion: failure`) was inspected individually and found to be a **cancelled** run (`security-scan: cancelled`, `test: skipped`, `deploy: skipped`) — not a real deploy attempt that ran long.
- For `production.yml`, 19 of the 75 `success` runs show the `deploy` job present with **zero recorded steps at all** — consistent with the job's `if: github.ref == 'refs/heads/main'` condition evaluating false (non-`main` context) rather than any step actually running and being skipped.
- For `automated-deployment.yml`, the `deploy-production` job shows conclusion `success` 123 times and `failure` 20 times; the 20 `failure` instances were sampled and found to have **zero recorded steps** — consistent with a job-level gate failure (e.g. GitHub Environment protection or job dispatch failure) rather than any real deployment step executing and failing. The pre-deploy `./docker-manage.sh backup` step specifically was checked and found `skipped` in every run where the job had any steps at all.

**Caveats on exhaustiveness:** this is the **complete run history the GitHub API returned** for each workflow file's current path, going back roughly one year (September/December 2025 through today) — longer than GitHub's 90-day default retention would suggest, indicating either this repository has extended/unlimited retention configured or these dates mark close to the workflows' actual creation. This audit cannot rule out (a) retention truncation of runs older than what the API returned, or (b) a workflow run bypassing the API's run-listing indexing entirely. Given that caveat, the corrected, defensible claim is:

> **No successful real production deployment was proven by the repository and Actions evidence reviewed.** Across the complete Actions run history available via the GitHub API for all three candidate production-deployment workflows (269 + 249 + 151 = 669 runs reviewed), zero runs show any real deployment action (SSH git-pull, Docker build/push, `docker-compose` up/exec, `artisan migrate`, backup invocation, health/smoke/performance check, rollback, blue-green switch, or canary promotion) executing with a non-skipped conclusion. This is the strongest evidence available short of an explicit host-side audit, but it is evidence-bounded, not a claim of metaphysical certainty about all of history.

This replaces the Round-1 document's unqualified "production has never deployed" language everywhere it appeared.

---

## 3. False-green / ambiguous signals (first-class finding — unchanged substance, cross-references corrected)

1. `production.yml`, `deploy.yml`, and every job in `automated-deployment.yml` report top-level `success`/expected conclusions while their actual deploy logic never executes (secret-gated skip) — confirmed exhaustively per §2, not just spot-checked. Anyone glancing at the Actions tab or a badge sees green (or, for `automated-deployment.yml`'s historical push-triggered runs, sometimes a job-level `failure` with zero real steps run — which is arguably even more misleading, since "failure" implies an attempt was made).
2. `.github/workflows/ci-cd.yml`'s `deploy` job (Category B) is a hardcoded no-op that always "succeeds."
3. The **health check `production.yml` itself would run** (`curl -f $PRODUCTION_URL/api/health`) targets `routes/api.php`'s `/health` handler, which is a **hardcoded** JSON literal: `'database' => 'connected'`, `'services' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok']` — string literals, not the result of any actual database/cache/queue probe (verified by reading the closure body directly). Note this specific false-green risk applies to `production.yml`'s health check; `automated-deployment.yml`'s health checks curl a **different** path (bare `/health`, not `/api/health`) against domains not otherwise evidenced in this repo (see §1 contradiction 5), so this audit cannot confirm what handler (if any) those `/health` paths would actually hit — that depends on the never-established real host/domain configuration, not on code in this repo.
4. `routes/health.php` defines a second, near-duplicate set of `/health*` and `/api/health*` routes bound to yet another controller (`App\Http\Controllers\HealthController`) — but this file is **never `require`d or grouped** anywhere in `RouteServiceProvider`/`bootstrap/app.php` (verified: only `routes/api.php`, `routes/web.php`, and conditionally `routes/api-simple.php`/`routes/debug_api.php` are registered). `routes/health.php` is dead code that could mislead a future reader into believing a different health contract is live.

---

## 4. Runtime topology (from code/config, not assumption — unchanged from Round 1)

- **PHP:** `^8.2` (`composer.json`); production Docker image uses `php:8.2-fpm-alpine` (`Dockerfile.prod`).
- **Web/process model:** `Dockerfile.prod` builds a `php-fpm-alpine` image (implies nginx or another front server is a separate container — `docker-compose.prod.yml` has a distinct `nginx` service). The bare-metal SSH path (`production.yml`, `deploy.yml`) assumes nginx + PHP-FPM already installed and configured on the host (`sudo systemctl reload nginx`) — nothing in the repo provisions this from scratch.
- **Composer:** `composer:2.6` build stage in `Dockerfile.prod`.
- **Node/frontend build:** `node:18-alpine` build stage in `Dockerfile.prod`; `production.yml`'s bare-metal path runs `npm ci && npm run build` directly **on the target host**, meaning the host itself needs Node 18-equivalent installed — no version pin enforced on that path (contrast with the Docker path, which pins via the build stage).
- **Database:** MySQL (`DB_CONNECTION=mysql` in `.env.example`; `docker/mysql/{master,slave,my}.cnf` present, implying a primary/replica topology is at least modeled for the Docker path — no evidence this replica topology is used or required for a *first* controlled deployment).
- **Cache/session/queue defaults:** `.env.example` ships `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, but `QUEUE_CONNECTION=sync` — i.e., out of the box, queued jobs run **synchronously in the request thread**, not in the background. Real async queue processing requires explicitly setting `QUEUE_CONNECTION` to `redis`/`database` *and* running a worker process. `production.yml`'s deploy script issues `php artisan queue:restart`, which only signals *existing* workers to gracefully restart — it does not start one. No systemd unit, supervisor config, or provisioning step for a host-side queue worker exists on the bare-metal path (supervisor config `docker/supervisor/supervisord.conf` exists but belongs to the *Docker* topology, not the SSH one).
- **Scheduler:** `routes/console.php` defines no scheduled tasks (only the stock `inspire` command); `bootstrap/app.php` has no `->withSchedule(...)` registration. There is currently nothing for a cron-triggered `php artisan schedule:run` to execute — not a blocker today, but worth noting before anyone assumes cron is wired for a reason.
- **Websocket/realtime:** `production.yml` runs `sudo systemctl restart websocket`, assuming a pre-existing systemd unit named `websocket` on the host. No such unit file, or any provisioning script that would create one, exists anywhere in the repo. `Dockerfile.websocket` exists (a separate container image) but again belongs to the Docker topology, not the SSH one — the SSH path's `systemctl restart websocket` command has no evidenced host-side counterpart this repo ever creates.
- **Storage/uploads:** standard Laravel `storage/` + `php artisan storage:link` model implied by `config/filesystems.php`-style usage; no explicit `storage:link` step appears in any deploy workflow — first-deploy image uploads could 404 until someone runs it manually.
- **Mail:** `MAIL_MAILER=smtp` default.
- **External APIs:** `ANTHROPIC_API_KEY` present in `.env.example` (AI features), `PUSHER_*` vars present (broadcast), zena-boq-core integration referenced elsewhere in the codebase per prior GAP work (not re-audited here — out of scope).
- **Background workers:** see queue note above; no proven host-side worker process for the bare-metal SSH topology (the Docker topology's `docker-compose.prod.yml` does define a `queue` service — see §9).

---

## 5. Production environment contract (names only, no values — corrected staging/production secret note)

Representative categories found in `.env.example` (60 unique top-level keys) and referenced `config/*.php` files:

- **Required to boot:** `APP_KEY`, `APP_ENV`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- **Core workflow:** `CACHE_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `BROADCAST_DRIVER`, `MAIL_MAILER` + SMTP host/port/username/password/encryption.
- **Optional integration:** `ANTHROPIC_API_KEY`, `PUSHER_APP_ID`/`PUSHER_APP_KEY`/`PUSHER_APP_SECRET`/`PUSHER_HOST`/`PUSHER_PORT`/`PUSHER_SCHEME`/`PUSHER_APP_CLUSTER` and matching `VITE_PUSHER_*` build-time equivalents.
- **Test-only:** anything consumed only under `APP_ENV=testing` / `.env.testing` (CI creates this from `.env.example` in `production.yml`'s own `test` job — not relevant to real production).
- **Staging-only:** `STAGING_HOST`, `STAGING_USERNAME`, `STAGING_SSH_KEY` (GitHub Secrets, referenced by `automated-deployment.yml`'s `deploy-staging` job only).
- **Production-only (secret, GitHub Actions) — see §1's per-workflow table for the exact per-workflow set, which is NOT uniform:** `PRODUCTION_HOST` (all three workflows), `PRODUCTION_USER` (`production.yml`, `deploy.yml` only), `PRODUCTION_USERNAME` (`automated-deployment.yml` only — a different secret name, not a synonym confirmed to hold the same value), `PRODUCTION_SSH_KEY` (all three), `PRODUCTION_URL` (`production.yml` only).
- **Secret (never print values, names only):** `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `PUSHER_APP_SECRET`, `ANTHROPIC_API_KEY`, `PRODUCTION_SSH_KEY`, and any SMTP password.

`APP_DEBUG=true` and `APP_ENV=local` are the **defaults shipped in `.env.example`** — a production `.env` must explicitly override both; the repo does not enforce or verify this anywhere in the deploy path (no assertion step checking `APP_DEBUG=false` before/after deploy).

---

## 6. Database / migration readiness (corrected migration-isolation claim)

- 209 migration files in `database/migrations/`. This audit did not exhaustively re-classify all 209 for destructiveness (out of scope for Gate 1 time budget); this is flagged as a **Gate-2 input**, not resolved here.
- `database/migrations_backup/` contains three `disabled_*` ULID-conversion migrations (`tenants`, `users`, `projects`) — explicitly disabled, evidence that at least one prior irreversible-migration attempt was deliberately shelved. Their presence signals the team has already encountered at least one destructive-migration hazard on core tables; Gate 2 should decide whether these remain permanently disabled or need a real decision.
- `database/seeders/` includes `DemoUsersSeeder`, `MockDataSeeder`, `DatabaseSeeder` — these must **not** run against a real production database on first deploy; nothing in any deploy workflow's `migrate --force` step touches seeders (none call `db:seed`), which is correct, but there is no explicit repo-level guard preventing someone from later running `php artisan migrate:fresh --seed` against production by mistake.
- **First-deploy assumption:** not established by the repo — whether the very first controlled deployment targets an empty database or one with pre-existing data is an external/operational decision, not something the codebase asserts. Flagged as an Owner input.
- **Migration locking/concurrency — corrected:** re-checked directly. `composer.json` pins `laravel/framework: ^12.0`, which supports Laravel's `migrate --isolated` flag (available since Laravel 8), but **no workflow or script in this repo ever passes `--isolated`** (`git grep` across `.github/workflows/*.yml`, `deploy.sh`, `scripts/deploy.sh`, `docker-manage.sh` confirms every `migrate --force` call is bare). No workflow declares a `concurrency:` group that would prevent two deploy runs from overlapping. No `flock` or equivalent host-side locking mechanism is referenced anywhere in the deploy scripts. **Corrected finding: no explicit cross-process migration/deployment isolation was proven.** The prior claim that "`migrate --force` provides Laravel's standard single-process migration locking" conflated the existence of Laravel's internal migrations-table bookkeeping with a proven concurrency-safety guarantee across overlapping deploy invocations — that guarantee is not established here.

---

## 7. Deployment atomicity / rollback (corrected — split per workflow, no global claim)

### `production.yml` (bare-metal, no separate rollback job) — the Round-1 finding stands for THIS workflow only

The model (`cd /var/www/zena && git pull && composer install && npm ci && npm run build && migrate --force && ...cache && queue:restart && reload nginx`) is a classic **in-place, non-atomic** deploy with **no defined rollback and no maintenance-mode window**:
- No `set -e` in the inline script; a failing `composer install`/`npm run build` does not necessarily abort subsequent steps, and even where it does, the working directory is left in a **half-updated state** (code moved forward via `git pull`, dependencies/assets did not) while requests continue being served from that inconsistent tree — `artisan down`/`up` is never called.
- `migrate --force` failing mid-batch leaves the schema partially migrated while the code has already fully moved forward.
- No release-directory/symlink model — exactly one working copy, updated in place. Rollback would require manually running `git checkout <previous-sha>` plus the entire build/migrate sequence; nothing scripts this, and no authority/trigger is documented.

### `deploy.yml` — same absence of rollback, additionally lacks even a health check

Delegates entirely to a host-side `./deploy.sh production` invocation this repo cannot fully audit at rest (see §1 Category C). No rollback job, no maintenance-mode step, and — corrected per §1 — **no post-deploy health check of any kind**, so a failed deploy via this path would not even be automatically detected by the workflow itself.

### `automated-deployment.yml` — rollback code exists; read in full; classified below

This workflow has a dedicated `rollback` job (`if: failure() && ...`) whose script is:
```
cd /opt/zenamanage
git reset --hard HEAD~1
docker-compose -f docker-compose.prod.yml up -d
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
```
**Classification: rollback code exists (proven present in the repo), but its safety is unproven and, on the specific evidence read, likely unsafe by default:**
- It is **code-only rollback** — `git reset --hard HEAD~1` reverts the application source tree exactly one commit, then restarts containers and rebuilds config cache. **It never runs any migration-down step, and never restores a database backup.** If the deploy that triggered the rollback included a forward schema migration, this rollback leaves the **schema at the new (migrated-forward) state while the application code reverts to the old (pre-migration) state** — a classic rolled-back-code-against-forward-schema mismatch that can crash or silently corrupt data depending on the migration's nature. **This is flagged explicitly as unsafe-by-default for any deploy that included a schema migration**, not assumed safe merely because rollback code exists.
- It assumes exactly one commit of drift (`HEAD~1`); a deploy that pulled multiple commits (routine for this repo, given typical multi-commit PR merges) would not be fully reverted by a single `HEAD~1` reset.
- **Proven/unproven:** the rollback job's steps show `skipped` in every run reviewed in §2 (the job's trigger condition, `failure()`, was never satisfied by a real deploy failure in the reviewed history) — this rollback path has **never actually executed**, proven or otherwise.
- The `blue-green-deployment` job's traffic-switch step (`Switch traffic to green environment`) is itself an `echo`-only placeholder comment (`"Switching traffic to green environment..."` — no real nginx/DNS/load-balancer call), so even if blue-green were exercised, the actual traffic cutover is not implemented, only described.

**Corrected conclusion:** rollback is not a single global "does not exist" fact. `production.yml` and `deploy.yml` genuinely have none. `automated-deployment.yml` has rollback *code*, but that code is unsafe-by-default against forward schema migrations and has never actually run in the reviewed history.

---

## 8. Host/server prerequisites (unchanged from Round 1)

Explicitly required by `production.yml`'s script content: nginx installed and running (reloadable via passwordless-or-configured `sudo systemctl reload nginx`), a `websocket` systemd service already present (unproven — see §4), PHP 8.2 + Composer + Node 18-class toolchain installed directly on the host, an SSH user with a home/checkout at `/var/www/zena` and sudo rights scoped at least to `systemctl reload nginx` / `systemctl restart websocket`. **OS, CPU/RAM/disk sizing, filesystem ownership scheme, and backup storage location are UNKNOWN — nothing in this repository establishes a target host or provider.** No host/provider name appears anywhere in workflows, docs, or config; this must not be invented and is recorded as a required external Owner input. (`deploy.yml` and `automated-deployment.yml` imply a *different* host/path — see §1 — so this section's specifics are `production.yml`-specific; the other two workflows' host prerequisites are separately unestablished.)

---

## 9. Domain / HTTPS / reverse proxy (corrected — automated-deployment.yml domain references noted)

- `APP_URL=http://localhost` is the only value in `.env.example` (placeholder, non-HTTPS).
- `PRODUCTION_URL` (used only by `production.yml`) is a GitHub Secret referenced only for the post-deploy curl check — its actual domain value is entirely external to the repo.
- `automated-deployment.yml`, by contrast, hardcodes concrete domain names directly in the workflow YAML (`dashboard.zenamanage.com`, `api.zenamanage.com`, `ws.zenamanage.com`, `staging.zenamanage.com`, `green.zenamanage.com`, `canary.zenamanage.com`) rather than reading them from a secret/variable. Whether these domains are live, reserved, or stale placeholder text from when the workflow was written is **unproven** by this audit (no external DNS/HTTP lookups were performed — out of scope and would be an external network action against a possibly-real third-party domain).
- `app/Http/Middleware/TrustProxies.php` and `TrustHosts.php` exist (standard Laravel scaffolding) but this audit did not find them configured with a specific known-reverse-proxy CIDR or host allowlist beyond framework defaults — real values would need to be supplied per-environment.
- `docker/nginx/nginx.prod.conf` exists as a template vhost for the Docker topology; the bare-metal SSH topology has **no equivalent nginx vhost committed to the repo** — whatever vhost, TLS certificate, and HTTPS-redirect exists on that hypothetical host is entirely undocumented here.
- **Domain name ownership, DNS records, and TLS certificate provisioning (e.g., Let's Encrypt/ACME) are UNKNOWN / not established as currently operable by the repo**, regardless of the literal domain strings hardcoded in `automated-deployment.yml`. These are genuine external Owner inputs, not something a design phase can infer or confirm from repo content alone.

---

## 10. Backup / recovery (corrected — automated-deployment.yml's backup hook exists; precise epistemics per claim)

Round 1 incorrectly stated "no workflow runs a backup." Corrected: `automated-deployment.yml`'s `deploy-production` job includes a dedicated step, `Create backup before deployment`, which SSHes to the host and runs `cd /opt/zenamanage && ./docker-manage.sh backup`. `docker-manage.sh`'s `backup` subcommand dispatches to a `create_backup()` function, read in full:

```
create_backup() {
    local backup_dir="backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$backup_dir"
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" --all-databases > "$backup_dir/database.sql"
    tar -czf "$backup_dir/storage.tar.gz" storage/
    tar -czf "$backup_dir/public.tar.gz" public/
    cp production.env "$backup_dir/"
    cp "$DOCKER_COMPOSE_FILE" "$backup_dir/"
}
```

`docker-manage.sh` also implements a `restore` subcommand (`restore_backup()`), which stops services, restores `database.sql` via `mysql` client, extracts the `storage.tar.gz`/`public.tar.gz` tarballs, and restarts services, gated behind an interactive `y/N` confirmation prompt.

Precise epistemics, each claim stated separately as instructed:

(a) **Backup hook/code exists — yes.** Both the workflow step and the full `create_backup()` implementation are present and read in full above.
(b) **Has it ever actually executed in a real run — no evidence of execution.** Per §2's exhaustive run-history review, the `Create backup before deployment` step shows `skipped` in every `automated-deployment.yml` run reviewed that had any recorded steps at all (gated behind the same missing-secrets check as the rest of the job).
(c) **Are resulting backup artifacts proven valid — unproven** (no execution evidence exists to validate against; see (b)).
(d) **Is retention proven — unproven.** `create_backup()` writes to a timestamped `backups/<timestamp>/` directory with no pruning/rotation logic anywhere in the script; nothing in the repo defines a retention policy.
(e) **Is restore implemented — yes**, `docker-manage.sh restore <backup_dir>` is a real, fully-coded subcommand (see above), not a stub.
(f) **Has an actual restore drill ever succeeded — almost certainly unproven; stated plainly.** No workflow, test, or documentation in this repo demonstrates `docker-manage.sh restore` has ever been invoked, let alone verified to produce a working restored system. **Backup code existing, and even restore code existing, is not proof that recoverability has ever been demonstrated end-to-end** — this remains a first-class blocker.

`scripts/backup-{database,files,system}.sh` remain a separate, genuinely unreferenced set of scripts (Category D, §1) — distinct from `docker-manage.sh`'s backup/restore pair, and still not invoked by any workflow.

---

## 11. First controlled deployment acceptance path (design sketch only — not implemented; unchanged from Round 1)

Beyond `/api/health`, a real first-deploy smoke sequence should verify, at minimum:
1. Login/auth succeeds for a real (non-demo) operator account.
2. Tenant isolation holds (a user in tenant A cannot see tenant B's data) — GAP-042's RBAC production-fidelity work is directly relevant prior art here.
3. RBAC — an operator with a specific role can perform exactly their permitted actions and no others.
4. A DB write persists across a request cycle (create → reload → still present).
5. A queued job actually executes in the background (not just accepted) — directly informed by the `QUEUE_CONNECTION=sync` default finding in §4; this check would immediately reveal if async queueing was never actually turned on.
6. A file upload round-trips through storage.
7. A small set of critical pages/APIs return 200 under authentication.

As a **future operational validation path** (explicitly not a spec to implement under GAP-049): the principal business flow Lead → Opportunity → classify/confirm Service Line → advance stage → Quote → sent/accepted → Contract → WON/Project touches CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line code directly. Per this task's explicit boundary, if implementing or specifying that acceptance check requires touching that product code, it must go through this repo's Design Dependency Preflight under its own Work ID — it is named here only as a description of what "real user success" should eventually mean, not a directive to build it now.

---

## 12. Security review (corrected secret-name references)

- **SSH deploy user/scope:** the deploy identity is an unknown secret value under one of two different secret names depending on workflow (`PRODUCTION_USER` for `production.yml`/`deploy.yml`, `PRODUCTION_USERNAME` for `automated-deployment.yml` — see §1); `production.yml`'s script runs `sudo systemctl reload nginx` and `sudo systemctl restart websocket` — this **requires passwordless (or Action-runnable) sudo for at least those two commands**, meaning a compromised `PRODUCTION_SSH_KEY` (a GitHub Secret, exposed to whatever has repo/workflow write access) grants an attacker the ability to run arbitrary code as that SSH user plus a defined sudo surface on the production host. Whether that sudo scope is tightly restricted or broader is **unknown — not established in this repo** and is a Critical item to confirm/harden before first real deploy. `automated-deployment.yml`'s jobs run Docker Compose commands rather than `sudo systemctl` calls, but a compromised key there would still grant control of whatever containers/volumes exist under `/opt/zenamanage`, including direct `mysqldump`/`mysql` access via `docker-manage.sh`.
- **Host-key verification:** `appleboy/ssh-action@v1.2.4` is used without any visible `fingerprint`/known-hosts pinning in any of the three workflows' YAML — whether it's pinned by another mechanism is not evidenced here (worth confirming against the action's actual default behavior at review time, not assumed either way by this audit).
- **`git pull` authentication on the server:** `production.yml` and `deploy.yml` run `git pull origin main` on the host with no explicit auth step in the workflow — implying the host's own git remote credentials (however configured) are used, entirely outside GitHub Actions' control and unaudited here. `automated-deployment.yml` avoids this by pulling a pre-built image from GHCR instead.
- **Debug mode / `APP_ENV`:** `.env.example` defaults to `APP_DEBUG=true`/`APP_ENV=local`; nothing in any deploy path asserts these are overridden for real production — a real risk if a hand-provisioned host `.env` was ever copied from `.env.example` without editing.
- **Secret leakage through logs:** GitHub Actions natively masks registered secrets in log output; no additional custom masking was found necessary or present.
- **DB/Redis public exposure, firewall rules:** UNKNOWN — host-level, not established by this repo.
- **Branch protection / GitHub Environment approval:** all three Category-A workflows' production-facing jobs use `environment: production` (and `automated-deployment.yml`'s staging job uses `environment: staging`), meaning GitHub's Environment protection rules (if configured on the GitHub repo settings, not visible from this local checkout) could gate the job with required reviewers — this audit could not confirm whether such protection rules are actually turned on (that setting lives in repo configuration, not in tracked files). The 20 unexplained job-level `failure` conclusions with zero recorded steps noted in §2 for `automated-deployment.yml`'s `deploy-production` job are circumstantially consistent with an Environment protection block, but this audit did not confirm that mechanism specifically.
- **Flag:** a compromised GitHub Actions run that reaches any of these deploy jobs on `main` would — **once the respective secrets are populated** — have SSH access plus whatever surface (sudo commands or Docker/DB access) that specific workflow's script grants on the production host. Populating any of these secrets is a security-significant decision, not just an operational one, and should coincide with GitHub Environment required-reviewer protection.

---

## 13. Recommended target architecture — Gate-2 design candidate only, NOT Owner-approved (corrected framing)

**This section states a current team recommendation for Gate 2 to evaluate. It does not pre-approve an architecture, and Gate 1 approval of the problem statement does not carry any approval of this recommendation.** Gate 2 must independently compare the corrected deployment surfaces described in §1 — including `automated-deployment.yml`'s materially more complete Docker/GHCR/backup/rollback/blue-green/canary path, now accurately described above, not the under-described version from Round 1 — before locking any target architecture.

Three real, differently-mature options exist in the repo today; none has been proven executed against a real host (§2):

1. **Direct single-VPS, release-based SSH deploy** (evolve `production.yml`): lowest new infrastructure to introduce, keeps the existing secret model, but requires fixing the atomicity/rollback gaps in §7 (adopt a releases/`current`-symlink pattern, add `artisan down`/`up` maintenance windows, add `set -e`, add an actual rollback definition that accounts for forward migrations). Lowest cost, moderate hardening effort.
2. **Docker Compose on one VPS**, via `docker-compose.prod.yml` as already invoked (not merely defined) by `automated-deployment.yml`: this is materially more built-out than Round 1 credited — it already has a working backup hook (§10), a rollback job (§7, though unsafe-by-default against forward migrations), blue-green and canary job skeletons (though their traffic-cutover steps are unimplemented placeholders), and multi-endpoint health/smoke/performance checks. Better isolation and reproducible CI-built images. Higher effort to finish hardening (the rollback safety gap and placeholder traffic-cutover steps are real work), but starts from a stronger base than Round 1's audit credited.
3. **`deploy.yml`'s manual-dispatch SSH path**: the least developed of the three — no health check at all, a different target path, delegates its entire body to an unaudited host-side script. Weakest current candidate.

**Team recommendation (Gate-2 design candidate — NOT Owner-approved architecture):** given the corrected evidence that `automated-deployment.yml` is substantially more complete than Round 1 credited, Gate 2 should seriously weigh Option 2 (harden the existing Docker/GHCR path) alongside Option 1, rather than defaulting to Option 1 as previously recommended. The deciding factors — team's Docker operational comfort, budget/host implications, and how much the rollback-safety and blue-green/canary placeholder gaps cost to close versus building Option 1's atomicity model from a thinner base — are Gate-2 analysis, not resolved here.

---

## 14. Concrete blockers (for first controlled deployment) — corrected wording

1. No target host/provider exists or is named anywhere (Critical — Owner input required).
2. No domain, DNS, or TLS provisioning proven operable anywhere, notwithstanding the literal domain strings hardcoded in `automated-deployment.yml` (Critical — Owner input required).
3. `production.yml` and `deploy.yml` are non-atomic with no rollback and no maintenance-mode window; `automated-deployment.yml`'s rollback exists but is unsafe-by-default against forward schema migrations and has never executed (Critical).
4. `production.yml`'s deploy health check is hardcoded/non-functional as a real signal; `deploy.yml` has no health check at all (High).
5. Backup/restore code exists (`docker-manage.sh`) but has never executed and no restore drill has ever been proven to succeed (High).
6. Three executable deployment workflows exist with contradictory target paths, secret names, and health-check contracts, and no authoritative decision about which is canonical (High).
7. Implicit dependency on a hand-provisioned host `.env`/`production.env` that nothing in any workflow creates, templates, or validates (High).
8. `websocket` systemd unit assumed by `production.yml` is never provisioned anywhere in-repo (Medium).
9. `QUEUE_CONNECTION=sync` default means background jobs silently run inline unless someone deliberately reconfigures + runs a worker — easy to miss on first deploy (Medium).
10. `PRODUCTION_USER` vs `PRODUCTION_USERNAME` secret-name inconsistency across workflows (Medium).
11. `automated-deployment.yml`'s blue-green traffic-cutover step is an unimplemented `echo` placeholder, not a real load-balancer/DNS action (Medium).
12. `routes/health.php` dead-code duplicate health routes could mislead future maintainers (Low).
13. 209 migrations not yet individually re-classified for destructiveness relevant to first deploy (Low/Medium — deferred to Gate 2 as a bounded task).

---

## 15. External Owner inputs genuinely required (not inferable from the repo — unchanged)

- Target host/provider (or explicit decision to provision one).
- Domain name and DNS control (including clarifying whether the `zenamanage.com` domains hardcoded in `automated-deployment.yml` are real/owned or stale placeholder text).
- Budget/cost tolerance influencing the architecture choice in §13.
- Who holds SSH/production credentials and who is authorized to trigger/approve a production deploy (GitHub Environment reviewers).
- Confirmation of whether a first deployment targets an empty database or one with existing data.

---

## 16. Risks (ranked — corrected)

- **Critical:** no host/domain/TLS proven operable; `production.yml`/`deploy.yml` have no rollback at all, and `automated-deployment.yml`'s rollback is unsafe-by-default against forward migrations; a compromised deploy pipeline currently has SSH + defined sudo/Docker/DB access on whatever host is eventually named.
- **High:** false-green health check on `production.yml` masks real dependency failures (and `deploy.yml` has no health check to even be false-green about); backup/restore code exists but is entirely unproven end-to-end; three executable deployment mechanisms with contradictory paths/secret-names/health-contracts; implicit unmanaged host `.env`.
- **Medium:** unprovisioned `websocket` systemd assumption; silent synchronous-queue default; secret-name inconsistency (`PRODUCTION_USER`/`PRODUCTION_USERNAME`); unimplemented blue-green traffic cutover; unclassified migration destructiveness.
- **Low:** dead-code duplicate health routes; unused scheduler infrastructure; duplicate `deploy.sh` naming; unconfirmed status of hardcoded `zenamanage.com` domains.

---

## 17. Explicit in-scope / out-of-scope boundary for GAP-049 (unchanged)

**In scope (this Work ID, across future gates):** deciding and hardening ONE deployment mechanism, its rollback/atomicity story, its real environment/secret contract, minimum health/observability, and a first-deployment acceptance/smoke sequence — for infrastructure/deploy/env/CI concerns only.

**Out of scope (this Work ID):** any CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product semantics or code changes (per explicit instruction — if the acceptance-path design ever requires touching that code, it must be escalated to a separate Work ID via the Design Dependency Preflight); GAP-042 RBAC scope (closed, released, not reopened); actual production secret configuration; actual infrastructure provisioning; an implementation plan (reserved for a post-Gate-2 phase); pre-selecting a target architecture (§13 is a Gate-2 design candidate, not a decision).
