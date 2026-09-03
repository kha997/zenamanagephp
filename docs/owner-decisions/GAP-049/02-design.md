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
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T10:06:57Z"
  updated_at: "2026-09-03T10:06:57Z"
generated_by: agent
---

## Owner Summary

Gate 2 compares two ways to harden ZENA's deployment into one truthful, recoverable first-controlled-deployment path — evolving the existing SSH-based `production.yml`, versus evolving the existing Docker/GHCR-based `automated-deployment.yml` — and recommends evolving `production.yml`, because it gets ZENA into controlled real use fastest with the least new operational complexity, while still closing every Gate-1 truthfulness/recoverability gap. `deploy.yml` is recommended for retirement, not hardening — its underlying script cannot currently even execute against `main`.

## Trước / Sau

**Trước (current state, per Gate-1 evidence):**
1. Three executable deployment workflows exist, contradicting each other on host path, secret names, and health-check contract.
2. No real deploy step has ever executed with a non-skipped conclusion across 669 reviewed Actions runs.
3. `production.yml`'s post-deploy health check is a hardcoded JSON literal.
4. No authoritative, proven production-safe rollback path exists for any workflow.
5. `deploy.yml`'s underlying `deploy.sh` cannot currently complete against `main` (missing npm script), and unconditionally seeds a hardcoded-password demo admin account if it ever did.
6. `automated-deployment.yml` has real backup/rollback code, but both are unproven end-to-end, and its rollback is schema-unsafe by default.

**Sau (proposed target state, Gate-2 design):**
1. ONE deployment mechanism (hardened `production.yml`) is authoritative; `deploy.yml` is retired.
2. Deploy status is truthfully reported as `not_configured` / `attempted` / `succeeded` / `failed` / `health_verified` — never a bare green checkmark that could mean "never ran."
3. Deploys use a versioned-release + atomic-symlink-switch model with deployment serialization (no overlapping runs), migrations run with `--isolated` ahead of traffic cutover, and a schema-compatibility-aware rollback contract.
4. A real, dependency-probing health check gates success, not a hardcoded literal.
5. A durable, off-host-capable backup step (borrowing `docker-manage.sh`'s pattern) runs before every deploy, with a restore drill required as acceptance evidence before this architecture is considered production-ready.
6. Host provisioning (PHP 8.2-fpm matching `composer.json`, queue-worker systemd unit, websocket systemd unit, scoped sudo) is documented as an explicit one-time checklist, closing several "assumed but never provisioned" Gate-1 findings.

## Vai trò bị ảnh hưởng

- **Whoever holds production SSH/secrets access:** gains a documented, one-time host-provisioning checklist and a truthful `.env`/secret contract instead of an undocumented implicit assumption.
- **Future on-call/operator responding to a bad deploy:** gains an actual rollback procedure (release-directory switch-back) with an explicit migration-compatibility decision step, instead of no defined procedure at all.
- **Anyone reading CI status:** the deploy workflow will report which of five truthful states occurred, instead of a green checkmark that could mean "skipped entirely."

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

- **Given** a deploy is attempted with all required secrets/host state present, **when** the workflow runs, **then** it reports one of `attempted → succeeded → health_verified` and the new release is live via the atomic symlink switch, with the old release retained for rollback.
- **Given** a deploy's build or migration step fails, **when** the workflow detects the failure, **then** the `current` symlink is never repointed, the previous release keeps serving traffic, and the workflow reports `failed` truthfully (not a bare skip or a misleading green).
- **Given** a deploy has completed and a subsequent rollback is triggered, **when** the rollback runs, **then** it repoints `current` back to the prior release and the schema-compatibility decision (safe-to-leave-applied vs. needs-a-fix) has been explicitly recorded, not silently assumed.
- **Given** the backup step runs before a deploy, **when** a restore drill is later executed against that backup, **then** it is proven to produce a working restored database (this proof is required before the architecture is accepted as complete, not merely as a design intention).
- **Given** `deploy.yml` is retired, **when** anyone attempts to invoke it, **then** it either does not exist or hard-fails immediately rather than partially executing and seeding a hardcoded-password admin account.

## Loại trừ phạm vi

Carried forward from Gate 1, unchanged: no CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line product code; no reopening of GAP-042 RBAC scope; no implementation plan authored in this document; no actual secret configuration, host provisioning, DNS/TLS change, or production database mutation. Additionally, per this Gate 2's own YAGNI discipline: blue-green deployment, canary deployment, and the full observability stack (`prometheus`/`grafana`/`elasticsearch`/`kibana`) are explicitly deferred past the first controlled deployment, not designed here.

## Decision Needed

Owner chọn một: Approve kiến trúc đề xuất (Candidate A — hardened `production.yml`) và quyết định loại bỏ `deploy.yml`, để tiến sang giai đoạn implementation plan / Yêu cầu thay đổi thiết kế / Từ chối.

## What the owner is NOT being asked to decide

The Owner is not being asked to approve exact GitHub Actions YAML, exact shell script contents, exact host sizing/provider, exact domain, or exact credential-holder identities — only whether the recommended architecture (Candidate A), its target lifecycle/contracts (truthful status reporting, atomic release switching, schema-aware rollback, real health checks, proven backup/restore, host-provisioning checklist), and the `deploy.yml` retirement decision are the right direction before an implementation plan is written.
