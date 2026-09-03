---
work_id: GAP-049
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-049/02-design.md
---

# GAP-049 Gate 2 Design — Production Deployment Architecture

**Date:** 2026-09-03
**Gate 1 status:** APPROVED (Round 3, PR #300, merged to main at `8efe40d6cfac4e5fb3b7bed9910af1db353731cf`).
**Canonical main SHA at Gate-2 baseline:** `8efe40d6cfac4e5fb3b7bed9910af1db353731cf`.
**This is a DESIGN document.** It does not implement anything, configure any secret, provision any host, or perform any deployment. It compares two hardening candidates against the Gate-1 evidence, recommends one, and specifies the target deployment lifecycle and contracts at a design level — implementation details (exact scripts, exact GitHub Actions YAML diffs) are Gate-3/implementation-phase work, not decided here.

---

## 1. Inputs carried forward from Gate 1 (not re-litigated here)

- Three executable deployment workflows exist (`production.yml`, `deploy.yml`, `automated-deployment.yml`); one placeholder (`ci-cd.yml`'s `deploy` job).
- No successful real production deployment was proven by the repository and Actions evidence reviewed (669 runs across all three, retention/API-completeness caveats apply).
- No authoritative, proven production-safe rollback path currently exists (per-workflow: `production.yml`/`deploy.yml` have none; `automated-deployment.yml` has code that is unsafe-by-default against forward migrations and never executed).
- `deploy.yml`'s underlying `deploy.sh` cannot currently complete against `main` as written (missing `npm run production` script), assumes stale `php8.1-fpm`, requires an unprovisioned `DB_PASSWORD`, and unconditionally seeds a hardcoded-password demo admin account via `db:seed --force`.
- `production.yml`'s post-deploy health check is a hardcoded JSON literal, not a real dependency probe.
- `automated-deployment.yml` is materially more complete than a first read suggests: real backup hook (`docker-manage.sh backup`/`restore`, itself unproven end-to-end), a rollback job (code-only, schema-unsafe by default), blue-green and canary job skeletons whose traffic-cutover steps are `echo`-only placeholders, and multi-endpoint health/smoke/performance checks against domains not otherwise evidenced in this repo.
- Full evidence: `docs/audits/2026-09-03-gap-049-production-readiness-evidence.md` (Gate-1, Round 3, Owner-approved).

## 2. Decision to make: ONE target architecture for the first controlled production deployment

Per Owner Gate-1 Round-3 approval, Gate 2 compares exactly two hardening candidates and treats a third as a deprecation item — not three co-equal options.

### Candidate A — Hardened release-based SSH deploy (evolves `production.yml`)

**Architecture direction unchanged from Round 1 (not reopened). The following load-bearing contracts are corrected/strengthened per Owner Gate-2 Round-1 CHANGES REQUESTED — each contract below is named (`A-1`, `A-2`, ...) so later sections can reference it precisely instead of by a renumberable list position.**

#### A-1. Merge/release vs. production-deployment separation, and the human-approval gate (Round-1 correction 1)

A merge to `main` must **not**, by itself, constitute production deployment during the first-controlled-deployment/pilot phase. This is a hard design contract, not an implementation detail:

- CI/main integration (tests, lint, `Owner Governance Lint`, `Routes Guardrails`, etc.) stays automatic on every push to `main`, as today — this is *integration*, not *deployment*, and nothing about it changes.
- **Production deployment is a distinct, explicitly-invoked event** — via `workflow_dispatch` (a human clicks "Run workflow" with an exact input) or an equivalent explicit production-deploy trigger (e.g. a GitHub Release being published, if the team later prefers that model) — **never** `on: push: branches: [main]` during the pilot phase. This directly supersedes `production.yml`'s current `push`-to-`main` trigger.
- **Deployment is bound to an exact, immutable Git commit SHA or release tag**, supplied as an explicit input to the trigger (e.g. `workflow_dispatch.inputs.sha` or a tag ref) — never an implicit "whatever `main` currently is at run time." The deploy job's first step verifies the checked-out commit's SHA matches the requested input exactly and fails closed on any mismatch (protects against a race where `main` moves between trigger and checkout).
- The production job uses GitHub `environment: production` (as `production.yml` already does), and **a human production-approval gate is required before any production host/database mutation** — implemented via GitHub Environment required reviewers where the plan/permissions support it. **Fallback if GitHub-plan/environment limitations prevent required-reviewer enforcement:** an explicit authorized-manual-dispatch model — only a named, access-controlled set of humans may trigger the `workflow_dispatch` at all (enforced via repository/environment permissions, not merely convention), and the act of manually dispatching *is* the approval, recorded in the Actions run's actor field. The design must never silently fall back to automatic deploy-on-main merely because required-reviewer enforcement is unavailable on the current GitHub plan.
- **Future auto-deploy after the pilot phase is explicitly out of scope for this Gate-2 decision** and requires a separate, later, explicit Owner decision — this design only covers the first-controlled-deployment/pilot period.

**Binding Owner clarification (Gate-2 Round 2): trusted exact-SHA source delivery.** Verifying the checked-out SHA matches the requested input (above) is necessary but not sufficient — it does not by itself specify *how* that exact source reaches the host, and the approved architecture must not reproduce a Gate-1-style implicit-host-git-credential problem (where the host's own git remote credentials, entirely outside this design's control, determine what gets deployed). Implementation planning (a future session) must choose exactly one of:
- **(a) Preferred — CI-delivered exact release.** CI checks out the exact requested SHA, verifies it, builds/prepares the release artifact there, and transfers that exact, already-verified release to the host over the approved deployment channel (e.g. via the same SSH action already used for the deploy step, or an artifact-transfer mechanism decided at implementation time). The host never independently decides what to fetch.
- **(b) Acceptable alternative — host-side fetch of the exact SHA.** The host fetches the exact requested SHA from GitHub using a dedicated, read-only repository/deploy credential (e.g. a deploy key or fine-grained PAT scoped to read-only access on this repository only) — never a broad, write-capable, or account-level GitHub credential sitting on the production host merely to perform a deploy.

In either case, the following are fixed, non-negotiable properties of the chosen mechanism: no `git pull origin main` (or any other mutable-branch reference) anywhere in the production path; no deploying "whatever `main` currently points to" at host-fetch time; no implicit dependency on a mutable branch ref for what gets deployed; no broad write-capable GitHub credential provisioned on the production host merely to enable deployment; the exact requested SHA is verified before the release is activated (i.e. before the `current` symlink ever points at it), regardless of which of (a)/(b) is chosen. The implementation plan must state explicitly which of (a) or (b) was chosen and why — this design does not pre-select between them, only fixes the properties both must satisfy.

#### A-2. Deployment state machine (Round-1 correction, "Additional")

The truthful-status model is corrected to six states, replacing the five-state model from Round 1 (which risked conflating workflow "success" with "production is healthy"):

| State | Meaning |
|---|---|
| `not_configured` | No deployment attempted (secrets/trigger absent) — must never be reported as, or confusable with, "production succeeded." |
| `attempted` | A production deployment was explicitly triggered and started. |
| `failed` | Deployment did not reach a usable state (build, migration, or cutover failure). |
| `deployed_unverified` | Code cutover (`current` symlink switch) occurred, but the readiness gate (A-4) and smoke sequence (§6) have not yet proven the deployment usable. |
| `health_verified` | The required readiness gate (A-4) passed post-cutover — this is the **only** state that may be described as a successful, usable production deployment. |
| `rolled_back` | A prior deployment was reverted per the migration-classification-aware rollback contract (A-5). |

**Binding rule:** the production workflow's overall "success"/green result must only ever mean one of: (a) the truthful `not_configured` state is accurately represented as such (not silently dressed up as a deploy), or (b) an actual deployment reached `health_verified`. A workflow reporting green while sitting at `attempted`, `failed`, or `deployed_unverified` is a defect, not an acceptable interim state. If post-cutover health fails: mark the deployment `failed` (not silently `deployed_unverified` forever); execute the code rollback (A-5) **only if** the migration-classification contract (A-5) says rollback is safe for the migrations that ran; otherwise the system enters/remains in maintenance (using A-3's shared, `current`-visible maintenance-mode mechanism) pending the migration-specific recovery runbook — automatic schema rollback is never invented or assumed.

#### A-3. Release / shared-filesystem contract (Round-1 correction 2)

Persistent mutable host state, with semantics fixed as follows (exact path names may vary at implementation time):

```
/var/www/zena/
  current -> releases/<exact-sha>/          # atomic symlink, always resolves to the release actually serving traffic
  releases/<exact-sha>/                     # one immutable directory per deployed commit: app code, vendor/, built frontend assets
  shared/.env                               # production environment file — provisioned once, out-of-band, NEVER inside a release directory
  shared/storage/                           # Laravel storage/ — uploads, documents, framework cache/session/view files, logs
```

- App code, `vendor/`, and built assets belong exclusively to an immutable `releases/<sha>/` directory — never mutated in place after being built.
- `.env` is shared, lives outside any release directory, and is provisioned/rotated independently of any deploy.
- Laravel's `storage/` directory is shared across all releases (symlinked from each `releases/<sha>/storage` to `shared/storage`) — uploads, generated documents, and logs survive every deployment and every rollback, because they are never release-scoped.
- **Every new release links to `shared/.env` and `shared/storage` BEFORE any Artisan command that depends on them runs** (config loading, migrations, cache commands) — the link step is not an afterthought after `artisan` calls have already run against a release-local, empty state.
- `public/storage` (and any other required storage symlink) resolves through to `shared/storage`, not a release-local copy.
- Permissions/ownership are explicit: the deploy user owns `releases/*` and can write `shared/storage` and read (not necessarily write) `shared/.env`; the web server user (e.g. `www-data`) has read access to `current` and read/write access to `shared/storage` — exact UID/GID mapping is an implementation-time (Gate-3) detail, but the ownership *model* (deploy user builds, web server user serves and writes uploads) is fixed here.
- **Release cleanup (pruning old releases to save disk) must never delete anything under `shared/`** — cleanup logic is scoped exclusively to old, no-longer-`current`, no-longer-rollback-target entries under `releases/`.
- **Maintenance-mode visibility:** Laravel's maintenance-mode state (`artisan down`/`up`) must be visible to whichever release is currently `current` — i.e. maintenance state must live in `shared/` (e.g. `shared/storage/framework/down`, which is where Laravel's default maintenance-mode file already lives when `storage/` is shared per the contract above) or another mechanism that actually affects the currently-serving release. A maintenance flag written only into a new, not-yet-`current` release directory has no effect on real traffic and does not satisfy this contract.

#### A-4. Minimal production readiness endpoint + queue canary (Round-1 correction 4, replaces the Gate-1 "point at `/api/health/detailed`" assumption)

**Do not bind this design to the existing `/api/health/detailed` endpoint as-is.** Gate-1 evidence (and direct re-confirmation in this round) shows it returns PHP/Laravel version, `APP_ENV`, memory/load metrics, and other diagnostic internals in a publicly reachable response — inappropriate to expose and inappropriate to bind a go/no-go deploy gate to as specified.

**Minimal production readiness contract (new, purpose-built for this design):**
- Returns **HTTP 200** only when every *synchronous* dependency required to serve a request is ready; **HTTP 503** otherwise.
- Performs a genuine database probe (a real, trivial query — not a hardcoded string).
- Performs a genuine cache probe (a real write/read round-trip against the configured cache store — not a hardcoded string).
- Performs a genuine shared-storage probe (a real write/read/delete against `shared/storage`) **if local storage participates in serving production requests** — if storage is fully offloaded to a remote object store instead, this probe targets that store instead.
- **No** PHP/Laravel version, `APP_ENV`, memory/load/CPU metrics, credentials, internal topology, or other diagnostic internals appear in the response body — minimal body (e.g. `{"status":"ready"}` or `{"status":"not_ready","failed":["database"]}`, no more).
- No hardcoded/default-success dependency status is ever returned for a dependency this endpoint claims to check.

**Queue-worker liveness is explicitly NOT claimed by this HTTP endpoint** — an HTTP 200 from the readiness endpoint proves nothing about whether a queue worker process is actually running or processing jobs, and this design does not pretend otherwise. Instead, first-controlled-deployment evidence for the queue uses a **queue canary**: enqueue a unique probe job as part of the deploy/smoke sequence; the real worker process (provisioned per A-7) processes it; the deploy/smoke step polls (bounded wait, e.g. a fixed timeout) for the probe's completion marker; timeout is treated as a deployment/smoke failure (contributing to `failed`/`deployed_unverified`, never silently ignored). This is intentionally narrow — it proves "a worker is alive and processing," not a general observability project, which stays out of scope per YAGNI (§4).

#### A-5. Migration classification, cutover, and rollback semantics (Round-1 correction 3)

`migrate --force --isolated` alone is **not** sufficient as a safety contract — it only prevents two migration *commands* from racing each other (mutual exclusion via Laravel's cache lock); it says nothing about whether a given migration is safe to run ahead of a code cutover, or safe to leave in place after a rollback. Corrected contract:

- **Deployment-level serialization** (no two `workflow_dispatch` production deploys running concurrently) is enforced by the workflow's `concurrency: { group: production-deploy, cancel-in-progress: false }` block (A-1/A-3 territory) — this is a distinct mechanism from `--isolated`'s migration-command-level mutual exclusion; the two are **not** interchangeable and both are used for what they actually guarantee, not conflated.
- **Every migration bundled in a deploy must be classified before deployment**, into exactly one of:
  - **(A) Expand / backward-compatible** — adding a nullable column, adding a new table, adding a compatible index, or any other change the *old, still-`current`* release's code can tolerate unchanged. These **may** run from the new release, against the shared database, before the `current` symlink switches — the old release keeps serving correctly against the now-expanded schema during that window, so there is no unsafe interval.
  - **(B) Breaking / contract / destructive** — dropping or renaming a column the current code reads/writes, a destructive data conversion, or an incompatible constraint change. These **must not** run as an ordinary zero-downtime pre-switch migration. They require: explicit classification as breaking before the deploy is triggered; a fresh backup (A-6) taken immediately before running them; an actual maintenance window (A-3's shared maintenance-mode mechanism) that takes real traffic out of service for the duration; a migration-specific forward/rollback/data-fix runbook written before the migration runs, not improvised after a failure; and a passing readiness check (A-4) before maintenance mode is lifted.
  - **Default policy for this first-deployment architecture: prefer expand/backward-compatible migrations.** A breaking migration is the exception requiring the full runbook above, not a routine occurrence.
- **Rollback is never an unconditional `migrate:rollback`.** Code rollback (re-pointing `current` to the previous release) is fast and safe on its own *for the code*. Whether to also touch the schema depends entirely on the classification above:
  - After an **expand** migration: a code rollback correctly **leaves the expanded schema in place** — the previous release's code already tolerates it (that was the definition of "expand"), so nothing further is needed.
  - After a **breaking** migration: "rollback" is not simply switching `current` — it requires maintenance mode (already active per the breaking-migration runbook above) plus the migration-specific data/schema recovery procedure written as part of that runbook. The design does not invent or assume an automatic schema-rollback mechanism for this case.

#### A-6. Least-privilege, evidence-based backup/restore contract (Round-1 correction 5)

Preserves the Gate-1 goals (pre-migration backup, durable, off-host, restore proven) but **does not bind Candidate A to `mysqldump --all-databases`** as Gate 1's evidence review found `docker-manage.sh` using (a different workflow's mechanism, not adopted verbatim here):

- **Scope of what is backed up:** (1) the actual ZENA production application database only — not unrelated system/other databases that might happen to share the same MySQL instance; (2) shared persistent application storage (`shared/storage` per A-3) — uploads/documents that would otherwise be unrecoverable; (3) any other explicitly identified mutable production state later found necessary for recovery (identified at Gate-3/implementation time, not invented here).
- **Mechanism:** either a dedicated, least-privilege backup database credential (scoped to `SELECT`/`LOCK TABLES`/`SHOW VIEW` on the ZENA application database only — not broad MySQL administrative privileges), or a host/provider-native database snapshot/backup mechanism, depending on the eventual host choice (§5) — the exact mechanism is a Gate-3 decision informed by which host is actually chosen; this design fixes the *scope and privilege* contract, not the specific tool.
- **Durability:** backup artifacts are written off the application host's own disk (correcting the Gate-1 finding about `deploy.sh`'s self-defeating in-checkout, same-disk, deleted-on-success "backup") — off-host storage, a separate volume, or a provider snapshot mechanism, any of which satisfy "does not share a single-disk failure domain with the running application."
- **Restore-drill acceptance (required before this architecture is considered complete, not optional polish):** executed in a **disposable, non-production environment** — never against production data to "prove" restore works. The drill: restore the backed-up database into the disposable environment; restore representative shared storage; boot the application against the restored state; prove that representative database rows and at least one representative uploaded file are genuinely usable (readable, correct) from that restored state — not merely that the restore command exited zero. Capture evidence: which backup identifier/hash/timestamp was restored, and the result of the usability check. **Production data must never be destroyed or mutated merely to prove restore works.**
- **Retention/encryption/access policy (minimal, stated explicitly rather than left implicit):** backups are retained for a Gate-3-specified minimum window (e.g. a rolling N most-recent daily backups — exact N is an implementation detail, not fixed here); backups containing production data are encrypted at rest wherever the chosen storage mechanism supports it; access to backup artifacts is restricted to the same credential-holder set as production access itself (§5), not broadened separately.

#### A-7. Host provisioning contract (updated to reference A-3's shared-storage model and A-4's queue canary)

A documented (not automated in this Gate) one-time host setup checklist: PHP 8.2-fpm (matching `composer.json`, correcting the stale 8.1 assumption found in Gate 1), Composer, Node matching `Dockerfile.prod`'s pinned version, nginx, a systemd unit for the queue worker (currently absent — Gate-1 finding; this is the same worker process A-4's queue canary proves is alive), a systemd unit for the websocket service (currently assumed but never provisioned — Gate-1 finding), and sudoers scoped to exactly the commands the deploy user needs (`systemctl reload nginx`, `systemctl restart <queue-worker-unit>`, `systemctl restart websocket` — nothing broader). Provisioning also establishes the `shared/.env` and `shared/storage` directories (A-3) once, before the first release is ever deployed.

#### A-8. Production `.env`/secret contract

The 4 existing GitHub Secrets (`PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL`) remain necessary but not sufficient: `shared/.env` (A-3) is provisioned once, out-of-band, by whoever holds production credentials (§5), and the deploy workflow asserts its presence (fails loudly if absent) rather than assuming it. `QUEUE_CONNECTION` in `shared/.env` is explicitly set to a real backend (`redis` or `database`), never left at the framework default `sync` — this is what makes the A-4 queue canary meaningful rather than vacuous.

#### A-9. Logs/observability (unchanged from Round 1)

Minimum viable: confirm `shared/storage/logs/laravel.log` (per A-3, shared and therefore not orphaned across releases) is captured/rotated on the host; defer full APM/Sentry adoption as a later enhancement, not a Gate-2 blocker.

#### A-10. SSH least privilege / host-key verification (unchanged from Round 1)

Pin `appleboy/ssh-action`'s host-key fingerprint explicitly rather than relying on default behavior; scope the deploy SSH user's sudo rights to the exact commands in A-7, nothing broader.

#### A-11. Domain/TLS (unchanged from Round 1)

Out of this design's control — depends on the external host/domain decision (§5). The design assumes nginx terminates TLS via a certificate provisioning mechanism (e.g. Let's Encrypt/ACME) set up once during host provisioning (A-7), not per-deploy.

#### A-12. First controlled-deployment acceptance evidence

See §6 (updated in this round to reference the A-4 queue canary and the production-safe bootstrap contract, §3a).

**Effort/complexity:** Low-to-moderate — evolves an existing, simpler mechanism. Does not require the team to adopt Docker/container operations if they haven't already. **Cost:** no new infrastructure spend beyond the already-assumed single host. **Maintainability:** straightforward for a team already comfortable with bare-metal Linux/SSH operations.

### Candidate B — Hardened Docker/GHCR/Compose (evolves `automated-deployment.yml`)

**What changes from today:**
1. **CI-built immutable image advantage.** Already present — `docker/build-push-action` builds once in CI and pushes to GHCR; every environment (staging/production) pulls the same tested artifact, avoiding Candidate A's host-side build variability (Node/Composer version drift). This is a genuine structural advantage over Candidate A.
2. **GHCR permissions.** Uses `secrets.GITHUB_TOKEN` (scoped to the repo, expires with the workflow run) rather than a long-lived registry credential — already reasonably safe, but Gate 3 must confirm the package visibility (public vs. private) and who/what can pull it.
3. **Docker host prerequisites.** Docker Engine + Docker Compose v2 installed on the host; this is a real, non-trivial prerequisite the SSH bare-metal candidate does not have — must be part of the host-provisioning contract if this candidate is chosen.
4. **`docker-compose.prod.yml` production fitness.** The file defines 12 services including `elasticsearch`/`kibana`/`prometheus`/`grafana` — genuinely more than a *first* controlled deployment needs. Per YAGNI (see §3), a hardened Candidate B for GAP-049's purpose should stand up only the services actually required for correctness (`app`, `nginx`, `mysql`, `redis`, `queue`, `scheduler`, `websocket`, `backup`) and explicitly defer the observability stack (`prometheus`/`grafana`/`elasticsearch`/`kibana`) to a later phase — running them prematurely adds host resource requirements and operational surface with no first-deployment benefit.
5. **DB/storage volume persistence.** `docker-compose.prod.yml`'s named volumes (`mysql_data`, `redis_data`, etc.) must be confirmed to survive `docker-compose down`/`up` cycles (they should, by Compose's volume semantics, but this must be verified in Gate 3, not assumed).
6. **Backup/restore.** Already has a real implementation (`docker-manage.sh backup`/`restore`) — ahead of Candidate A on this axis today, though equally unproven end-to-end (no restore drill evidenced for either candidate).
7. **Rollback after migrations.** The existing `rollback` job's `git reset --hard HEAD~1` is schema-unsafe by default (Gate-1 finding) — this must be redesigned in either candidate, not inherited as-is. A hardened Candidate B should tag/pin the *previous* GHCR image digest and redeploy that exact image, plus the same migration-classification/rollback-safety framework as Candidate A's A-5 (was the migration additive/expand, or breaking/contract).
8. **Blue-green/canary — explicitly NOT adopted for first deployment (YAGNI).** The existing `blue-green-deployment` and `canary-deployment` jobs are dormant code with unimplemented, `echo`-only traffic-cutover logic (Gate-1 finding). A hardened Candidate B for GAP-049 should **not** attempt to complete or use these jobs for the first controlled deployment — they represent meaningfully more operational complexity (load-balancer/DNS traffic-splitting infrastructure this repo does not have) than a first controlled deployment justifies. Recommendation: leave them disabled/unused, revisit only after the simple path is proven.
9. **Health/smoke contract.** Already multi-endpoint and more thorough than Candidate A's current state, but references domains (`dashboard.zenamanage.com` etc.) not otherwise evidenced as real/owned — must be parameterized against whatever real domain the Owner eventually provides, not hardcoded.
10. **Secret naming.** Must resolve the `PRODUCTION_USER`/`PRODUCTION_USERNAME` inconsistency (Gate-1 finding) as part of adopting either candidate — Gate 3 should standardize on one name across every workflow that survives, retiring the other.
11. **Operational complexity.** Genuinely higher than Candidate A — the team must operate Docker Engine, Compose, and a container registry, in addition to the underlying Linux host. This is real, ongoing cost, not a one-time setup cost.
12. **Observability.** Structurally ahead of Candidate A if the full stack were adopted, but see item 4 — deferred by design for a first deployment.
13. **Resource requirements.** Running even the trimmed 8-service set requires meaningfully more RAM/CPU headroom than Candidate A's bare PHP-FPM/nginx process model, on whatever host is eventually chosen (host sizing itself is an external Owner input, §5).
14. **First controlled-deployment acceptance evidence.** See §6.

**Effort/complexity:** Moderate-to-higher — most of the pieces exist in code already, but none are proven, and adopting Docker operations is a real step-change for a team not already running it day-to-day. **Cost:** likely somewhat higher host resource requirements even trimmed. **Maintainability:** better long-term reproducibility (immutable images) once the operational learning curve is paid.

### Legacy candidate — `deploy.yml`: disposition, not a design option

Per Gate-1 evidence, `deploy.yml`'s underlying `deploy.sh` **cannot currently execute successfully** against `main` (missing `npm run production` script), independent of any hardening question. It is not being compared as a third architecture. **Disposition specified here (Gate-2 design decision, not implementation):**
1. `deploy.yml`'s `workflow_dispatch` trigger should be removed or the workflow file deleted entirely once whichever of Candidate A/B is implemented, so it can no longer be manually triggered and fail confusingly or (if someone first fixed just the `npm run production` line without noticing the other hazards) partially succeed in a way that seeds a hardcoded-password admin account into production.
2. Until removed, as an interim safety measure, Gate 3's implementation should add an immediate hard-fail guard at the top of `deploy.sh` (or remove the file's executable trigger path) rather than leave a silently-broken-but-still-invocable production entry point live.
3. No effort should be spent hardening `deploy.sh` itself — retiring it is strictly cheaper and safer than fixing it, since Candidate A already supersedes its intended purpose.

## 3. Recommendation

**Recommended: Candidate A — hardened release-based SSH deploy, evolved from `production.yml`.**

Applying the Owner-specified priority order:

1. **Getting ZENA into controlled real use soon** — Candidate A requires no new operational skill (Docker) the team doesn't already need for CI itself, and evolves a mechanism that is already 90% structurally present (secrets, SSH action, health-check hook) rather than requiring Docker Engine + Compose + GHCR to all be stood up and learned first. This wins decisively on time-to-first-deployment.
2. **Truthfulness** — both candidates need the same truthful-status-reporting work; a tie, addressed identically in either.
3. **Recoverability** — roughly comparable once hardened: Candidate A's releases-directory rollback (A-5) and Candidate B's previous-image-tag rollback are structurally similar in safety once the same migration-classification/rollback-safety framework is applied to both. Candidate B has a head start on backup/restore code existing already, but neither has a proven restore drill — this is a wash until Gate 3 evidence exists for either (Candidate A's least-privilege backup/restore contract is A-6).
4. **Security** — comparable; Candidate A's attack surface is SSH + sudo-scoped systemctl; Candidate B's is SSH + Docker socket/Compose access (arguably a *larger* privileged surface, since Docker access is commonly root-equivalent) — a mild edge to Candidate A here, not decisive on its own.
5. **Operational simplicity** — decisive edge to Candidate A: no new toolchain (Docker/Compose/registry) for the team to operate day-to-day.
6. **Maintainability** — mild edge to Candidate B long-term (immutable CI-built images avoid host-side build drift), but this matters more at scale/multi-host than for a first controlled deployment.
7. **Cost** — mild edge to Candidate A (lower host resource floor without a container runtime and even a trimmed service set).

**Why Candidate A wins overall:** criteria 1 and 5 (time-to-first-use and operational simplicity) are weighted highest by the Owner's own priority order, and Candidate A wins both clearly, while the criteria favoring Candidate B (4 is a wash-to-mild-edge-A, 6 is a real but lower-priority long-term advantage) do not outweigh that. **Candidate B's work is not wasted** — `docker-manage.sh`'s backup pattern informed A-6's least-privilege redesign rather than being re-invented from nothing, and Candidate B remains the well-positioned upgrade path if/when the team's operational needs justify the container step-change later (e.g. multi-host scaling, need for the observability stack already modeled in `docker-compose.prod.yml`).

**This recommendation is a Gate-2 design output for Owner review — it is not self-approved and does not authorize implementation.**

## 3a. Production-safe first-database bootstrap contract (Round-1 correction 6)

This contract governs how the *first* real production database is ever populated — it applies regardless of which candidate is chosen, and is a hard rule, not a preference:

- **`DatabaseSeeder` (and any seeder chain reachable from it) is NEVER run in production.** This is the same `DatabaseSeeder` Gate 1 found `deploy.yml`'s `deploy.sh` invoking via `db:seed --force`, which creates a fixed-email, hardcoded-password demo admin account — that hazard is closed permanently by this rule, for every deployment mechanism, not merely by retiring `deploy.yml`.
- **No demo/sample tenants or users are ever created in production**, by any mechanism.
- **No fixed or default password is ever used** for any account created as part of first-deployment bootstrap.
- **Bootstrap creates only the canonical minimum data required for ZENA to operate:** the real initial production tenant (using the Owner's actual organization data, not a placeholder), and one real initial administrator/operator account for that tenant.
- **Initial credential handling:** the first administrator's credential is either securely generated (e.g. a random password shown exactly once at creation time, or a signed one-time setup link) or directly supplied by the authorized Owner/operator — never a value checked into this repository or any seeder file. Where a generated credential is used, immediate secure handling (forced reset on first login, or equivalent) is required.
- **RBAC/permission bootstrap** (roles, permissions, default role-permission mappings) may reuse existing targeted seeders (e.g. `RoleSeeder`, `PermissionSeeder`) **only after each specific seeder has been explicitly reviewed and proven production-safe** (no hardcoded credentials, no demo data, idempotent) — until that review happens for a given seeder, a dedicated production-bootstrap command/path is used instead rather than assuming an existing seeder is safe by association.
- **Idempotency / fail-closed:** the bootstrap procedure must either be safely re-runnable without duplicating or corrupting state (idempotent), or must fail closed with a clear error if production has already been initialized — it must never silently re-create or overwrite an existing real tenant/admin.
- **If the production database already contains data** (the "existing-data" case from §5's external-input table): bootstrap does **not** run at all — instead, the deployment/smoke sequence verifies the real tenant/admin/RBAC state already present, rather than attempting to seed anything.
- **The first-controlled-deployment smoke sequence (§6) must authenticate as a real, non-demo operator identity produced or resolved through this contract** — never a `DatabaseSeeder`-created demo account, regardless of which candidate or which host is eventually used.

## 4. YAGNI discipline applied

Explicitly excluded from the recommended first-deployment scope, despite existing as dormant code in the repo:
- Blue-green deployment (traffic-splitting infrastructure this repo does not have; unimplemented cutover logic).
- Canary deployment (same reasons, plus staged-percentage traffic shifting has no real mechanism behind it today).
- The full `docker-compose.prod.yml` observability stack (`prometheus`, `grafana`, `elasticsearch`, `kibana`) — deferred regardless of which candidate is chosen, since neither is needed to safely complete a *first* controlled deployment, only to operate at greater scale/maturity later.
- MySQL primary/replica topology (`docker/mysql/{master,slave}.cnf`) — a single MySQL instance is sufficient for a first controlled deployment; replication is a later scaling concern.
- Multi-host/HA — a first controlled deployment targets one host, one instance of the application, consistent with "smallest practical architecture."

## 5. External Owner inputs still required (specified as contracts, not invented values)

Gate 2 specifies the architecture independently of these values — implementation can proceed once they are supplied, without redesigning the architecture itself:

| Input | Contract placeholder used in this design |
|---|---|
| Target host/provider | `PRODUCTION_HOST` (existing secret name) — architecture is host-agnostic (any Linux VPS/VM meeting the provisioning checklist in A-7). |
| Domain name / DNS ownership | `PRODUCTION_URL` (existing secret name) — architecture does not hardcode any domain; the readiness endpoint (A-4) and any user-facing URL must read from this contract, not a literal string (correcting the Gate-1 finding about `automated-deployment.yml`'s hardcoded `zenamanage.com` references). |
| Budget/cost tolerance | Not required to start Gate 3 — Candidate A's cost floor is a single VPS sized to run PHP-FPM/nginx/MySQL/Redis; exact sizing (CPU/RAM/disk) is an Owner/host decision the design does not need to fix in advance. |
| Production credential authority | Who holds `PRODUCTION_SSH_KEY` and `shared/.env` (A-3/A-8) — an access-control decision, not an architectural one; the design only requires that *someone* holds them and the workflow can reach them via the existing 4-secret GitHub Secrets contract, plus the authorized-manual-dispatch set defined in A-1. |
| GitHub Environment deployment approvers | Whether the `production` GitHub Environment requires manual reviewer approval before the deploy job runs (A-1) — a governance/process decision layered on top of this architecture, not blocking its design; A-1 specifies the fallback if this isn't enforceable on the current GitHub plan. |
| Empty vs. existing-data first production DB | Governed by the production-safe bootstrap contract (§3a): an empty DB gets the canonical minimum bootstrap (real tenant + real admin, no seeders with demo data); an existing DB skips bootstrap and instead has its real tenant/admin/RBAC state verified. The migration step (A-5) works identically either way — this only affects the one-time Gate-3 runbook decision of which bootstrap path applies on first deploy. |

None of these block finishing Gate 2's architecture comparison and recommendation; they gate Gate 3/implementation readiness, not this design.

## 6. First controlled-deployment acceptance path (corrected: real bootstrap identity + queue canary)

Beyond the minimal production readiness endpoint (A-4), the smoke sequence remains the design target for Gate 3 to implement as an evidence-producing step, not merely describe:
1. Login/auth succeeds for the real, non-demo operator account produced or resolved through the production-safe bootstrap contract (§3a) — never a `DatabaseSeeder`-created demo account.
2. Tenant isolation — see the corrected, split evidence model immediately below (Gate-2 Round 2 clarification); production smoke does **not** attempt to re-prove cross-tenant isolation from scratch using manufactured data.
3. RBAC enforces exactly the permitted actions for a given role.
4. A DB write persists across a request cycle.
5. The A-4 queue canary completes within its bounded wait — a unique probe job is enqueued, the real worker processes it, and completion is observed before the timeout; this is the evidence that `QUEUE_CONNECTION` and the worker process are real, not the `sync` default, replacing any claim the readiness HTTP endpoint itself could make about queue liveness.
6. A file upload round-trips through `shared/storage` (A-3).
7. A small set of critical pages/APIs return 200 under authentication.

**Binding Owner clarification (Gate-2 Round 2): tenant-isolation evidence is split, not claimed from production smoke alone.** The production-safe bootstrap contract (§3a) intentionally creates only real production data and must **not** create a fake/demo second tenant merely to give a smoke test something to isolate against — doing so would violate §3a's own "no demo data in production" rule. Evidence is therefore split into two distinct, non-substitutable parts:
- **(a) Pre-release security evidence (required before this architecture is considered acceptance-ready, executed in a disposable/non-production environment):** cross-tenant *negative* isolation is proven in a disposable test environment provisioned with at least two controlled test tenants, exercising the real, live authorization/tenant-boundary code paths (not a synthetic unit-test double), demonstrating concretely that Tenant A cannot read or write Tenant B's data. This is the evidence that actually proves the isolation *mechanism* works.
- **(b) Production smoke (item 2 above, using only real data):** verifies the authenticated real operator is correctly scoped to their own real tenant, and that no unexpected data belonging to any other tenant appears in their session — this proves *configuration/wiring* in the real production environment, not the isolation mechanism itself (that was already proven in (a)). If production legitimately already contains two or more real tenants (e.g. a later, later-arriving second real customer), an additional non-destructive cross-tenant verification may be performed between those real tenants where safe and explicitly authorized — but demo/synthetic production tenants must never be manufactured merely to enable this check.
- **This clarification is evidence-methodology only — it does not authorize any CRM/project/business-semantics change.** Both (a) and (b) exercise existing tenant-isolation behavior; neither modifies it.

As before, the principal business flow (Lead → Opportunity → Service Line → Quote → Contract → Project) remains named only as a description of eventual real-user success, not a spec to implement here — any future need to touch that product code goes through a separate Work ID's Design Dependency Preflight.

## 7. Explicit scope boundary (unchanged from Gate 1, restated for Gate 2)

**In scope for Gate 2 (this document):** comparing Candidate A vs. Candidate B, recommending one, specifying the target deployment lifecycle/contracts, and disposing of `deploy.yml`.

**Out of scope for Gate 2:** writing the implementation plan (reserved for the next phase after Owner Gate-2 approval), configuring any actual secret, provisioning any actual host, making any actual DNS/domain/TLS change, mutating any production database, and any CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product code.
