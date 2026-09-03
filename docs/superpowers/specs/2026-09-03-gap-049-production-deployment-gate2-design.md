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

**What changes from today:**
1. **Truthful status reporting.** Replace the binary "job succeeded/skipped" signal with an explicit, machine-readable deploy-status artifact (e.g. a workflow summary line and/or a status file written to the host) that distinguishes: `not_configured` (secrets absent — current state), `attempted`, `succeeded`, `failed`, `health_verified`. A skip due to missing secrets must never render as a plain green checkmark without this qualifier visible in the job summary.
2. **Versioned releases + atomic switch.** Move from in-place `git pull` at `/var/www/zena` to a releases directory model: each deploy checks out into `/var/www/zena/releases/<timestamp-or-sha>/`, builds there, and only on full success atomically repoints a `current` symlink (`ln -sfn`) that nginx/PHP-FPM actually serve from. A failed build never touches `current`; there is never a window where partially-built code is live.
3. **Deployment serialization.** Add a `concurrency: { group: production-deploy, cancel-in-progress: false }` block to the workflow so two deploy runs can never interleave on the same host.
4. **Migration strategy.** Run `php artisan migrate --force --isolated` (Laravel's built-in cache-lock isolation, available in the pinned `^12.0` framework but never currently used) before the symlink switch, against the *new* release directory's code but the *shared* database — i.e. migrations run once, ahead of traffic cutover, so the window between "schema migrated" and "code live" is controlled and short, not an in-place race.
5. **Schema-compatible rollback.** Rollback = re-point `current` back to the previous release directory (fast, code-only, safe on its own) **plus** an explicit written contract: a rollback is only declared "complete" after confirming whether the failed deploy's migrations were additive/backward-compatible (safe to leave applied) or breaking (requires a corresponding down-migration or a data-fix runbook) — this decision is deferred to implementation-time migration review per release, not automated blindly. This directly closes the Gate-1 finding that a code-only rollback against forward migrations is unsafe by default.
6. **Maintenance window where required.** `php artisan down --secret=<token>` before migrations that are flagged non-backward-compatible; `up` after the symlink switch and a passing health check. Purely additive migrations may skip the window (a Gate-3 implementation decision, not fixed here).
7. **Host provisioning contract.** A documented (not automated in this Gate) one-time host setup checklist: PHP 8.2-fpm (matching `composer.json`, correcting the stale 8.1 assumption found in Gate 1), Composer, Node matching `Dockerfile.prod`'s pinned version, nginx, a systemd unit for the queue worker (currently absent — Gate 1 finding), a systemd unit for the websocket service (currently assumed but never provisioned — Gate 1 finding), sudoers scoped to exactly the commands the deploy user needs (`systemctl reload nginx`, `systemctl restart <queue-worker-unit>`, `systemctl restart websocket` — nothing broader).
8. **Production `.env`/secret contract.** Formalize that the 4 existing GitHub Secrets (`PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL`) are necessary but not sufficient — a host-side `.env` must be provisioned once, out-of-band, by whoever holds production credentials, and the workflow should assert its presence (fail loudly, not silently proceed) rather than assume it.
9. **Backup + proven restore.** Adopt (or closely mirror) `docker-manage.sh`'s `mysqldump --all-databases` + `storage`/`public` tarball pattern as a pre-migration step in this candidate too, writing to a durable, off-host-replicated location (not the same disk as the app, per the Gate-1 finding about `deploy.sh`'s self-defeating in-checkout "backup"). **A restore drill must be executed and evidenced before this architecture is considered acceptance-ready** — this is a Gate-3/implementation deliverable, explicitly called out here so it is not silently dropped.
10. **Real health checks.** Point the post-deploy check at a dependency-probing endpoint (the existing `SystemHealthController::detailed()`/`/api/health/detailed` logic, or an equivalent purpose-built check), not the current hardcoded `/api/health` literal.
11. **Queue/cache/storage/websocket.** Explicitly set `QUEUE_CONNECTION` to a real backend (`redis` or `database`) in the production `.env` contract (item 8) and require a running, systemd-supervised worker (item 7) — closing the Gate-1 finding that the default `sync` driver silently runs jobs inline. `storage:link` added as an idempotent step in every deploy (currently missing).
12. **Logs/observability.** Minimum viable: confirm `storage/logs/laravel.log` (or equivalent) is captured/rotated on the host and that the release-directory model doesn't orphan logs from old releases; defer full APM/Sentry adoption as a later enhancement, not a Gate-2 blocker.
13. **SSH least privilege / host-key verification.** Pin `appleboy/ssh-action`'s host-key fingerprint explicitly rather than relying on default behavior; scope the deploy SSH user's sudo rights to the exact commands in item 7, nothing broader.
14. **Domain/TLS.** Out of this design's control — depends on the external host/domain decision (see §5). The design assumes nginx terminates TLS via a certificate provisioning mechanism (e.g. Let's Encrypt/ACME) set up once during host provisioning (item 7), not per-deploy.
15. **First controlled-deployment acceptance evidence.** See §6.

**Effort/complexity:** Low-to-moderate — evolves an existing, simpler mechanism. Does not require the team to adopt Docker/container operations if they haven't already. **Cost:** no new infrastructure spend beyond the already-assumed single host. **Maintainability:** straightforward for a team already comfortable with bare-metal Linux/SSH operations.

### Candidate B — Hardened Docker/GHCR/Compose (evolves `automated-deployment.yml`)

**What changes from today:**
1. **CI-built immutable image advantage.** Already present — `docker/build-push-action` builds once in CI and pushes to GHCR; every environment (staging/production) pulls the same tested artifact, avoiding Candidate A's host-side build variability (Node/Composer version drift). This is a genuine structural advantage over Candidate A.
2. **GHCR permissions.** Uses `secrets.GITHUB_TOKEN` (scoped to the repo, expires with the workflow run) rather than a long-lived registry credential — already reasonably safe, but Gate 3 must confirm the package visibility (public vs. private) and who/what can pull it.
3. **Docker host prerequisites.** Docker Engine + Docker Compose v2 installed on the host; this is a real, non-trivial prerequisite the SSH bare-metal candidate does not have — must be part of the host-provisioning contract if this candidate is chosen.
4. **`docker-compose.prod.yml` production fitness.** The file defines 12 services including `elasticsearch`/`kibana`/`prometheus`/`grafana` — genuinely more than a *first* controlled deployment needs. Per YAGNI (see §3), a hardened Candidate B for GAP-049's purpose should stand up only the services actually required for correctness (`app`, `nginx`, `mysql`, `redis`, `queue`, `scheduler`, `websocket`, `backup`) and explicitly defer the observability stack (`prometheus`/`grafana`/`elasticsearch`/`kibana`) to a later phase — running them prematurely adds host resource requirements and operational surface with no first-deployment benefit.
5. **DB/storage volume persistence.** `docker-compose.prod.yml`'s named volumes (`mysql_data`, `redis_data`, etc.) must be confirmed to survive `docker-compose down`/`up` cycles (they should, by Compose's volume semantics, but this must be verified in Gate 3, not assumed).
6. **Backup/restore.** Already has a real implementation (`docker-manage.sh backup`/`restore`) — ahead of Candidate A on this axis today, though equally unproven end-to-end (no restore drill evidenced for either candidate).
7. **Rollback after migrations.** The existing `rollback` job's `git reset --hard HEAD~1` is schema-unsafe by default (Gate-1 finding) — this must be redesigned in either candidate, not inherited as-is. A hardened Candidate B should tag/pin the *previous* GHCR image digest and redeploy that exact image, plus the same schema-compatibility decision framework as Candidate A item 5 (was the migration additive or breaking).
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
3. **Recoverability** — roughly comparable once hardened: Candidate A's releases-directory rollback and Candidate B's previous-image-tag rollback are structurally similar in safety once the schema-compatibility decision framework (§2, Candidate A item 5 / Candidate B item 7) is applied to both. Candidate B has a head start on backup/restore code existing already, but neither has a proven restore drill — this is a wash until Gate 3 evidence exists for either.
4. **Security** — comparable; Candidate A's attack surface is SSH + sudo-scoped systemctl; Candidate B's is SSH + Docker socket/Compose access (arguably a *larger* privileged surface, since Docker access is commonly root-equivalent) — a mild edge to Candidate A here, not decisive on its own.
5. **Operational simplicity** — decisive edge to Candidate A: no new toolchain (Docker/Compose/registry) for the team to operate day-to-day.
6. **Maintainability** — mild edge to Candidate B long-term (immutable CI-built images avoid host-side build drift), but this matters more at scale/multi-host than for a first controlled deployment.
7. **Cost** — mild edge to Candidate A (lower host resource floor without a container runtime and even a trimmed service set).

**Why Candidate A wins overall:** criteria 1 and 5 (time-to-first-use and operational simplicity) are weighted highest by the Owner's own priority order, and Candidate A wins both clearly, while the criteria favoring Candidate B (4 is a wash-to-mild-edge-A, 6 is a real but lower-priority long-term advantage) do not outweigh that. **Candidate B's work is not wasted** — `docker-manage.sh`'s backup/restore pattern (§2, Candidate A item 9) should be borrowed into Candidate A's implementation rather than re-invented, and Candidate B remains the well-positioned upgrade path if/when the team's operational needs justify the container step-change later (e.g. multi-host scaling, need for the observability stack already modeled in `docker-compose.prod.yml`).

**This recommendation is a Gate-2 design output for Owner review — it is not self-approved and does not authorize implementation.**

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
| Target host/provider | `PRODUCTION_HOST` (existing secret name) — architecture is host-agnostic (any Linux VPS/VM meeting the provisioning checklist in §2 Candidate A item 7). |
| Domain name / DNS ownership | `PRODUCTION_URL` (existing secret name) — architecture does not hardcode any domain; the health-check and any user-facing URL must read from this contract, not a literal string (correcting the Gate-1 finding about `automated-deployment.yml`'s hardcoded `zenamanage.com` references). |
| Budget/cost tolerance | Not required to start Gate 3 — Candidate A's cost floor is a single VPS sized to run PHP-FPM/nginx/MySQL/Redis; exact sizing (CPU/RAM/disk) is an Owner/host decision the design does not need to fix in advance. |
| Production credential authority | Who holds `PRODUCTION_SSH_KEY` and the host-side `.env` secrets — an access-control decision, not an architectural one; the design only requires that *someone* holds them and the workflow can reach them via the existing 4-secret GitHub Secrets contract. |
| GitHub Environment deployment approvers | Whether the `production` GitHub Environment requires manual reviewer approval before the deploy job runs — a governance/process decision layered on top of this architecture, not blocking its design. |
| Empty vs. existing-data first production DB | Affects only the first-run migration/seed sequence (an empty DB needs the RBAC/role bootstrap seeders; an existing DB does not) — the design's migration step (§2 Candidate A item 4) works identically either way; this only affects a one-time Gate-3 runbook decision about whether to run any seeder at all on first deploy, and if so which one (never `DatabaseSeeder`'s hardcoded-admin path as-is — see the Gate-1 finding on `deploy.sh`'s seeding hazard, which applies to any mechanism that might reuse that seeder unmodified). |

None of these block finishing Gate 2's architecture comparison and recommendation; they gate Gate 3/implementation readiness, not this design.

## 6. First controlled-deployment acceptance path (carried forward from Gate 1, unchanged in substance)

Beyond a real dependency-probing health check (§2, Candidate A item 10 / Candidate B item 9), the smoke sequence from Gate 1 remains the design target for Gate 3 to implement as an evidence-producing step, not merely describe:
1. Login/auth succeeds for a real (non-demo) operator account.
2. Tenant isolation holds.
3. RBAC enforces exactly the permitted actions for a given role.
4. A DB write persists across a request cycle.
5. A queued job actually executes in the background (proves `QUEUE_CONNECTION` and the worker process are real, not the `sync` default).
6. A file upload round-trips through storage.
7. A small set of critical pages/APIs return 200 under authentication.

As before, the principal business flow (Lead → Opportunity → Service Line → Quote → Contract → Project) remains named only as a description of eventual real-user success, not a spec to implement here — any future need to touch that product code goes through a separate Work ID's Design Dependency Preflight.

## 7. Explicit scope boundary (unchanged from Gate 1, restated for Gate 2)

**In scope for Gate 2 (this document):** comparing Candidate A vs. Candidate B, recommending one, specifying the target deployment lifecycle/contracts, and disposing of `deploy.yml`.

**Out of scope for Gate 2:** writing the implementation plan (reserved for the next phase after Owner Gate-2 approval), configuring any actual secret, provisioning any actual host, making any actual DNS/domain/TLS change, mutating any production database, and any CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product code.
