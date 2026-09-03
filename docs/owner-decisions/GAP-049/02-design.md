---
work_id: GAP-049
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md
  plan: null
  branch: docs/GAP-049-gate2-design
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-03T12:00:00Z"
  owner_response_reference: "GAP-049 Gate 2 Round 1 (relayed via coordinator session, reviewed exact PR head d65aee3a791b05603ba578c70877ce7a759bea2a of PR #301, canonical main at review time 8efe40d6cfac4e5fb3b7bed9910af1db353731cf): 'Owner Gate-2 Round 1 decision: CHANGES REQUESTED on PR #301. The architectural direction is ACCEPTED and must NOT be reopened: Candidate A (hardened production.yml release-based SSH) stays the recommended architecture; deploy.yml stays a retirement candidate, not a design option; Candidate B (Docker/GHCR) stays a documented future upgrade path, not first-deployment architecture; blue-green/canary/multi-host/HA/full observability stack stay deferred under YAGNI; single-host first controlled deployment stays the target. No implementation, no implementation plan, no workflow/app code changes, no secrets, no provisioning, no deployment — this round only strengthens six load-bearing contracts in the design docs.' Owner directed 6 corrections plus a deployment-state-machine refinement, all addressed in this re-presentation: (1) merge/release separated from production deployment — production deploy is now an explicit workflow_dispatch/release event bound to an exact immutable commit SHA, gated by GitHub Environment required reviewers with an explicit authorized-manual-dispatch fallback, never automatic on push-to-main during the pilot phase (design spec A-1). (2) the release/shared-filesystem contract is now explicit (design spec A-3): releases/<sha>/ immutable, shared/.env and shared/storage outside any release, storage links established before any Artisan command depending on them runs, maintenance-mode state visible to whichever release is current. (3) migration/cutover/rollback semantics distinguish expand/backward-compatible migrations (may run pre-switch) from breaking/contract migrations (require classification, fresh backup, real maintenance window, a written forward/rollback/data-fix runbook, default policy prefers expand) — migrate --isolated is now correctly scoped to migration-command mutual exclusion only, distinct from the workflow's own concurrency: deployment-level serialization; migrate:rollback is never automatic (design spec A-5). (4) the health-check assumption was replaced with a minimal production readiness endpoint contract (genuine DB/cache/shared-storage probes, HTTP 200/503, no diagnostic internals, no hardcoded dependency status) plus a separate queue canary for worker-liveness evidence, since HTTP alone cannot prove a worker is alive (design spec A-4). (5) backup/restore is now least-privilege and evidence-based: scoped to the ZENA application database and shared storage only, not docker-manage.sh's mysqldump --all-databases verbatim, with a restore drill required in a disposable non-production environment before this architecture is considered complete (design spec A-6). (6) a production-safe first-database bootstrap contract was added: DatabaseSeeder is never run in production, no demo/sample tenants or users, no fixed/default passwords, only the canonical minimum real tenant + real admin is bootstrapped, RBAC seeders are usable only after individual production-safety review, the bootstrap is idempotent or fails closed, and an already-populated production DB is verified rather than re-seeded (design spec Section 3a). Additionally, the deployment-state model was refined from 5 to 6 states (not_configured / attempted / failed / deployed_unverified / health_verified / rolled_back) so workflow success can never be confused with production health, and automatic schema rollback is never invented (design spec A-2). Correction scope strictly bounded to docs/superpowers/specs/2026-09-03-gap-049-production-deployment-gate2-design.md and this file; no app/**, src/**, routes/**, .github/workflows/**, database/**, config/** change authorized or made; no implementation plan authored. PR #301 remains Draft throughout; this packet remains gate_status: awaiting_owner / owner_decision.value: none pending a fresh Owner Gate-2 decision on this corrected re-presentation.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T10:06:57Z"
  updated_at: "2026-09-03T12:00:00Z"
generated_by: agent
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
