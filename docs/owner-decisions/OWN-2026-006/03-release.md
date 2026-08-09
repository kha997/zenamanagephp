---
work_id: OWN-2026-006
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation-design.md
  plan: docs/superpowers/plans/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation.md
  branch: fix/OWN-2026-006-multi-work-gate3-digest-isolation
  pr: https://github.com/kha997/zenamanagephp/pull/254
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-09T15:52:11+07:00"
  updated_at: "2026-08-09T15:52:11+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "31 exact-head CI checks passed on provenance SHA 26e9ed59b1bcac65a2cac4e0b22238f66be320ed; the Draft-only deploy job was skipped as intended. Focused regression passed 11 tests / 16 assertions, scoped OwnerGovernance passed 158 tests / 537 assertions, both governance linters passed, and git diff check passed."
technical_evidence:
  subject_sha: "26e9ed59b1bcac65a2cac4e0b22238f66be320ed"
  implementation_tree_digest: "bf4aaea6053ce6df8d93c4da6eecd4d3d80c9f3e7286f720727192293301d797"
  verified_pr_head_sha: "26e9ed59b1bcac65a2cac4e0b22238f66be320ed"
  verified_at: "2026-08-09T15:52:11+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## OWN-2026-006 — Formal Gate 3 release packet

### Owner summary

The governance digest previously excluded only the target work item's active Gate-3 packet. In a combined release, another work item's Gate-3 packet remained part of the digest, so updating either release decision could invalidate the other's evidence. That cross-work coupling blocked a stable formal decision for GAP-010b and GAP-034.

The correction keeps the target work item's existing version-aware behavior: its active Gate-3 packet is excluded, while its superseded packets remain included and tamper-sensitive. It also excludes every recognized `03-release.md` or `03-release-vN.md` packet belonging to another work ID. All non-Gate-3 content remains digest-sensitive, including Gate 1/2 packets, specs, plans, governance schema and scripts, CI workflows, tests, application code, routes, migrations, and dependencies.

### Evidence

The focused temporary-Git-repository regression first demonstrated the defect with three expected failures. After the minimal implementation change, all 11 focused tests and 16 assertions passed. The matrix proves bidirectional isolation between Work A and Work B, exclusion of all recognized versions for another work, continued sensitivity of the target's own superseded packet, and continued sensitivity of every required non-Gate-3 category.

On exact provenance SHA `26e9ed59b1bcac65a2cac4e0b22238f66be320ed`, 31 GitHub checks passed, including Owner Governance Lint, unit/feature/integration/API/security/performance/browser tests, real-MySQL checks, route guardrails, staging smoke, and code/security analysis. The deploy job was skipped because PR #254 remains Draft.

The exact unscoped `./vendor/bin/phpunit --filter OwnerGovernance` command still stops during repository-wide discovery because the repository already contains a duplicate `Tests\Feature\Zena\ZenaRouteSurfaceInvariantTest` class. This was reproduced on untouched main before OWN-2026-006 and was not introduced or weakened by this PR. The scoped OwnerGovernance suite passed 158 tests and 537 assertions.

### Scope and behavior

This is governance tooling only. It changes no application route, runtime behavior, tenant boundary, migration, database data, or product workflow. GAP-010b and GAP-034 implementation files were not modified.

### Residual risk and rollback

Residual risk is low. The classification deliberately recognizes only canonical Gate-3 packet paths, while unrecognized release-like paths remain digest-sensitive. Regression tests bind the intended inclusion and exclusion rules. Rollback is a normal revert of PR #254; no data repair or migration rollback is required.

### Engineering recommendation

Approve release of OWN-2026-006, bound to implementation-tree digest:

`bf4aaea6053ce6df8d93c4da6eecd4d3d80c9f3e7286f720727192293301d797`

**Decision requested:** Approve, request correction, or defer.

**Owner decision:** Not yet recorded.
