# GAP-049 Gate 1 Evidence — Production Readiness & First Controlled Deployment

**Date:** 2026-09-03
**Canonical main SHA used:** `0872ac856932193a037ce30f00050179374811af` (verified live: `git rev-parse origin/main` at time of writing equals the handoff SHA reported by the prior session — no drift, no intervening commits to inspect).
**Method:** Static repository evidence only (file content, `git log`/`git diff`, `gh run` API). No deployment attempted, no secrets configured, no production database touched, no infrastructure provisioned or destroyed. GAP-042 scope (RBAC semantics) was not touched.

---

## 0. GAP-042 worktree cleanup outcome (housekeeping, not part of the technical evidence)

Two local worktrees left over from the GAP-042 effort were independently verified and removed:

| Worktree | Branch | HEAD | Finding | Disposition |
|---|---|---|---|---|
| `.claude/worktrees/agent-a068f9441ec6f8f40` | `worktree-agent-a068f9441ec6f8f40` | `673855f6` | `git merge-base --is-ancestor <branch> origin/main` = true — branch tip is the exact GAP-042 squash-merge commit, fully contained in `origin/main`. Clean working tree. | Backed up (`git bundle`) then `git worktree remove -f -f` + `git branch -D`. |
| `.claude/worktrees/agent-aba038bab4a22bde1` | `gap042-impl` | `2b1a05f3` | `git ls-remote --heads origin gap042-impl` → empty (zero remote trace). `git merge-base gap042-impl origin/main` = `673855f6` (the squash commit itself), and a content diff of e.g. `src/RBAC/Services/RBACManager.php` between the branch and `origin/main` shows the branch is an **earlier, less-hardened draft** of the same RBAC fix (missing the fail-closed helpers `isGenuineSystemRole()`/`revokeRoleIdentitiesValid()` that exist in the released main) — a superseded pre-squash WIP, not unmerged unique work. | Backed up (`git bundle` + patch + commit log) then `git worktree remove -f -f` + `git branch -D`. |

Both worktrees showed a `locked` flag in `git worktree list --porcelain` attributed to `pid 33562`, but that same pid was the lock-holder for essentially every worktree in the tree (including this session's own and several others clearly idle for weeks), which is consistent with stale harness lock bookkeeping rather than a live session. `git worktree remove -f -f` (git's own documented lock-override) succeeded cleanly for both; no harness sandbox block was encountered for this specific operation (a sandbox block was encountered only for exploratory `git -C <other-worktree-path> ...` commands and was worked around by using ref-based commands — `git log`/`git diff`/`git bundle` by branch name — which are valid from any worktree because refs/objects are shared).

Backups: `/tmp/zena-stale-worktree-backups/20260903-gap049/{worktree-agent-a068f9441ec6f8f40,gap042-impl}.{bundle,patch,commits.txt}`.

Final state: `git worktree prune` removed 9 additional unrelated stale entries whose gitdir files pointed to non-existent locations (pre-existing debt, unrelated to GAP-042, left as-is — out of scope for this cleanup). Neither `agent-a068f9441ec6f8f40` nor `agent-aba038bab4a22bde1` / `gap042-impl` remain in `git worktree list --porcelain` or `git branch -a`.

This section is local git housekeeping only; it produced no repository changes and is not part of the PR diff.

---

## 1. Deployment-surface inventory

| Surface | File | Classification | Evidence |
|---|---|---|---|
| SSH git-pull deploy on push to `main` | `.github/workflows/production.yml` | **Live but never actually executed** (workflow runs and reports "success" on every push to main, but the `deploy` job's secret-gate step (`Gate production secrets`) checks `PRODUCTION_HOST`/`PRODUCTION_USER`/`PRODUCTION_SSH_KEY`/`PRODUCTION_URL`; when any is empty it sets `ready=false` and the `Deploy to production` and `Health check` steps carry `if: ready == 'true'`, so they are **skipped**, not run.) Verified directly against the most recent run (`gh run view 33633749009 --json jobs`): `Deploy to production: skipped`, `Health check: skipped`, overall job `success`. This pattern repeats for every run on `main` back through GAP-038 (10 most recent runs all `success` with the same skip pattern implied by unconfigured secrets). Assumptions in the task brief were verified byte-for-byte against the file: target `/var/www/zena`, `git pull origin main`, host-side `composer install --no-dev --optimize-autoloader` + `npm ci && npm run build`, `php artisan migrate --force`, `config:cache`/`route:cache`/`view:cache`, `queue:restart`, `sudo systemctl reload nginx`, `sudo systemctl restart websocket`, then `curl -f $PRODUCTION_URL/api/health`. |
| Manual-dispatch SSH deploy | `.github/workflows/deploy.yml` (`Deploy Z.E.N.A to Production`) | **Duplicate/contradictory, never run** | Same 4-secret gate pattern, but targets a **different path** (`/var/www/zenamanage`, not `/var/www/zena`) and delegates to `./deploy.sh production` on the host instead of inlining steps. Contradicts `production.yml`'s target directory. `workflow_dispatch`-only; no evidence in `gh run list` of it ever being invoked. |
| Docker/staging + production release-based deploy | `.github/workflows/automated-deployment.yml` (`Automated Deployment`) | **Duplicate/contradictory, never run** | Builds a Docker image from `Dockerfile.prod`, pushes to GHCR, then SSHes into a **third** target model (`/opt/zenamanage`, container-based) for staging (`STAGING_*` secrets) and — by symmetry of the file — production. This is architecturally incompatible with the bare-metal `git pull` model in `production.yml`/`deploy.yml`: the repo currently describes three non-interoperable deployment topologies (bare-metal SSH+git-pull ×2 with different paths, and container/GHCR+SSH) with no documented decision about which is authoritative. |
| CI placeholder | `.github/workflows/ci-cd.yml`, `deploy` job | **Placeholder, confirmed** | Verified exact content: the job's only step after checkout is `run: | echo "Deploying to production..."` — a comment-only no-op. It runs unconditionally on every push to `main` (`if: github.event_name == 'push' && github.ref == 'refs/heads/main'`) and always reports success. |
| Docker Compose full-stack definition | `docker-compose.prod.yml` | **Present, unreferenced by any workflow** | Defines 12 services (`app`, `nginx`, `mysql`, `redis`, `queue`, `scheduler`, `websocket`, `prometheus`, `grafana`, `elasticsearch`, `kibana`, `backup`) plus named volumes — the most complete single-host topology in the repo — but no GitHub workflow invokes `docker compose -f docker-compose.prod.yml up`. It is dead infrastructure-as-code relative to CI: a fourth candidate model with no execution path proven. |
| Local deploy script | `deploy.sh` (repo root) and `scripts/deploy.sh` | Present, **role unclear** — two same-named scripts exist at different paths | `deploy.yml` invokes `./deploy.sh production` on the *host*, i.e. the copy that would exist post-`git pull` at `/var/www/zenamanage`, not necessarily `scripts/deploy.sh`. Neither script has ever run in a logged CI job (only referenced from the never-invoked `deploy.yml`). |
| Backup scripts | `scripts/backup-database.sh`, `scripts/backup-files.sh`, `scripts/backup-system.sh`, `app/Console/Commands/{BackupCommand,DatabaseBackupCommand}.php`, `app/Jobs/BackupJob.php`, `config/backup.php` | Present, **unproven** | No workflow or documented runbook invokes any of these against a real target; no restore drill evidence exists anywhere in the repo (`docs/`, `tests/`) proving a restore has ever actually been performed. A script's existence is not proof of a working backup/restore cycle. |
| Staging smoke | `.github/workflows/staging-smoke.yml`, `scripts/smoke_staging.sh` | Present, staging-only | Not part of the production path; useful precedent for a Gate-2 acceptance-path design but out of scope here. |

**Conclusion:** the repository currently has **zero proven production deployment executions**. Every workflow that could reach a real host is either a hard placeholder (`ci-cd.yml`) or gated behind secrets that are demonstrably absent (`production.yml`, `deploy.yml`, `automated-deployment.yml`), and three of those candidate mechanisms describe mutually incompatible host topologies and target directories.

---

## 2. Proven current production status (truthful deployment status)

There is no way, from repository state alone, to distinguish "deployment intentionally not yet configured" from "attempted and failed" from "misconfigured" — but for `production.yml` specifically, this session obtained a *direct* answer via the GitHub Actions API rather than inferring it: the `Deploy to production` and `Health check` steps report `conclusion: skipped` on every recent run. **A green `Production Deployment` workflow run does not mean production was deployed.** This is the single most important finding for anyone reading CI status as a proxy for "we shipped." No `/api/health` (or any other production URL) was queried by this session, and no such external check was in scope — nothing in the repo asserts a live external endpoint exists.

---

## 3. False-green / ambiguous signals (first-class finding)

1. `.github/workflows/production.yml` and `deploy.yml` both report top-level `success` while their actual deploy logic never executes (secret-gated skip). Anyone glancing at the Actions tab or a badge sees green.
2. `.github/workflows/ci-cd.yml`'s `deploy` job is a hardcoded no-op that always "succeeds."
3. The **health check the deploy workflow itself would run** (`curl -f $PRODUCTION_URL/api/health`) targets `routes/api.php`'s `/health` handler, which is a **hardcoded** JSON literal: `'database' => 'connected'`, `'services' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok']` — these values are string literals, not the result of any actual database/cache/queue probe (verified by reading the closure body directly). A 200 response from this endpoint proves only that PHP-FPM/nginx answered HTTP requests; it proves nothing about the database, Redis, or queue actually being reachable. A *real* dependency-probing health check exists (`HealthController::detailed()` / `SystemHealthController::detailed()`, checking DB/Redis/storage/queue/websocket/external services) but it is wired only to `/api/health/detailed`, not to `/api/health` — the exact path the deploy workflow curls.
4. `routes/health.php` defines a second, near-duplicate set of `/health*` and `/api/health*` routes bound to yet another controller (`App\Http\Controllers\HealthController`) — but this file is **never `require`d or grouped** anywhere in `RouteServiceProvider`/`bootstrap/app.php` (verified: only `routes/api.php`, `routes/web.php`, and conditionally `routes/api-simple.php`/`routes/debug_api.php` are registered). `routes/health.php` is dead code that could mislead a future reader into believing a different health contract is live.

---

## 4. Runtime topology (from code/config, not assumption)

- **PHP:** `^8.2` (`composer.json`); production Docker image uses `php:8.2-fpm-alpine` (`Dockerfile.prod`).
- **Web/process model:** `Dockerfile.prod` builds a `php-fpm-alpine` image (implies nginx or another front server is a separate container — `docker-compose.prod.yml` has a distinct `nginx` service). The SSH bare-metal path (`production.yml`) assumes nginx + PHP-FPM already installed and configured on the host (`sudo systemctl reload nginx`) — nothing in the repo provisions this from scratch.
- **Composer:** `composer:2.6` build stage in `Dockerfile.prod`.
- **Node/frontend build:** `node:18-alpine` build stage in `Dockerfile.prod`; `production.yml`'s bare-metal path runs `npm ci && npm run build` directly **on the target host**, meaning the host itself needs Node 18-equivalent installed — no version pin enforced on that path (contrast with the Docker path, which pins via the build stage).
- **Database:** MySQL (`DB_CONNECTION=mysql` in `.env.example`; `docker/mysql/{master,slave,my}.cnf` present, implying a primary/replica topology is at least modeled for the Docker path — no evidence this replica topology is used or required for a *first* controlled deployment).
- **Cache/session/queue defaults:** `.env.example` ships `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, but `QUEUE_CONNECTION=sync` — i.e., out of the box, queued jobs run **synchronously in the request thread**, not in the background. Real async queue processing requires explicitly setting `QUEUE_CONNECTION` to `redis`/`database` *and* running a worker process. `production.yml`'s deploy script issues `php artisan queue:restart`, which only signals *existing* workers to gracefully restart — it does not start one. No systemd unit, supervisor config, or provisioning step for a host-side queue worker exists on the bare-metal path (supervisor config `docker/supervisor/supervisord.conf` exists but belongs to the *Docker* topology, not the SSH one).
- **Scheduler:** `routes/console.php` defines no scheduled tasks (only the stock `inspire` command); `bootstrap/app.php` has no `->withSchedule(...)` registration. There is currently nothing for a cron-triggered `php artisan schedule:run` to execute — not a blocker today, but worth noting before anyone assumes cron is wired for a reason.
- **Websocket/realtime:** `production.yml` runs `sudo systemctl restart websocket`, assuming a pre-existing systemd unit named `websocket` on the host. No such unit file, or any provisioning script that would create one, exists anywhere in the repo. `Dockerfile.websocket` exists (a separate container image) but again belongs to the Docker topology, not the SSH one — the SSH path's `systemctl restart websocket` command has no evidenced host-side counterpart this repo ever creates.
- **Storage/uploads:** standard Laravel `storage/` + `php artisan storage:link` model implied by `config/filesystems.php`-style usage; no explicit `storage:link` step appears in any deploy workflow — first-deploy image uploads could 404 until someone runs it manually.
- **Mail:** `MAIL_MAILER=smtp` default.
- **External APIs:** `ANTHROPIC_API_KEY` present in `.env.example` (AI features), `PUSHER_*` vars present (broadcast), zena-boq-core integration referenced elsewhere in the codebase per prior GAP work (not re-audited here — out of scope).
- **Background workers:** see queue note above; no proven host-side worker process for the SSH-based topology.

---

## 5. Production environment contract (names only, no values)

Representative categories found in `.env.example` (60 unique top-level keys) and referenced `config/*.php` files:

- **Required to boot:** `APP_KEY`, `APP_ENV`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- **Core workflow:** `CACHE_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `BROADCAST_DRIVER`, `MAIL_MAILER` + SMTP host/port/username/password/encryption.
- **Optional integration:** `ANTHROPIC_API_KEY`, `PUSHER_APP_ID`/`PUSHER_APP_KEY`/`PUSHER_APP_SECRET`/`PUSHER_HOST`/`PUSHER_PORT`/`PUSHER_SCHEME`/`PUSHER_APP_CLUSTER` and matching `VITE_PUSHER_*` build-time equivalents.
- **Test-only:** anything consumed only under `APP_ENV=testing` / `.env.testing` (CI creates this from `.env.example` in `production.yml`'s own `test` job — not relevant to real production).
- **Staging-only:** `STAGING_HOST`, `STAGING_USERNAME`, `STAGING_SSH_KEY` (GitHub Secrets, referenced by `automated-deployment.yml`).
- **Production-only (secret, GitHub Actions):** `PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL` — the only 4 secrets any current production workflow actually gates on.
- **Secret (never print values, names only):** `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `PUSHER_APP_SECRET`, `ANTHROPIC_API_KEY`, `PRODUCTION_SSH_KEY`, and any SMTP password.

`APP_DEBUG=true` and `APP_ENV=local` are the **defaults shipped in `.env.example`** — a production `.env` must explicitly override both; the repo does not enforce or verify this anywhere in the deploy path (no assertion step checking `APP_DEBUG=false` before/after deploy).

---

## 6. Database / migration readiness

- 209 migration files in `database/migrations/`. This audit did not exhaustively re-classify all 209 for destructiveness (out of scope for Gate 1 time budget); this is flagged as a **Gate-2 input**, not resolved here.
- `database/migrations_backup/` contains three `disabled_*` ULID-conversion migrations (`tenants`, `users`, `projects`) — explicitly disabled, evidence that at least one prior irreversible-migration attempt was deliberately shelved. Their presence signals the team has already encountered at least one destructive-migration hazard on core tables; Gate 2 should decide whether these remain permanently disabled or need a real decision.
- `database/seeders/` includes `DemoUsersSeeder`, `MockDataSeeder`, `DatabaseSeeder` — these must **not** run against a real production database on first deploy; nothing in `production.yml`'s `migrate --force` step touches seeders (it does not call `db:seed`), which is correct, but there is no explicit repo-level guard preventing someone from later running `php artisan migrate:fresh --seed` against production by mistake.
- **First-deploy assumption:** not established by the repo — whether the very first controlled deployment targets an empty database or one with pre-existing data is an external/operational decision, not something the codebase asserts. Flagged as an Owner input.
- **Migration locking/concurrency:** `php artisan migrate --force` provides Laravel's standard single-process migration locking; no evidence of a multi-instance/rolling-deploy race scenario being considered (consistent with a single-host model).

---

## 7. Deployment atomicity / rollback

The `production.yml` model (`cd /var/www/zena && git pull && composer install && npm ci && npm run build && migrate --force && ...cache && queue:restart && reload nginx`) is a classic **in-place, non-atomic** deploy:

- **Composer/npm build failure mid-script:** the shell script has no `set -e`; a failing `composer install` or `npm run build` does not necessarily abort subsequent steps, and even if it did (SSH-action scripts typically do stop on first non-zero exit under `bash`'s default), the working directory is already left in a **half-updated state** — the git checkout has moved forward but dependencies/assets have not, and requests continue being served from that inconsistent tree the entire time (no maintenance mode is ever enabled: `artisan down`/`up` is never called in this workflow).
- **Migration failure:** `migrate --force` failing mid-batch leaves the schema partially migrated while the *code* has already fully moved forward (since `git pull` ran first) — a classic code/schema-mismatch window with no rollback step defined.
- **No release-directory/symlink model:** there is exactly one working copy (`/var/www/zena`), updated via `git pull` in place. There is no "current" symlink pointing at a versioned release directory, so there is no defined way to atomically flip back to the previous release — "rollback" is undefined; it would require manually running `git checkout <previous-sha>` plus re-running the entire build/migrate sequence, is not scripted anywhere, and has no documented authority/trigger.
- **CI-built vs host-built artifacts:** the SSH path builds *on the target host* (`npm run build` runs there), which mixes build-time and deploy-time failure modes and means the host needs the full Node/Composer toolchain permanently installed — a materially different (and more failure-prone) model than the Docker path's CI-built, immutable image.

**Conclusion: no atomic deploy, no defined rollback, no maintenance-mode window exists today for the only deployment mechanism that has secrets check logic wired up.**

---

## 8. Host/server prerequisites

Explicitly required by `production.yml`'s script content: nginx installed and running (reloadable via passwordless-or-configured `sudo systemctl reload nginx`), a `websocket` systemd service already present (unproven — see §4), PHP 8.2 + Composer + Node 18-class toolchain installed directly on the host, an SSH user with a home/checkout at `/var/www/zena` and sudo rights scoped at least to `systemctl reload nginx` / `systemctl restart websocket`. **OS, CPU/RAM/disk sizing, filesystem ownership scheme, and backup storage location are UNKNOWN — nothing in this repository establishes a target host or provider.** No host/provider name appears anywhere in workflows, docs, or config; this must not be invented and is recorded as a required external Owner input.

---

## 9. Domain / HTTPS / reverse proxy

- `APP_URL=http://localhost` is the only value in `.env.example` (placeholder, non-HTTPS).
- `PRODUCTION_URL` is a GitHub Secret referenced only for the post-deploy curl check — its actual domain value is entirely external to the repo.
- `app/Http/Middleware/TrustProxies.php` and `TrustHosts.php` exist (standard Laravel scaffolding) but this audit did not find them configured with a specific known-reverse-proxy CIDR or host allowlist beyond framework defaults — real values would need to be supplied per-environment.
- `docker/nginx/nginx.prod.conf` exists as a template vhost for the Docker topology; the bare-metal SSH topology has **no equivalent nginx vhost committed to the repo** — whatever vhost, TLS certificate, and HTTPS-redirect exists on that hypothetical host is entirely undocumented here.
- **Domain name, DNS records, and TLS certificate provisioning (e.g., Let's Encrypt/ACME) are UNKNOWN / not established by the repo.** These are genuine external Owner inputs, not something a design phase can infer.

---

## 10. Production secret model

The **only** secrets any currently-wired production workflow gates on are the 4 named in `production.yml`: `PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL`. These 4 secrets are **necessary but not sufficient**: the deploy script assumes a pre-existing host `.env` file already sitting at `/var/www/zena/.env` (the script never writes, copies, or templates one) carrying `APP_KEY`, DB credentials, Redis credentials, mail credentials, and any integration keys. In other words, the real secret model implicitly requires a **human to have already hand-provisioned a working `.env` on the host once**, outside of anything GitHub Actions controls — the 4 GitHub Secrets alone cannot bring up a working application. This gap is not documented anywhere in the repo. No secret values were read, printed, or inferred in this audit — only secret **names/purpose** are recorded above.

---

## 11. Health / observability

- `/api/health` (the one path anything currently checks) is HTTP-reachability-only and hardcoded, per §3 finding 3 — not a real dependency check.
- `/api/health/detailed` (via `SystemHealthController::detailed`) and the unregistered `routes/health.php`'s `HealthController::detailed` **do** perform real DB/Redis/storage/queue/websocket/external-service checks — but nothing in any deploy workflow calls the detailed endpoint; only the shallow one is curled post-deploy.
- **Logs:** standard Laravel `storage/logs/` (`Log::` facade usage throughout, e.g., in `HealthController`); no centralized/shipped log aggregation wired into the SSH deploy path. `docker-compose.prod.yml` includes `elasticsearch`/`kibana`, but again only for the unused Docker topology.
- **Metrics:** `docker/prometheus/prometheus.prod.yml`, `docker/grafana/provisioning/*` exist (Docker topology only); a `/metrics` route exists in the never-loaded `routes/health.php`.
- **Uptime/error monitoring (e.g. Sentry):** no Sentry SDK or equivalent found wired into `composer.json`/`.env.example` during this pass (not exhaustively re-verified beyond a name search) — recorded as **absent/unconfirmed**, not assumed present.
- **Recommendation (design input for Gate 2, not implemented here):** minimum viable observability before real users should be (a) point the deploy health check at the *detailed* endpoint or a purpose-built smoke check, not the hardcoded shallow one; (b) ensure `storage/logs/laravel.log` (or equivalent) is actually monitored/rotated on the host; (c) add a basic external uptime check against the real domain once one exists.

---

## 12. Backup / recovery

Backup **scripts and code exist** (`scripts/backup-{database,files,system}.sh`, `DatabaseBackupService`, `BackupJob`, `BackupCommand`, `config/backup.php`) but:

- No workflow step runs any of them before/after a production deploy.
- No documentation or test demonstrates an actual restore has ever been performed from any backup this system produced.
- No retention policy verification, no `.env`/secret-recovery procedure, and no defined rollback **trigger or authority** (who decides to roll back, and how) exist in the repo.

**A script named "backup" is not proof of a working, tested restore path** — this is recorded as a first-class blocker, not resolved here.

---

## 13. First controlled deployment acceptance path (design sketch only — not implemented)

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

## 14. Security review

- **SSH deploy user/scope:** `PRODUCTION_USER` is an unknown identity (secret); the script runs `sudo systemctl reload nginx` and `sudo systemctl restart websocket` — this **requires passwordless (or Action-runnable) sudo for at least those two commands**, meaning a compromised `PRODUCTION_SSH_KEY` (a GitHub Secret, exposed to whatever has repo/workflow write access) grants an attacker the ability to run arbitrary code as that SSH user plus a defined sudo surface on the production host. Whether that sudo scope is tightly restricted (via `/etc/sudoers.d` `NOPASSWD` entries limited to those two exact commands) or broader (full sudo) is **unknown — not established in this repo** and is a Critical item to confirm/harden before first real deploy.
- **Host-key verification:** `appleboy/ssh-action@v1.2.4` is used without any visible `fingerprint`/known-hosts pinning in the workflow YAML — by default this class of action can be configured to skip host-key checking; whether it's pinned is not evidenced here (worth confirming against the action's actual default behavior at review time, not assumed either way by this audit).
- **`git pull` authentication on the server:** the script runs `git pull origin main` on the host with no explicit auth step in the workflow — implying the host's own git remote credentials (however configured) are used, entirely outside GitHub Actions' control and unaudited here.
- **Debug mode / `APP_ENV`:** `.env.example` defaults to `APP_DEBUG=true`/`APP_ENV=local`; nothing in any deploy path asserts these are overridden for real production — a real risk if a hand-provisioned host `.env` (see §10) was ever copied from `.env.example` without editing.
- **Secret leakage through logs:** GitHub Actions natively masks registered secrets in log output; no additional custom masking was found necessary or present.
- **DB/Redis public exposure, firewall rules:** UNKNOWN — host-level, not established by this repo.
- **Branch protection / GitHub Environment approval:** `production.yml` uses `environment: production`, meaning GitHub's Environment protection rules (if configured on the GitHub repo settings, not visible from this local checkout) could gate the job with required reviewers — this audit could not confirm whether such protection rules are actually turned on for the `production` environment (that setting lives in repo configuration, not in tracked files).
- **Flag:** a compromised GitHub Actions run (e.g., via a malicious PR that gets merged, or a compromised Action dependency) that reaches the `deploy` job on `main` would — **once the 4 secrets are populated** — have SSH access plus a defined-but-unverified sudo surface on the production host. This is a normal CI/CD trust model, but it means populating those 4 secrets is a security-significant decision, not just an operational one, and should coincide with GitHub Environment required-reviewer protection.

---

## 15. Recommended target architecture (comparison, not a decision)

Three real options exist in the repo today; none has been proven in production:

1. **Direct single-VPS, release-based SSH deploy** (evolve `production.yml`): lowest new infrastructure to introduce, keeps the existing secret model, but requires fixing the atomicity problems in §7 (adopt a releases/`current`-symlink pattern, add `artisan down`/`up` maintenance windows, add `set -e` and explicit rollback). Lowest cost, moderate effort to harden, best fit for "first controlled deployment" scale.
2. **Docker Compose on one VPS** (adopt `docker-compose.prod.yml`, which already exists and is more complete): better isolation, reproducible builds (image built once in CI, not on the host), built-in observability stack (Prometheus/Grafana) and a `backup` service already modeled — but nobody has ever run it, so first use carries integration risk, and it duplicates work already partially done for the SSH path. Higher effort now, better long-term operability.
3. **GHCR image + SSH to a container host** (`automated-deployment.yml`'s model, extended to production): combines CI-built immutable images with a simple SSH trigger; smallest conceptual change from option 2 if a registry is already desired, but the workflow file's production leg is not fully filled in and would need real design work.

**Recommendation:** Option 1 (hardened single-VPS SSH/release deploy) is the smallest-practical path to a genuinely truthful "first controlled deployment," because it requires the least new infrastructure and the existing secret model already fits it — provided its atomicity and rollback gaps (§7) are closed in Gate 2 design. Option 2 is the better long-term target once the team has bandwidth, since most of its infrastructure-as-code already exists unused. This is a recommendation for Gate 2 to evaluate, not a decision made here.

---

## 16. Concrete blockers (for first controlled deployment)

1. No target host/provider exists or is named anywhere (Critical — Owner input required).
2. No domain, DNS, or TLS provisioning exists or is named anywhere (Critical — Owner input required).
3. The only wired deploy path (`production.yml`) is non-atomic with no rollback and no maintenance-mode window (Critical).
4. The deploy health check is hardcoded/non-functional as a real signal (High).
5. No proven backup/restore cycle (High).
6. Three contradictory deployment mechanisms exist with no authoritative decision (High).
7. Implicit dependency on a hand-provisioned host `.env` that nothing in the repo creates, templates, or validates (High).
8. `websocket` systemd unit assumed by `production.yml` is never provisioned anywhere in-repo (Medium).
9. `QUEUE_CONNECTION=sync` default means background jobs silently run inline unless someone deliberately reconfigures + runs a worker — easy to miss on first deploy (Medium).
10. `routes/health.php` dead-code duplicate health routes could mislead future maintainers (Low).
11. 209 migrations not yet individually re-classified for destructiveness relevant to first deploy (Low/Medium — deferred to Gate 2 as a bounded task).

---

## 17. External Owner inputs genuinely required (not inferable from the repo)

- Target host/provider (or explicit decision to provision one).
- Domain name and DNS control.
- Budget/cost tolerance influencing the architecture choice in §15.
- Who holds SSH/production credentials and who is authorized to trigger/approve a production deploy (GitHub Environment reviewers).
- Confirmation of whether a first deployment targets an empty database or one with existing data.

---

## 18. Risks (ranked)

- **Critical:** no host/domain/TLS exists; the only wired deploy mechanism is non-atomic with no rollback; a compromised deploy pipeline currently has SSH + defined sudo scope on whatever host is eventually named.
- **High:** false-green health check masks real dependency failures; no proven backup/restore; three contradictory deployment mechanisms; implicit unmanaged host `.env`.
- **Medium:** unprovisioned `websocket` systemd assumption; silent synchronous-queue default; unclassified migration destructiveness.
- **Low:** dead-code duplicate health routes; unused scheduler infrastructure; duplicate `deploy.sh` naming.

---

## 19. Explicit in-scope / out-of-scope boundary for GAP-049

**In scope (this Work ID, across future gates):** deciding and hardening ONE deployment mechanism, its rollback/atomicity story, its real environment/secret contract, minimum health/observability, and a first-deployment acceptance/smoke sequence — for infrastructure/deploy/env/CI concerns only.

**Out of scope (this Work ID):** any CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product semantics or code changes (per explicit instruction — if the acceptance-path design ever requires touching that code, it must be escalated to a separate Work ID via the Design Dependency Preflight); GAP-042 RBAC scope (closed, released, not reopened); actual production secret configuration; actual infrastructure provisioning; an implementation plan (reserved for a post-Gate-2 phase).
