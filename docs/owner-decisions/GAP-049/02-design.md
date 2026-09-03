---
work_id: GAP-049
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md
  plan: null
  branch: docs/GAP-049-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/301"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-03T13:00:00Z"
  owner_response_reference: "GAP-049 Gate 2 Round 1 (relayed via coordinator session, reviewed exact PR head d65aee3a791b05603ba578c70877ce7a759bea2a of PR #301, canonical main at review time 8efe40d6cfac4e5fb3b7bed9910af1db353731cf): 'Owner Gate-2 Round 1 decision: CHANGES REQUESTED on PR #301. The architectural direction is ACCEPTED and must NOT be reopened...' Owner directed 6 corrections plus a deployment-state-machine refinement, addressed in Round 2. | GAP-049 Gate 2 Round 2 (relayed via coordinator session, reviewed exact PR head b7ee73fd7fd1cd14febd7accda18cbfd44a56a3c of PR #301, canonical main at review time 8efe40d6cfac4e5fb3b7bed9910af1db353731cf): 'OWNER GATE 2 ROUND 2 DECISION: APPROVED on PR #301. Approved architecture: Candidate A (hardened release-based SSH deployment evolved from production.yml) with all the design contracts from Round 1 (deploy!=merge separation, exact-SHA binding, environment:production gate, immutable releases, atomic symlink switch, shared .env/storage, deployment serialization, expand-vs-breaking migration classification, migrate --isolated for command-mutex only, no automatic migrate:rollback, migration-aware rollback/recovery, minimal readiness endpoint, separate queue-canary, least-privilege app-DB+storage backup with off-host durability and mandatory restore drill, production-safe bootstrap with DatabaseSeeder forbidden and no demo tenants/fixed passwords, six-state deployment lifecycle, YAGNI exclusions). deploy.yml stays a legacy/deprecation path — implementation (in a FUTURE session) should retire/remove its production entry point and the repo-root legacy deploy.sh as an alternative production path. Candidate B stays a documented future upgrade path only. Blue-green/canary/multi-host HA/MySQL replication/full Prometheus-Grafana-Elastic-Kibana stack remain NOT authorized under GAP-049. This is a narrow admin-only round.' Owner directed: (STEP 1) record this approval with gate_status: approved, owner_decision.value: approved, decision_requested: null, preserving Round 1 (changes requested) and Round 2 (approved) history permanently, and encode two binding clarifications into the design docs narrowly: CLARIFICATION 1 (trusted exact-SHA source delivery) — the approved architecture must not reproduce a Gate-1-style implicit-host-git-credential problem; implementation planning must choose ONE of (a) preferred: CI checks out/verifies the exact requested SHA, prepares the release, and transfers that exact release to the host over the approved deployment channel, or (b) acceptable alternative: the host fetches the exact requested SHA using a dedicated read-only repository/deploy credential; in either case no git pull origin main, no deploying whatever main currently points to, no implicit mutable-branch dependency, no broad write-capable GitHub credential on the production host merely to deploy, exact SHA verified before release activation; the implementation plan must state which mechanism was chosen. CLARIFICATION 2 (tenant-isolation evidence split) — do not claim cross-tenant isolation is proven using only one production tenant, since the production bootstrap contract must not create a fake/demo second tenant for smoke testing; split evidence into (a) pre-release security evidence: cross-tenant negative isolation proven in a disposable/non-production environment with at least two controlled tenants exercising the real live authorization/tenant boundaries, and (b) production smoke: uses the real non-demo operator and real production tenant, verifies correct tenant-scoping and no unexpected cross-tenant data, with an additional non-destructive cross-tenant check permitted only if production legitimately already has two or more real tenants — demo production tenants must never be manufactured for a smoke test; this clarification does not authorize any CRM/project/business-semantics change. Also fix the harmless §4a to §3a cross-reference typo if still present. (STEP 2) verify only the Gate-2 docs changed, push to PR #301, block on exact-head CI green. (STEP 3) merge PR #301 via this repo's normal squash convention once CI is green and main has not materially drifted. (STEP 4) MANDATORY SESSION BOUNDARY: after the clean Gate-2 merge, STOP completely — no implementation plan, no implementation, no app/workflow/infra code, no host provisioning, no secrets, no deployment; implementation planning is explicitly a NEW GAP-049 session starting from the post-Gate-2 canonical main, not to be attempted here even partially.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T10:06:57Z"
  updated_at: "2026-09-03T13:00:00Z"
generated_by: agent
---

## Owner Decision History — Round 2 — APPROVED (permanent record, never erased)

**Owner Gate 2 Round 2 decision: APPROVED** (reviewed exact PR head `b7ee73fd7fd1cd14febd7accda18cbfd44a56a3c`, canonical main `8efe40d6cfac4e5fb3b7bed9910af1db353731cf`). Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. **Approved architecture:** Candidate A (hardened release-based SSH deployment evolved from `production.yml`) with every design contract from Round 1 (A-1 through A-12, §3a) intact: deploy≠merge separation, exact-SHA binding, `environment: production` human-approval gate, immutable releases with atomic symlink switch, shared `.env`/storage, deployment serialization, expand-vs-breaking migration classification, `migrate --isolated` scoped to command-mutex only, no automatic `migrate:rollback`, migration-aware rollback/recovery, minimal readiness endpoint, a separate queue canary, least-privilege application-database+storage backup with off-host durability and a mandatory restore drill, a production-safe bootstrap contract (no `DatabaseSeeder`, no demo tenants, no fixed passwords), and the six-state deployment lifecycle. **`deploy.yml` stays a legacy/deprecation path** — a future implementation session should retire/remove its production entry point and the repo-root legacy `deploy.sh` as an alternative production path. **Candidate B stays a documented future upgrade path only** — not authorized as first-deployment architecture. **Blue-green/canary/multi-host HA/MySQL replication/the full Prometheus-Grafana-Elastic-Kibana stack remain NOT authorized under GAP-049.**

**Two binding clarifications directed and encoded into the design spec in this round (not new design changes, narrow strengthening only):**
1. **Trusted exact-SHA source delivery (design spec A-1):** implementation must choose either CI-delivered exact release (preferred) or host-side fetch via a dedicated read-only deploy credential (acceptable alternative) — never `git pull origin main`, never an implicit mutable-branch dependency, never a broad write-capable GitHub credential on the production host.
2. **Tenant-isolation evidence split (design spec §6):** cross-tenant negative isolation is proven pre-release in a disposable environment with controlled test tenants; production smoke verifies only correct tenant-scoping of the real operator against the real tenant, using no manufactured demo tenants. This clarification is evidence-methodology only and authorizes no CRM/business-semantics change.

**Mandatory session boundary directed by the Owner:** after this Gate-2 record is merged, the session stops completely. No implementation plan, no implementation, no app/workflow/infra code, no host provisioning, no secrets, no deployment. Implementation planning is a **new**, future GAP-049 session starting from the post-Gate-2 canonical main. This Round 2 record is preserved permanently and must not be removed by any future revision.

---

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate 2 Round 1 decision: CHANGES REQUESTED** (not a rejection — reviewed exact PR head `d65aee3a791b05603ba578c70877ce7a759bea2a`, canonical main `8efe40d6cfac4e5fb3b7bed9910af1db353731cf`). **The architectural direction was explicitly ACCEPTED and is not reopened:** Candidate A remains the recommended architecture; `deploy.yml` remains a retirement candidate, not a design option; Candidate B remains a documented future upgrade path; blue-green/canary/multi-host/HA/the full observability stack remain deferred under YAGNI; a single-host first controlled deployment remains the target. Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. Six corrections plus a deployment-state-machine refinement were directed and are addressed in this re-presentation (see the design spec's now-corrected A-1 through A-12 and new §3a): (1) merge/release separated from production deployment, with an explicit human-approval gate and immutable-SHA binding; (2) an explicit release/shared-filesystem contract; (3) migration expand-vs-breaking classification with corrected rollback semantics; (4) a minimal production readiness endpoint replacing the Gate-1 `/api/health/detailed` assumption, plus a separate queue canary; (5) a least-privilege, evidence-based backup/restore contract; (6) a production-safe first-database bootstrap contract (no `DatabaseSeeder`, no demo data, no fixed passwords, real tenant/admin only); plus a refined 6-state deployment state machine. This Round 1 record is preserved permanently and must not be removed by any future revision.

---

## Owner Summary

Gate 2 compares two ways to harden ZENA's deployment into one truthful, recoverable first-controlled-deployment path — evolving the existing SSH-based `production.yml`, versus evolving the existing Docker/GHCR-based `automated-deployment.yml` — and recommends evolving `production.yml`, because it gets ZENA into controlled real use fastest with the least new operational complexity, while still closing every Gate-1 truthfulness/recoverability gap. `deploy.yml` is recommended for retirement, not hardening — its underlying script cannot currently even execute against `main`.

## Trước / Sau

**Trước (current state, per Gate-1 evidence):**
1. Three executable deployment workflows exist, contradicting each other on host path, secret names, and health-check contract.
2. No real deploy step has ever executed with a non-skipped conclusion across 669 reviewed Actions runs.
3. `production.yml`'s post-deploy health check is a hardcoded JSON literal, and `production.yml` deploys automatically on every push to `main` with no human approval gate.
4. No authoritative, proven production-safe rollback path exists for any workflow.
5. `deploy.yml`'s underlying `deploy.sh` cannot currently complete against `main` (missing npm script), and unconditionally seeds a hardcoded-password demo admin account via `DatabaseSeeder` if it ever did.
6. `automated-deployment.yml` has real backup/rollback code, but both are unproven end-to-end, and its rollback is schema-unsafe by default.

**Sau (proposed target state, Gate-2 design, corrected Round 1):**
1. ONE deployment mechanism (hardened `production.yml`) is authoritative; `deploy.yml` is retired.
2. Production deployment is a distinct, explicitly-invoked, immutable-SHA-bound event, separate from merging to `main`, gated by a human production-approval step (A-1).
3. Deploy status is truthfully reported across six states — `not_configured` / `attempted` / `failed` / `deployed_unverified` / `health_verified` / `rolled_back` — so a green workflow can never be confused with "production is healthy" (A-2).
4. Deploys use a versioned-release + shared-filesystem model (A-3) with atomic `current`-symlink switching, deployment serialization, and migrations classified as expand-safe (may run pre-switch) or breaking (require a maintenance window and a written recovery runbook) before any rollback decision is made (A-5).
5. A minimal, genuine-dependency-probing readiness endpoint (no diagnostic internals, no hardcoded status) gates cutover completion, with a separate queue canary proving worker liveness (A-4).
6. A least-privilege, evidence-based backup/restore contract (A-6) — scoped to the ZENA database and shared storage only, with a mandatory restore drill in a disposable non-production environment — replaces the earlier `mysqldump --all-databases`-pattern assumption.
7. A production-safe first-database bootstrap contract (§3a) guarantees `DatabaseSeeder` never runs in production, no demo data or fixed passwords are ever created, and only a real tenant/admin is bootstrapped.
8. Host provisioning (PHP 8.2-fpm matching `composer.json`, queue-worker systemd unit, websocket systemd unit, scoped sudo, `shared/.env`+`shared/storage` setup) is documented as an explicit one-time checklist (A-7), closing several "assumed but never provisioned" Gate-1 findings.

## Vai trò bị ảnh hưởng

- **Whoever holds production SSH/secrets access:** gains a documented, one-time host-provisioning checklist and a truthful `shared/.env` contract instead of an undocumented implicit assumption.
- **Whoever is authorized to trigger a production deploy:** gains an explicit, approval-gated, immutable-SHA-bound trigger instead of an automatic push-to-`main` deploy with no human checkpoint.
- **Future on-call/operator responding to a bad deploy:** gains an actual rollback procedure that correctly distinguishes expand migrations (safe symlink-only rollback) from breaking migrations (maintenance + a written recovery runbook), instead of no defined procedure and no such distinction at all.
- **The first real production administrator:** is created through an explicit, non-demo bootstrap contract with a securely handled credential, instead of risking a hardcoded-password demo account.
- **Anyone reading CI status:** the deploy workflow will report which of six truthful states occurred, instead of a green checkmark that could mean "skipped entirely" or "code cutover happened but nobody checked it actually works."

## Được phép / Không được phép

- Implementation (Gate 3) is **not** authorized by this Gate-2 design alone — a separate Owner approval of this design is required first, and implementation itself follows only after that.
- This design does **not** authorize configuring any real secret, provisioning any real host, or making any DNS/TLS change — those remain external Owner-supplied inputs and/or later execution steps.
- This design does **not** authorize touching any CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line code.
- This design **does** authorize (once separately approved and implemented) `deploy.yml` being disabled/removed as a live entry point, since it is currently a hazard (can seed a demo admin with a hardcoded password) rather than a functioning deployment path.

## Trạng thái và bước tiếp theo

1. **Gate 2 (this document): awaiting Owner review.** Owner approves/requests changes/declines the architecture recommendation (Candidate A) and the `deploy.yml` retirement decision.
2. **If approved:** proceeds to an implementation plan (not written here) covering the exact workflow YAML changes, the release-directory scripting, the migration/rollback contract, and the host-provisioning runbook.
3. **Gate 3:** technical evidence gate — the implementation must be proven (including an actual restore drill and the acceptance smoke sequence from §6 of the design spec) before any Owner release decision.
4. **First controlled deployment:** only after Gate 3 evidence is accepted, and only once the external Owner inputs in §5 of the design spec (host, domain, credential authority) are actually supplied.

## Ngoại lệ

- If the Owner's actual host/domain choice turns out to require a fundamentally different topology (e.g. a managed PaaS with no direct SSH access), this design's Candidate A assumptions (SSH-based release deploy) would need re-evaluation — flagged here as a known dependency on the external host decision, not resolved by this Gate 2.
- If Gate 3 implementation finds `migrate --isolated` insufficient for a specific migration's real concurrency needs, a stronger lock (e.g. an explicit advisory lock) may be substituted — the design commits to the isolation *goal*, not necessarily the exact Laravel flag, if evidence shows it's insufficient.

## Hành vi người dùng nhìn thấy

None directly — this is an infrastructure/deployment design. The first-order visible effect, once implemented and a first deployment occurs, is that ZENA becomes reachable by designated operators at a real URL for the first time; no in-app behavior changes as a result of this design itself.

## Kịch bản chấp nhận

- **Given** a human explicitly triggers a production deploy bound to an exact commit SHA and the GitHub Environment approval (or its authorized-manual-dispatch fallback) is satisfied, **when** the workflow runs and passes build/migration/cutover, **then** it progresses `attempted → deployed_unverified → health_verified`, and only `health_verified` is ever described as a successful, usable deployment — never a bare "success" at an earlier state.
- **Given** a deploy's build or migration step fails, **when** the workflow detects the failure, **then** the `current` symlink is never repointed, the previous release keeps serving traffic, and the workflow reports `failed` truthfully (not a bare skip or a misleading green).
- **Given** a deploy included only expand/backward-compatible migrations and a subsequent rollback is triggered, **when** the rollback runs, **then** it repoints `current` back to the prior release and the expanded schema is correctly left in place (the prior code already tolerates it) — no schema rollback is attempted.
- **Given** a deploy included a breaking/contract migration, **when** something goes wrong, **then** the system enters/remains in maintenance (visible to the currently-serving release, not a new unreleased directory) and the migration-specific written recovery runbook is followed — an automatic `migrate:rollback` or automatic schema change is never invented.
- **Given** the backup step runs before a deploy (scoped to the ZENA database and shared storage, least-privilege credential), **when** a restore drill is later executed against that backup in a disposable non-production environment, **then** it is proven to produce a working restored database and usable representative uploaded file(s) — this proof is required before the architecture is accepted as complete, and production data is never destroyed to prove it.
- **Given** a first production deployment against an empty database, **when** bootstrap runs, **then** it creates only the real initial tenant and one real initial administrator with a securely generated or Owner-supplied credential — `DatabaseSeeder` is never invoked, no demo tenant/user is created, and no fixed/default password is used.
- **Given** the production readiness endpoint is queried, **when** it responds, **then** a 200 means genuine DB/cache/(shared-storage, if applicable) probes all passed, a 503 means at least one failed, and the response body never contains PHP/Laravel version, `APP_ENV`, memory/load metrics, or other diagnostic internals.
- **Given** `deploy.yml` is retired, **when** anyone attempts to invoke it, **then** it either does not exist or hard-fails immediately rather than partially executing and seeding a hardcoded-password admin account.

## Loại trừ phạm vi

Carried forward from Gate 1, unchanged: no CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product code; no reopening of GAP-042 RBAC scope; no implementation plan authored in this document; no actual secret configuration, host provisioning, DNS/TLS change, or production database mutation. Additionally, per this Gate 2's own YAGNI discipline: blue-green deployment, canary deployment, and the full observability stack (`prometheus`/`grafana`/`elasticsearch`/`kibana`) are explicitly deferred past the first controlled deployment, not designed here.

## Decision Needed

Owner chọn một: Approve kiến trúc đề xuất (Candidate A — hardened `production.yml`) và quyết định loại bỏ `deploy.yml`, để tiến sang giai đoạn implementation plan / Yêu cầu thay đổi thiết kế / Từ chối.

## What the owner is NOT being asked to decide

The Owner is not being asked to approve exact GitHub Actions YAML, exact shell script contents, exact host sizing/provider, exact domain, or exact credential-holder identities — only whether the recommended architecture (Candidate A), its corrected target lifecycle/contracts (explicit human-approval-gated deployment separate from merge, the six-state truthful status model, the release/shared-filesystem contract, expand-vs-breaking migration classification, the minimal readiness endpoint + queue canary, least-privilege evidence-based backup/restore, and the production-safe first-database bootstrap contract), and the `deploy.yml` retirement decision are the right direction before an implementation plan is written.
