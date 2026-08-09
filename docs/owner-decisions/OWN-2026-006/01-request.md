---
work_id: OWN-2026-006
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation-design.md
  plan: docs/superpowers/plans/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation.md
  branch: fix/OWN-2026-006-multi-work-gate3-digest-isolation
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-09T12:00:00+07:00"
  owner_response_reference: "ChatGPT project conversation — OWNER AUTHORIZATION — OWN-2026-006 MULTI-WORK-ITEM GATE-3 DIGEST ISOLATION, received 2026-08-09, explicitly states Gate 1 APPROVED and authorizes this governance-tooling correction."
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-09T12:00:00+07:00"
  updated_at: "2026-08-09T12:00:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

The owner approves a narrowly scoped governance-tooling correction to remove cross-work coupling from implementation-tree digests when multiple governed work items have Gate 3 packets in the same tree.

The correction is limited to digest classification, regression tests, and governance documentation. It does not authorize changes to GAP-010b or GAP-034 implementation, does not weaken freshness checks, and does not authorize Gate 3, Ready, merge, or release.

## Problem statement

The current digest excludes only the target work item's active Gate 3 packet. Another work item's Gate 3 packet remains included, so editing either packet changes the other's digest and can make an atomic multi-work release self-invalidating.

## Required outcome

Preserve the target work item's existing active-versus-superseded behavior, exclude every recognized Gate 3 packet belonging to other work IDs, and retain digest sensitivity for every non-Gate-3 path.
